<?php

declare(strict_types=1);

namespace OCA\Tests\StructuredDiary\Integration;

use OCA\StructuredDiary\AppInfo\Application;
use OCA\StructuredDiary\Controller\AlarmSoundController;
use OCA\StructuredDiary\Controller\AnswerController;
use OCA\StructuredDiary\Controller\DiaryController;
use OCA\StructuredDiary\Controller\EntryController;
use OCA\StructuredDiary\Controller\QuestionController;
use OCA\StructuredDiary\Db\AlarmSoundMapper;
use OCA\StructuredDiary\Db\AnswerMapper;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCA\StructuredDiary\Db\DiaryShareMapper;
use OCA\StructuredDiary\Db\EntryMapper;
use OCA\StructuredDiary\Db\QuestionMapper;
use OCA\StructuredDiary\Db\QuestionTypes;
use OCA\Tests\StructuredDiary\Integration\TestUtil\IntegrationTestParentClass;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * @runTestsInSeparateProcesses
 */
final class FixtureExportIntegrationTest extends IntegrationTestParentClass {
	private AlarmSoundMapper $alarmSoundMapper;
	private DiaryMapper $diaryMapper;
	private DiaryShareMapper $shareMapper;
	private EntryMapper $entryMapper;
	private QuestionMapper $questionMapper;
	private AnswerMapper $answerMapper;
	/** @var list<string> */
	private array $backendFixtureFiles = [];

	protected function setUp(): void {
		parent::setUp();

		$this->alarmSoundMapper = self::$container->get(AlarmSoundMapper::class);
		$this->diaryMapper = self::$container->get(DiaryMapper::class);
		$this->shareMapper = self::$container->get(DiaryShareMapper::class);
		$this->entryMapper = self::$container->get(EntryMapper::class);
		$this->questionMapper = self::$container->get(QuestionMapper::class);
		$this->answerMapper = self::$container->get(AnswerMapper::class);
	}

	public function testExportCanonicalBackendPayloadFixtures(): void {
		$diaryController = $this->createDiaryController();
		$questionController = $this->createQuestionController();
		$entryController = $this->createEntryController();
		$answerController = $this->createAnswerController();
		$alarmSoundController = $this->createAlarmSoundController();
		$this->resetBackendFixtureOutput();

		$diary = $this->exportBackendMutationFixture(
			'diary_post',
			'POST',
			'/api/v1/diaries',
			[
				'body' => [
					'title' => 'Health journal',
					'description' => 'Daily notes',
					'reminderActive' => true,
					'reminderTime' => 32400,
					'reminderCount' => 3,
					'reminderDelay' => 2700,
					'reminderSignalFirst' => 'bell',
					'reminderSignalRepeat' => 'soft-bell',
					'entrySchedule' => 86400,
				],
			],
			fn (): DataResponse => $diaryController->create(
				'Health journal',
				'Daily notes',
				true,
				32400,
				3,
				2700,
				'bell',
				'soft-bell',
				86400,
			),
		);
		$this->exportBackendMutationFixture(
			'diary_share_post_writer',
			'POST',
			'/api/v1/diaries/' . $diary->getId() . '/shares',
			[
				'path' => ['id' => $diary->getId()],
				'body' => [
					'sharedWith' => 'bob',
					'permission' => DiaryPermissions::READ | DiaryPermissions::WRITE,
				],
			],
			fn (): DataResponse => $diaryController->createShare($diary->getId(), 'bob', DiaryPermissions::READ | DiaryPermissions::WRITE),
		);
		$carolShare = $this->exportBackendMutationFixture(
			'diary_share_post_reader',
			'POST',
			'/api/v1/diaries/' . $diary->getId() . '/shares',
			[
				'path' => ['id' => $diary->getId()],
				'body' => [
					'sharedWith' => 'carol',
					'permission' => DiaryPermissions::READ,
				],
			],
			fn (): DataResponse => $diaryController->createShare($diary->getId(), 'carol', DiaryPermissions::READ),
		);
		$this->exportBackendMutationFixture(
			'diary_share_put_permission',
			'PUT',
			'/api/v1/diaries/' . $diary->getId() . '/shares/' . $carolShare->getId(),
			[
				'path' => ['id' => $diary->getId(), 'shareId' => $carolShare->getId()],
				'body' => ['permission' => DiaryPermissions::READ | DiaryPermissions::ANALYZE],
			],
			fn (): DataResponse => $diaryController->updateShare($diary->getId(), $carolShare->getId(), DiaryPermissions::READ | DiaryPermissions::ANALYZE),
		);
		$this->exportBackendMutationFixture(
			'diary_post',
			'POST',
			'/api/v1/diaries',
			[
				'body' => [
					'title' => '   ',
					'description' => 'Invalid diary',
				],
			],
			fn (): DataResponse => $diaryController->create('   ', 'Invalid diary'),
			false,
		);

		$question = $this->exportBackendMutationFixture(
			'question_post_text',
			'POST',
			'/api/v1/diaries/' . $diary->getId() . '/questions',
			[
				'path' => ['diaryId' => $diary->getId()],
				'body' => [
					'label' => 'Mood',
					'displayText' => 'How do you feel today?',
					'type' => QuestionTypes::TEXT,
					'minimum' => null,
					'maximum' => null,
					'choices' => null,
					'active' => true,
					'templateText' => 'Write a short note',
				],
			],
			fn (): DataResponse => $questionController->create(
				$diary->getId(),
				'Mood',
				'How do you feel today?',
				QuestionTypes::TEXT,
				null,
				null,
				null,
				true,
				'Write a short note',
			),
		);
		$entryTimestamp = $question->getCreatedAt() + 3600;
		$entry = $this->exportBackendMutationFixture(
			'entry_post',
			'POST',
			'/api/v1/diaries/' . $diary->getId() . '/entries',
			[
				'path' => ['diaryId' => $diary->getId()],
				'body' => [
					'timestamp' => $entryTimestamp,
					'title' => 'Morning check-in',
				],
			],
			fn (): DataResponse => $entryController->create($diary->getId(), $entryTimestamp, 'Morning check-in'),
		);
		$answer = $this->exportBackendMutationFixture(
			'answer_post_text',
			'POST',
			'/api/v1/entries/' . $entry->getId() . '/answers',
			[
				'path' => ['entryId' => $entry->getId()],
				'body' => [
					'questionId' => $question->getId(),
					'textContent' => 'Feeling stable today.',
					'numericContent' => null,
				],
			],
			fn (): DataResponse => $answerController->create($entry->getId(), $question->getId(), 'Feeling stable today.', null),
		);
		$currentAnswer = $this->exportBackendMutationFixture(
			'answer_put_text',
			'PUT',
			'/api/v1/answers/' . $answer->getId(),
			[
				'path' => ['id' => $answer->getId()],
				'body' => [
					'textContent' => 'Feeling better now.',
					'numericContent' => null,
				],
			],
			fn (): DataResponse => $answerController->update($answer->getId(), 'Feeling better now.', null),
		);
		$currentQuestion = $this->exportBackendMutationFixture(
			'question_put_text',
			'PUT',
			'/api/v1/questions/' . $question->getId(),
			[
				'path' => ['id' => $question->getId()],
				'body' => [
					'label' => 'Mood check-in',
					'displayText' => 'How do you feel today, in detail?',
					'type' => QuestionTypes::TEXT,
					'minimum' => null,
					'maximum' => null,
					'choices' => null,
					'active' => true,
					'templateText' => 'Write a longer note',
				],
			],
			fn (): DataResponse => $questionController->update(
				$question->getId(),
				'Mood check-in',
				'How do you feel today, in detail?',
				QuestionTypes::TEXT,
				null,
				null,
				null,
				true,
				'Write a longer note',
			),
		);
		$this->exportBackendMutationFixture(
			'question_put_text',
			'PUT',
			'/api/v1/questions/' . $question->getId(),
			[
				'path' => ['id' => $question->getId()],
				'body' => [
					'label' => '   ',
					'displayText' => null,
				],
			],
			fn (): DataResponse => $questionController->update($question->getId(), '   ', null),
			false,
		);
		$alarmSound = $this->exportBackendMutationFixture(
			'alarm_sound_post',
			'POST',
			'/api/v1/alarm-sounds',
			[
				'body' => [
					'name' => 'Bell',
					'path' => 'bell',
					'osAffinity' => ['ios:17'],
					'isDefault' => true,
				],
			],
			fn (): DataResponse => $alarmSoundController->create('Bell', 'bell', ['ios:17'], true),
		);
		$alarmSound = $this->exportBackendMutationFixture(
			'alarm_sound_patch_affinity',
			'PATCH',
			'/api/v1/alarm-sounds/' . $alarmSound->getId(),
			[
				'path' => ['id' => $alarmSound->getId()],
				'body' => [
					'osAffinity' => ['android:15'],
				],
			],
			fn (): DataResponse => $alarmSoundController->patch($alarmSound->getId(), null, null, ['android:15'], null),
		);
		$this->exportBackendMutationFixture(
			'alarm_sound_put',
			'PUT',
			'/api/v1/alarm-sounds/' . $alarmSound->getId(),
			[
				'path' => ['id' => $alarmSound->getId()],
				'body' => [
					'name' => 'Soft bell',
					'path' => 'soft-bell',
					'osAffinity' => ['ios:17', 'android:15'],
					'isDefault' => false,
				],
			],
			fn (): DataResponse => $alarmSoundController->update($alarmSound->getId(), 'Soft bell', 'soft-bell', ['ios:17', 'android:15'], false),
		);

		$base = 'cypress/';
		$files = [
			'question-types.json' => [
				['id' => 'TEXT', 'value' => QuestionTypes::TEXT],
				['id' => 'BOOLEAN', 'value' => QuestionTypes::BOOLEAN],
				['id' => 'RATING', 'value' => QuestionTypes::RATING],
				['id' => 'NUMBER', 'value' => QuestionTypes::NUMBER],
				['id' => 'INTEGER', 'value' => QuestionTypes::INTEGER],
				['id' => 'TIME', 'value' => QuestionTypes::TIME],
				['id' => 'SELECT', 'value' => QuestionTypes::SELECT],
				['id' => 'EDITABLE_SELECT', 'value' => QuestionTypes::EDITABLE_SELECT],
			],
			'diaries.json' => $this->diaryMapper->getAccessibleDiaries('alice'),
			'diary-detail.json' => $this->diaryMapper->getDiaryForUser($diary->getId(), 'alice', DiaryPermissions::READ),
			'diary-shares.json' => $this->shareMapper->getSharesForDiary($diary->getId()),
			'diary-stats.json' => $this->diaryMapper->getDiaryStats($diary->getId(), 'alice'),
			'entries.json' => $this->entryMapper->getEntriesForDiary($diary->getId()),
			'questions.json' => $this->questionMapper->getCurrentQuestionsForDiary($diary->getId()),
			'question-versions.json' => $this->questionMapper->getQuestionChain($question->getId()),
			'answers-versioned.json' => $this->answerMapper->getCurrentAnswersForEntry($entry->getId()),
			'answer-history.json' => $this->answerMapper->getAnswerChainForEntryQuestion($entry->getId(), $question->getId()),
			'alarm-sounds.json' => $this->alarmSoundMapper->getAlarmSounds(),
			'backend/manifest.json' => [
				'note' => 'Each backend mutation fixture is split into one request file and one backend result file.',
				'files' => $this->backendFixtureFiles,
			],
			'manifest.json' => [
				'diaryId' => $diary->getId(),
				'entryId' => $entry->getId(),
				'questionId' => $question->getId(),
				'currentQuestionId' => $currentQuestion->getId(),
				'answerId' => $answer->getId(),
				'currentAnswerId' => $currentAnswer->getId(),
				'note' => 'Generated from integration tests. Copy manually to cypress/fixtures only after reviewing ID-dependent tests.',
			],
		];

		foreach ($files as $file => $payload) {
			$path = $this->writeGeneratedFixture($base . $file, $payload);
			$this->assertFileExists($path);
		}
	}

	/**
	 * @param array<string, mixed> $input
	 * @param callable(): DataResponse $call
	 */
	private function exportBackendMutationFixture(string $name, string $method, string $endpoint, array $input, callable $call, bool $expectSuccess = true): mixed {
		$response = $call();
		$status = $response->getStatus();
		if ($expectSuccess) {
			$this->assertGreaterThanOrEqual(200, $status, $method . ' ' . $endpoint . ' failed.');
			$this->assertLessThan(300, $status, $method . ' ' . $endpoint . ' failed.');
		} else {
			$this->assertGreaterThanOrEqual(400, $status, $method . ' ' . $endpoint . ' unexpectedly succeeded.');
		}
		$result = $response->getData();
		$outcome = $expectSuccess ? 'ok' : 'error';
		$requestFile = 'backend/' . $name . '_request_' . $outcome . '.json';
		$resultFile = 'backend/' . $name . '_result_' . $outcome . '.json';

		$requestPath = $this->writeGeneratedFixture('cypress/' . $requestFile, [
			'method' => $method,
			'endpoint' => $endpoint,
			'input' => $input,
		]);
		$resultPath = $this->writeGeneratedFixture('cypress/' . $resultFile, [
			'status' => $status,
			'result' => $result,
		]);
		$this->assertFileExists($requestPath);
		$this->assertFileExists($resultPath);
		$this->backendFixtureFiles[] = $requestFile;
		$this->backendFixtureFiles[] = $resultFile;

		return $result;
	}

	private function resetBackendFixtureOutput(): void {
		$root = dirname(__DIR__) . '/generated-fixtures/cypress';
		$legacyAggregate = $root . '/backend-mutations.json';
		if (is_file($legacyAggregate) && !unlink($legacyAggregate)) {
			throw new \RuntimeException('Unable to remove generated fixture: ' . $legacyAggregate);
		}

		$backendDirectory = $root . '/backend';
		if (!is_dir($backendDirectory)) {
			return;
		}

		foreach (glob($backendDirectory . '/*.json') ?: [] as $file) {
			if (!unlink($file)) {
				throw new \RuntimeException('Unable to remove generated fixture: ' . $file);
			}
		}
	}

	private function createDiaryController(): DiaryController {
		return new DiaryController(Application::APP_ID, $this->createMock(IRequest::class), $this->diaryMapper, $this->shareMapper, 'alice');
	}

	private function createQuestionController(): QuestionController {
		return new QuestionController(Application::APP_ID, $this->createMock(IRequest::class), $this->diaryMapper, $this->questionMapper, 'alice');
	}

	private function createEntryController(): EntryController {
		return new EntryController(Application::APP_ID, $this->createMock(IRequest::class), $this->diaryMapper, $this->entryMapper, $this->answerMapper, 'alice');
	}

	private function createAnswerController(): AnswerController {
		return new AnswerController(Application::APP_ID, $this->createMock(IRequest::class), $this->diaryMapper, $this->entryMapper, $this->questionMapper, $this->answerMapper, 'alice');
	}

	private function createAlarmSoundController(): AlarmSoundController {
		return new AlarmSoundController(Application::APP_ID, $this->createMock(IRequest::class), $this->alarmSoundMapper, 'alice');
	}
}
