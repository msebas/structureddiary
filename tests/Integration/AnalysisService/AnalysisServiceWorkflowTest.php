<?php

declare(strict_types=1);

namespace OCA\Tests\StructuredDiary\Integration\AnalysisService;

use OCA\StructuredDiary\AppInfo\Application;
use OCA\StructuredDiary\Controller\AnalysisAdminSettingsController;
use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Db\AnswerMapper;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\EntryMapper;
use OCA\StructuredDiary\Db\QuestionMapper;
use OCA\StructuredDiary\Db\QuestionTypes;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCA\StructuredDiary\Service\AnalysisServiceClient;
use OCA\Tests\StructuredDiary\Integration\TestUtil\IntegrationTestParentClass;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUserManager;

/**
 * This is a guard for the real Python service workflow described in README.md.
 * It remains skipped until the service image and disposable Nextcloud endpoint
 * are supplied by the integration environment.
 *
 * @group analysis-service
 */
final class AnalysisServiceWorkflowTest extends IntegrationTestParentClass {
	private AnalysisJobMapper $jobMapper;
	private AnalysisArtifactMapper $artifactMapper;
	private DiaryMapper $diaryMapper;
	private QuestionMapper $questionMapper;
	private EntryMapper $entryMapper;
	private AnswerMapper $answerMapper;
	private AnalysisConfigService $configService;
	private IUserManager $userManager;

	protected function setUp(): void {
		if (getenv('ANALYSIS_SERVICE_SYSTEM_TEST') !== '1') {
			$this->markTestSkipped('Set ANALYSIS_SERVICE_SYSTEM_TEST=1 to run the real Python analysis-service workflow.');
		}

		parent::setUp();
		$this->jobMapper = self::$container->get(AnalysisJobMapper::class);
		$this->artifactMapper = self::$container->get(AnalysisArtifactMapper::class);
		$this->diaryMapper = self::$container->get(DiaryMapper::class);
		$this->questionMapper = self::$container->get(QuestionMapper::class);
		$this->entryMapper = self::$container->get(EntryMapper::class);
		$this->answerMapper = self::$container->get(AnswerMapper::class);
		$this->configService = self::$container->get(AnalysisConfigService::class);
		$this->userManager = self::$container->get(IUserManager::class);
	}

	public function testRealAnalysisServiceWorkflowIsExplicitlyEnabled(): void {
		$serviceUrl = $this->requiredEnvironment('ANALYSIS_SERVICE_URL');
		$serviceSecret = $this->requiredEnvironment('ANALYSIS_SERVICE_SECRET');
		$this->registerNextcloudInstance($serviceUrl, $serviceSecret);

		$now = time();
		$userId = 'analysis-system-test-' . bin2hex(random_bytes(6));
		$this->assertNotFalse($this->userManager->createUser($userId, bin2hex(random_bytes(16))));
		$diary = $this->diaryMapper->createDiary($userId, 'Analysis service integration', 'Numeric-only system-test diary');
		$question = $this->questionMapper->createQuestion($diary->getId(), 'Mood', 'How is your mood?', QuestionTypes::RATING, 1.0, 5.0, null, true, '');
		$entry = $this->entryMapper->createEntry($diary->getId(), $now, 'System test entry');
		$this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $question->getId(), null, 4.0);
		$job = $this->jobMapper->createJob(
			$diary->getId(),
			$userId,
			$now - 60,
			$now + 60,
			'Analysis service system test',
			'en',
			true,
			['JSON', 'HTML'],
			['includeTextAnalysis' => false],
		);

		$completed = $this->waitForCompletedJob($job->getId(), $this->timeoutSeconds());
		$artifacts = $this->artifactMapper->getArtifactsForJob($completed->getId());

		$this->assertSame(AnalysisJob::STATUS_COMPLETED, $completed->getStatus());
		$this->assertNotEmpty($artifacts);
		$this->assertContains('JSON', array_map(static fn ($artifact): string => $artifact->getArtifactType(), $artifacts));
		$this->assertContains('HTML', array_map(static fn ($artifact): string => $artifact->getArtifactType(), $artifacts));
		foreach ($artifacts as $artifact) {
			$this->assertTrue($artifact->getDownloaded(), $artifact->getFilePath() . ' was not uploaded.');
			$this->assertNotNull($artifact->getFileId(), $artifact->getFilePath() . ' has no Nextcloud file ID.');
			$this->assertNotNull($artifact->getChecksum(), $artifact->getFilePath() . ' has no checksum.');
			$this->assertNotSame('', $artifact->getChecksum(), $artifact->getFilePath() . ' has no checksum.');
		}
	}

	private function registerNextcloudInstance(string $serviceUrl, string $serviceSecret): void {
		$controller = new AnalysisAdminSettingsController(
			Application::APP_ID,
			$this->createMock(IRequest::class),
			$this->configService,
			new AnalysisServiceClient(),
		);
		$response = $controller->save($serviceUrl, $serviceSecret, '/StructuredDiary/AnalysisServiceSystemTest');
		$this->assertSame(Http::STATUS_OK, $response->getStatus(), (string)json_encode($response->getData()));

		$health = $controller->testConnection($serviceUrl, $this->configService->getNextcloudApiToken());
		$this->assertSame(Http::STATUS_OK, $health->getStatus(), (string)json_encode($health->getData()));
		$this->assertTrue($health->getData()['ok']);
	}

	private function waitForCompletedJob(int $jobId, int $timeout): AnalysisJob {
		$deadline = microtime(true) + $timeout;
		$lastJob = $this->jobMapper->getJob($jobId);
		while (microtime(true) < $deadline) {
			$lastJob = $this->jobMapper->getJob($jobId);
			if ($lastJob->getStatus() === AnalysisJob::STATUS_COMPLETED) {
				return $lastJob;
			}
			usleep(500000);
		}

		$this->fail(sprintf(
			'Analysis service did not complete job %s within %d seconds; last status=%s, message=%s, error=%s.',
			$lastJob->getUuid(),
			$timeout,
			$lastJob->getStatus(),
			$lastJob->getStatusMessage(),
			$lastJob->getErrorMessage() ?? '',
		));
	}

	private function timeoutSeconds(): int {
		$timeout = getenv('ANALYSIS_SERVICE_TIMEOUT');
		return is_string($timeout) && ctype_digit($timeout) ? max(1, (int)$timeout) : 120;
	}

	private function requiredEnvironment(string $name): string {
		$value = getenv($name);
		if (!is_string($value) || trim($value) === '') {
			throw new \RuntimeException($name . ' must be set for the analysis-service system test.');
		}

		return trim($value);
	}
}
