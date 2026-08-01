<?php

declare(strict_types=1);

namespace Controller;

use OCA\StructuredDiary\Controller\AnalysisJobController;
use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Service\AnalysisArtifactArchiveService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AnalysisJobControllerTest extends TestCase {
	public function testIndexReturnsChangedJobsImmediately(): void {
		$job = $this->job(42);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->once())
			->method('getJobsForUser')
			->with('alice', 1713254400)
			->willReturn([$job]);

		$controller = $this->controller($jobMapper, ['waitBeforeNextLongPollCheck']);
		$controller->expects($this->never())->method('waitBeforeNextLongPollCheck');

		$response = $controller->index('2024-04-16T08:00:00Z', 30);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([$job], $response->getData());
	}

	public function testIndexLongPollsUntilChangedJobsAppear(): void {
		$job = $this->job(42);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->exactly(2))
			->method('getJobsForUser')
			->with('alice', 1713254400)
			->willReturnOnConsecutiveCalls([], [$job]);

		$controller = $this->controller($jobMapper, ['getCurrentTimestamp', 'waitBeforeNextLongPollCheck']);
		$controller->expects($this->exactly(2))->method('getCurrentTimestamp')->willReturn(1000);
		$controller->expects($this->once())->method('waitBeforeNextLongPollCheck');

		$response = $controller->index('2024-04-16T08:00:00Z', 30);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([$job], $response->getData());
	}

	public function testIndexReturnsEmptyListWhenLongPollTimeoutExpires(): void {
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->exactly(2))
			->method('getJobsForUser')
			->with('alice', 1713254400)
			->willReturn([]);

		$controller = $this->controller($jobMapper, ['getCurrentTimestamp', 'waitBeforeNextLongPollCheck']);
		$controller->expects($this->exactly(3))->method('getCurrentTimestamp')->willReturnOnConsecutiveCalls(1000, 1000, 1001);
		$controller->expects($this->once())->method('waitBeforeNextLongPollCheck');

		$response = $controller->index('2024-04-16T08:00:00Z', 1);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	public function testIndexAcceptsWaitAliasForLongPollTimeout(): void {
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->exactly(2))
			->method('getJobsForUser')
			->with('alice', 1713254400)
			->willReturn([]);

		$controller = $this->controller($jobMapper, ['getCurrentTimestamp', 'waitBeforeNextLongPollCheck']);
		$controller->expects($this->exactly(3))->method('getCurrentTimestamp')->willReturnOnConsecutiveCalls(1000, 1000, 1001);
		$controller->expects($this->once())->method('waitBeforeNextLongPollCheck');

		$response = $controller->index('2024-04-16T08:00:00Z', null, 1);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	public function testIndexRejectsInvalidLongPollTimeout(): void {
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->never())->method('getJobsForUser');

		$controller = $this->controller($jobMapper);

		$response = $controller->index('2024-04-16T08:00:00Z', 'soon');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'longPollTimeout must be a non-negative integer.'], $response->getData());
	}

	public function testCreatePassesCopyableAnalysisSettingsToMapper(): void {
		$created = $this->job(42);
		$parameters = ['includeTextAnalysis' => false, 'shifting_median_width' => 9];
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->once())
			->method('createJob')
			->with(
				5,
				'alice',
				1000,
				2000,
				'Copied analysis',
				'en-GB',
				false,
				['PDF', 'XLSX'],
				$parameters,
				'custom',
				'https://llm.example/v1/chat/completions',
				'{"Authorization":"Bearer secret"}',
			)
			->willReturn($created);

		$response = $this->controller($jobMapper)->create(
			5,
			1000,
			2000,
			'Copied analysis',
			'en-GB',
			false,
			['PDF', 'XLSX'],
			$parameters,
			'custom',
			'https://llm.example/v1/chat/completions',
			'{"Authorization":"Bearer secret"}',
		);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($created, $response->getData());
	}

	/**
	 * @dataProvider directDownloadMethods
	 */
	public function testDirectDownloadEndpointsAreCsrfExempt(string $method): void {
		$reflection = new ReflectionMethod(AnalysisJobController::class, $method);

		$this->assertNotEmpty($reflection->getAttributes(NoCSRFRequired::class), $method . ' must be usable as a direct browser download link.');
	}

	public function testArtifactContentDownloadsOneArtifactWhenRequested(): void {
		$archive = $this->createMock(AnalysisArtifactArchiveService::class);
		$archive->expects($this->once())
			->method('buildInlineArtifact')
			->with(42, 43, 'alice')
			->willReturn(['content' => 'report', 'filename' => 'report.html', 'contentType' => 'text/html']);
		$controller = new AnalysisJobController(
			'structureddiary',
			$this->createMock(IRequest::class),
			$this->createMock(AnalysisJobMapper::class),
			$this->createMock(AnalysisArtifactMapper::class),
			$archive,
			'alice',
		);

		$response = $controller->artifactContent(42, 43, '1');

		$this->assertInstanceOf(DataDownloadResponse::class, $response);
		$this->assertSame('report', $response->render());
	}

	public function testIntegratedArtifactViewRewritesReportLocalLinksAndRemovesActiveContent(): void {
		$archive = $this->createMock(AnalysisArtifactArchiveService::class);
		$archive->expects($this->once())
			->method('buildInlineArtifact')
			->with(42, 43, 'alice')
			->willReturn([
				'content' => '<html><body><script>alert(1)</script><a href="questions/mood.html">Mood</a><img src="plots/mood.svg"><a href="https://example.com">External</a></body></html>',
				'filename' => 'report.html',
				'contentType' => 'text/html',
			]);
		$artifacts = $this->createMock(AnalysisArtifactMapper::class);
		$artifacts->expects($this->once())->method('getArtifactsForJob')->with(42)->willReturn([
			$this->artifact(43, 'HTML', 'report.html', 'report.html'),
			$this->artifact(44, 'HTML', 'mood.html', 'questions/mood.html'),
			$this->artifact(45, 'PLOT', 'mood.svg', 'plots/mood.svg'),
		]);
		$controller = new AnalysisJobController(
			'structureddiary',
			$this->createMock(IRequest::class),
			$this->createMock(AnalysisJobMapper::class),
			$artifacts,
			$archive,
			'alice',
		);

		$response = $controller->integratedArtifactView(42, 43);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertStringContainsString('../44/integrated-view', $response->render());
		$this->assertStringContainsString('../45/content', $response->render());
		$this->assertStringNotContainsString('alert(1)', $response->render());
		$this->assertStringNotContainsString('https://example.com', $response->render());
	}

	public function testCopySettingsReturnsLlmHeaderWithoutExposingItInJobLists(): void {
		$job = $this->job(42);
		$job->setDataFrom(1000);
		$job->setTitle('Report');
		$job->setLanguage('en-GB');
		$job->setAnalysisType('standard');
		$job->setOutputTypes('["HTML"]');
		$job->setParametersJson('{"includeTextAnalysis":true}');
		$job->setLlmUrl('https://llm.example/v1/chat/completions');
		$job->setLlmHeader('{"Authorization":"Bearer secret"}');
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->once())->method('getJobForUser')->with(42, 'alice')->willReturn($job);

		$response = $this->controller($jobMapper)->copySettings(42);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			'data_from' => 1000,
			'title' => 'Report',
			'language' => 'en-GB',
			'analysis_type' => 'standard',
			'output_types' => ['HTML'],
			'parameters' => ['includeTextAnalysis' => true],
			'llm_url' => 'https://llm.example/v1/chat/completions',
			'llm_header' => '{"Authorization":"Bearer secret"}',
		], $response->getData());
	}

	/**
	 * @return list<array{string}>
	 */
	public static function directDownloadMethods(): array {
		return [
			['downloadArtifacts'],
			['downloadArtifactsByType'],
			['artifactContent'],
			['integratedArtifactView'],
		];
	}

	/**
	 * @param list<string> $methods
	 */
	private function controller(AnalysisJobMapper $jobMapper, array $methods = []): AnalysisJobController {
		$constructorArgs = [
			'structureddiary',
			$this->createMock(IRequest::class),
			$jobMapper,
			$this->createMock(AnalysisArtifactMapper::class),
			$this->createMock(AnalysisArtifactArchiveService::class),
			'alice',
		];

		if ($methods !== []) {
			return $this->getMockBuilder(AnalysisJobController::class)
				->setConstructorArgs($constructorArgs)
				->onlyMethods($methods)
				->getMock();
		}

		return new AnalysisJobController(...$constructorArgs);
	}

	private function job(int $id): AnalysisJob {
		$job = new AnalysisJob();
		$job->setId($id);

		return $job;
	}

	private function artifact(int $id, string $type, string $fileName, string $filePath): \OCA\StructuredDiary\Db\AnalysisArtifact {
		$artifact = new \OCA\StructuredDiary\Db\AnalysisArtifact();
		$artifact->setId($id);
		$artifact->setArtifactType($type);
		$artifact->setFileName($fileName);
		$artifact->setFilePath($filePath);
		return $artifact;
	}
}
