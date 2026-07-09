<?php

declare(strict_types=1);

namespace OCA\Tests\StructuredDiary\Integration;

use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Db\AnswerMapper;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\EntryMapper;
use OCA\StructuredDiary\Db\QuestionMapper;
use OCA\StructuredDiary\Db\QuestionTypes;
use OCA\StructuredDiary\Service\AnalysisExportService;
use OCA\Tests\StructuredDiary\Integration\TestUtil\IntegrationTestParentClass;

/**
 * @runTestsInSeparateProcesses
 */
final class AnalysisExportServiceIntegrationTest extends IntegrationTestParentClass {
	private DiaryMapper $diaryMapper;
	private QuestionMapper $questionMapper;
	private EntryMapper $entryMapper;
	private AnswerMapper $answerMapper;
	private AnalysisJobMapper $jobMapper;
	private AnalysisExportService $exportService;

	protected function setUp(): void {
		parent::setUp();

		$this->diaryMapper = self::$container->get(DiaryMapper::class);
		$this->questionMapper = self::$container->get(QuestionMapper::class);
		$this->entryMapper = self::$container->get(EntryMapper::class);
		$this->answerMapper = self::$container->get(AnswerMapper::class);
		$this->jobMapper = self::$container->get(AnalysisJobMapper::class);
		$this->exportService = self::$container->get(AnalysisExportService::class);
	}

	public function testExportDiaryAndEntriesUsesDateRangeAndCurrentAnswers(): void {
		$now = time();
		$diary = $this->diaryMapper->createDiary('alice', 'Mood Diary', 'desc', false, 0, 3, 2700, '', '', 86400);
		$mood = $this->questionMapper->createQuestion($diary->getId(), 'Mood', 'How are you?', QuestionTypes::RATING, 1.0, 5.0, null, true, '');
		$text = $this->questionMapper->createQuestion($diary->getId(), 'Text', 'Notes', QuestionTypes::TEXT, null, null, null, true, '{{text}}');
		$tooOld = $this->entryMapper->createEntry($diary->getId(), $now - 200, 'old');
		$inRange = $this->entryMapper->createEntry($diary->getId(), $now, 'in range');
		$tooNew = $this->entryMapper->createEntry($diary->getId(), $now + 200, 'new');

		$this->answerMapper->createAnswer($diary->getId(), $tooOld->getId(), $mood->getId(), null, 1.0);
		$moodAnswer = $this->answerMapper->createAnswer($diary->getId(), $inRange->getId(), $mood->getId(), null, 2.0);
		$currentMoodAnswer = $this->answerMapper->updateAnswer($moodAnswer, null, 4.0);
		$textAnswer = $this->answerMapper->createAnswer($diary->getId(), $inRange->getId(), $text->getId(), 'hello', null);
		$this->answerMapper->createAnswer($diary->getId(), $tooNew->getId(), $mood->getId(), null, 5.0);

		$job = $this->jobMapper->createJob($diary->getId(), 'alice', $now - 100, $now + 100, 'Report', 'en', false);
		$job->setStatus(AnalysisJob::STATUS_LOAD_DATA);
		$job = $this->jobMapper->update($job);

		$diaryExport = $this->exportService->exportDiary($job);
		$this->assertSame(AnalysisExportService::SCHEMA_VERSION, $diaryExport['schema_version']);
		$this->assertSame('Mood Diary', $diaryExport['diary']['title']);
		$this->assertEqualsCanonicalizing([$mood->getId(), $text->getId()], array_map(static fn (array $question): int => $question['id'], $diaryExport['questions']));

		$entriesExport = $this->exportService->exportEntries($job);
		$this->assertSame(1, $entriesExport['total_entries']);
		$this->assertFalse($entriesExport['has_more']);
		$this->assertCount(1, $entriesExport['entries']);
		$this->assertSame($inRange->getId(), $entriesExport['entries'][0]['id']);
		$this->assertEqualsCanonicalizing(
			[$currentMoodAnswer->getId(), $textAnswer->getId()],
			array_map(static fn (array $answer): int => $answer['id'], $entriesExport['entries'][0]['answers'])
		);
		$this->assertEqualsCanonicalizing(
			[4.0, null],
			array_map(static fn (array $answer): ?float => $answer['numericContent'], $entriesExport['entries'][0]['answers'])
		);
	}

	public function testExportEntriesDoesNotLeakAnswersForQuestionsOutsideJobDiary(): void {
		$now = time();
		$diary = $this->diaryMapper->createDiary('alice', 'Private Diary', 'desc');
		$otherDiary = $this->diaryMapper->createDiary('bob', 'Other Diary', 'desc');
		$allowedQuestion = $this->questionMapper->createQuestion($diary->getId(), 'Mood', 'Mood?', QuestionTypes::RATING, 1.0, 5.0, null, true, '');
		$foreignQuestion = $this->questionMapper->createQuestion($otherDiary->getId(), 'Secret', 'Secret?', QuestionTypes::TEXT, null, null, null, true, '');
		$entry = $this->entryMapper->createEntry($diary->getId(), $now, 'entry');
		$allowedAnswer = $this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $allowedQuestion->getId(), null, 3.0);

		// Simulates malformed DB state that normal controllers reject. Export must still not leak it.
		$foreignAnswer = $this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $foreignQuestion->getId(), 'do not export', null);

		$job = $this->jobMapper->createJob($diary->getId(), 'alice', $now - 10, $now + 10, 'Report', 'en', false);
		$job->setStatus(AnalysisJob::STATUS_LOAD_DATA);
		$job = $this->jobMapper->update($job);

		$entriesExport = $this->exportService->exportEntries($job);
		$answerIds = array_map(static fn (array $answer): int => $answer['id'], $entriesExport['entries'][0]['answers']);

		$this->assertContains($allowedAnswer->getId(), $answerIds);
		$this->assertNotContains($foreignAnswer->getId(), $answerIds);
		$this->assertSame(['Mood'], array_map(static fn (array $question): string => $question['label'], $this->exportService->exportDiary($job)['questions']));
	}
}
