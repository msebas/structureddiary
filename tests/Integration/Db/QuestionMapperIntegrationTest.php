<?php

declare(strict_types=1);

namespace OCA\Tests\StructuredDiary\Integration\Db;

use OCA\StructuredDiary\Db\AnswerMapper;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\EntryMapper;
use OCA\StructuredDiary\Db\QuestionMapper;
use OCA\StructuredDiary\Db\QuestionTypes;
use OCA\Tests\StructuredDiary\Integration\TestUtil\IntegrationTestParentClass;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * @runTestsInSeparateProcesses
 */
final class QuestionMapperIntegrationTest extends IntegrationTestParentClass {
	private DiaryMapper $diaryMapper;
	private EntryMapper $entryMapper;
	private QuestionMapper $questionMapper;
	private AnswerMapper $answerMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->diaryMapper = self::$container->get(DiaryMapper::class);
		$this->entryMapper = self::$container->get(EntryMapper::class);
		$this->questionMapper = self::$container->get(QuestionMapper::class);
		$this->answerMapper = self::$container->get(AnswerMapper::class);
	}

	public function testCreateFetchAndGetCurrentQuestionsReturnOnlyCurrentVersions(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$entry = $this->entryMapper->createEntry($diary->getId(), 1713254400, 'day');
		$questionA = $this->questionMapper->createQuestion($diary->getId(), 'Mood', 'Mood', QuestionTypes::TEXT, null, null, null, true, '');
		$questionB = $this->questionMapper->createQuestion($diary->getId(), 'Color', 'Color', QuestionTypes::SELECT, null, null, ['red', 'blue'], true, '');
		$this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $questionA->getId(), 'good', null);
		$questionANext = $this->questionMapper->updateQuestion($questionA, 'Mood now', 'Mood now', QuestionTypes::TEXT, null, null, null, true, 'later');

		$fetched = $this->questionMapper->getQuestion($questionB->getId());
		$this->assertSame(['red', 'blue'], $fetched->getChoices());

		$current = $this->questionMapper->getCurrentQuestionsForDiary($diary->getId());

		$this->assertCount(2, $current);
		$this->assertSame([$questionB->getId(), $questionANext->getId()], array_map(static fn ($question) => $question->getId(), $current));
	}

	public function testGetQuestionChainReturnsAllVersionsInChronologicalOrder(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$entry = $this->entryMapper->createEntry($diary->getId(), 1713254400, 'day');
		$first = $this->questionMapper->createQuestion($diary->getId(), 'Q1', 'Q1', QuestionTypes::TEXT, null, null, null, true, '');
		$this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $first->getId(), 'first', null);
		$second = $this->questionMapper->updateQuestion($first, 'Q2', 'Q2', QuestionTypes::TEXT, null, null, null, true, '');
		$third = $this->questionMapper->updateQuestion($second, 'Q3', 'Q3', QuestionTypes::TEXT, null, null, null, true, '');

		$chain = $this->questionMapper->getQuestionChain($second->getId());

		$this->assertSame([$first->getId(), $second->getId(), $third->getId()], array_map(static fn ($question) => $question->getId(), $chain));
	}

	public function testGetQuestionsForDiaryAtTimestampReturnsQuestionsVisibleAtThatTime(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$first = $this->questionMapper->createQuestion($diary->getId(), 'Mood', 'Mood', QuestionTypes::TEXT, null, null, null, true, '');
		sleep(1);
		$other = $this->questionMapper->createQuestion($diary->getId(), 'Energy', 'Energy', QuestionTypes::TEXT, null, null, null, false, '');

		$before = $this->questionMapper->getQuestionsForDiaryAtTimestamp($diary->getId(), $first->getCreatedAt(), false);
		$after = $this->questionMapper->getQuestionsForDiaryAtTimestamp($diary->getId(), $other->getCreatedAt(), false);

		$this->assertSame([$first->getId()], array_map(static fn ($question) => $question->getId(), $before));
		$this->assertSame([$other->getId(), $first->getId()], array_map(static fn ($question) => $question->getId(), $after));
	}

	public function testCreateQuestionPersistsCreatedAt(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');

		$question = $this->questionMapper->createQuestion($diary->getId(), 'Mood', 'Mood', QuestionTypes::TEXT, null, null, null, true, '');
		$reloaded = $this->questionMapper->getQuestion($question->getId());

		$this->assertGreaterThan(0, $reloaded->getCreatedAt());
	}

	public function testCreateQuestionRejectsEmptySelectionChoice(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Selection choices cannot be empty.');

		$this->questionMapper->createQuestion($diary->getId(), 'Mood', 'Mood', QuestionTypes::SELECT, null, null, ['yes', ' '], true, '');
	}

	public function testCreateQuestionRejectsFractionalIntegerBounds(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Integer questions must use whole-number minimum and maximum values.');

		$this->questionMapper->createQuestion($diary->getId(), 'Count', 'Count', QuestionTypes::INTEGER, 1.5, 3.0, null, true, '');
	}

	public function testUpdateQuestionWithoutAnswersPersistsInPlace(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$question = $this->questionMapper->createQuestion($diary->getId(), 'Mood', 'Mood', QuestionTypes::TEXT, null, null, null, true, '');
		$originalCreatedAt = $question->getCreatedAt();

		$updated = $this->questionMapper->updateQuestion(
			$question,
			'Score',
			'Score',
			QuestionTypes::NUMBER,
			1.0,
			10.0,
			null,
			false,
			'template',
		);

		$this->assertSame($question->getId(), $updated->getId());
		$this->assertSame($originalCreatedAt, $updated->getCreatedAt());
		$this->assertNull($updated->getPreviousVersionId());
		$this->assertNull($updated->getNextVersionId());

		$reloaded = $this->questionMapper->getQuestion($question->getId());
		$this->assertSame(QuestionTypes::NUMBER, $reloaded->getType());
		$this->assertSame(1.0, $reloaded->getMinimum());
		$this->assertSame(10.0, $reloaded->getMaximum());
		$this->assertFalse($reloaded->getActive());
		$this->assertSame('template', $reloaded->getTemplateText());
		$this->assertSame($originalCreatedAt, $reloaded->getCreatedAt());
	}

	public function testUpdateQuestionWithAnswersCreatesVersionAndBlocksHistoricalMutation(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$entry = $this->entryMapper->createEntry($diary->getId(), 1713254400, 'day');
		$question = $this->questionMapper->createQuestion($diary->getId(), 'Mood', 'Mood', QuestionTypes::TEXT, null, null, null, true, '');
		$this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $question->getId(), 'good', null);

		$newVersion = $this->questionMapper->updateQuestion($question, 'Mood 2', 'Mood 2', QuestionTypes::TEXT, null, null, null, true, 'new');
		$reloadedOriginal = $this->questionMapper->getQuestion($question->getId());

		$this->assertSame($question->getId(), $newVersion->getPreviousVersionId());
		$this->assertSame($newVersion->getId(), $reloadedOriginal->getNextVersionId());
		$this->assertGreaterThanOrEqual($reloadedOriginal->getCreatedAt(), $newVersion->getCreatedAt());

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Only the current question version can be changed.');
		$this->questionMapper->updateQuestion($reloadedOriginal, 'invalid', 'invalid', QuestionTypes::TEXT, null, null, null, true, '');
	}

	public function testDeleteQuestionReassignsAnswersToCompatibleNextVersionAndReconnectsChain(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$entry = $this->entryMapper->createEntry($diary->getId(), 1713254400, 'day');
		$question = $this->questionMapper->createQuestion($diary->getId(), 'Choice', 'Choice', QuestionTypes::SELECT, null, null, ['yes', 'no'], true, '');
		$answer = $this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $question->getId(), 'yes', null);
		$next = $this->questionMapper->updateQuestion($question, 'Choice 2', 'Choice 2', QuestionTypes::SELECT, null, null, ['yes', 'no', 'maybe'], true, '');

		$this->questionMapper->deleteQuestion($question);

		$reassignedAnswer = $this->answerMapper->getAnswer($answer->getId());
		$remaining = $this->questionMapper->getQuestion($next->getId());

		$this->assertSame($next->getId(), $reassignedAnswer->getQuestionId());
		$this->assertNull($remaining->getPreviousVersionId());

		$this->expectException(DoesNotExistException::class);
		$this->questionMapper->getQuestion($question->getId());
	}

	public function testDeleteQuestionRejectsWhenExistingAnswersWouldBecomeInvalid(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$entry = $this->entryMapper->createEntry($diary->getId(), 1713254400, 'day');
		$question = $this->questionMapper->createQuestion($diary->getId(), 'Choice', 'Choice', QuestionTypes::SELECT, null, null, ['yes', 'no'], true, '');
		$this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $question->getId(), 'yes', null);
		$this->questionMapper->updateQuestion($question, 'Choice 2', 'Choice 2', QuestionTypes::SELECT, null, null, ['no'], true, '');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Questions with answers cannot be deleted without a compatible replacement version.');

		$this->questionMapper->deleteQuestion($this->questionMapper->getQuestion($question->getId()));
	}

	public function testDeleteQuestionFallsBackToPreviousVersionWhenNextIsInvalid(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$entryOne = $this->entryMapper->createEntry($diary->getId(), 1713254400, 'day one');
		$entryTwo = $this->entryMapper->createEntry($diary->getId(), 1713340800, 'day two');
		$first = $this->questionMapper->createQuestion($diary->getId(), 'Choice', 'Choice', QuestionTypes::SELECT, null, null, ['yes', 'no'], true, '');
		$this->answerMapper->createAnswer($diary->getId(), $entryOne->getId(), $first->getId(), 'yes', null);
		$second = $this->questionMapper->updateQuestion($first, 'Choice 2', 'Choice 2', QuestionTypes::SELECT, null, null, ['yes', 'no'], true, '');
		$answer = $this->answerMapper->createAnswer($diary->getId(), $entryTwo->getId(), $second->getId(), 'yes', null);
		$third = $this->questionMapper->updateQuestion($second, 'Choice 3', 'Choice 3', QuestionTypes::SELECT, null, null, ['no'], true, '');

		$this->questionMapper->deleteQuestion($this->questionMapper->getQuestion($second->getId()));

		$reassignedAnswer = $this->answerMapper->getAnswer($answer->getId());
		$previous = $this->questionMapper->getQuestion($first->getId());
		$next = $this->questionMapper->getQuestion($third->getId());

		$this->assertSame($first->getId(), $reassignedAnswer->getQuestionId());
		$this->assertSame($third->getId(), $previous->getNextVersionId());
		$this->assertSame($first->getId(), $next->getPreviousVersionId());
	}

	public function testDeletingMiddleQuestionWithoutAnswersReconnectsChain(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$entry = $this->entryMapper->createEntry($diary->getId(), 1713254400, 'day');
		$first = $this->questionMapper->createQuestion($diary->getId(), 'Q1', 'Q1', QuestionTypes::TEXT, null, null, null, true, '');
		$answer = $this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $first->getId(), 'v1', null);
		$second = $this->questionMapper->updateQuestion($first, 'Q2', 'Q2', QuestionTypes::TEXT, null, null, null, true, '');
		$third = $this->questionMapper->updateQuestion($second, 'Q3', 'Q3', QuestionTypes::TEXT, null, null, null, true, '');
		$this->answerMapper->deleteAnswer($answer);

		$this->questionMapper->deleteQuestion($this->questionMapper->getQuestion($second->getId()));

		$reloadedFirst = $this->questionMapper->getQuestion($first->getId());
		$reloadedThird = $this->questionMapper->getQuestion($third->getId());
		$this->assertSame($third->getId(), $reloadedFirst->getNextVersionId());
		$this->assertSame($first->getId(), $reloadedThird->getPreviousVersionId());

		$this->expectException(DoesNotExistException::class);
		$this->questionMapper->getQuestion($second->getId());
	}

	public function testHistoricalQuestionWithAnswersLaterInChainCannotBeUpdatedInPlace(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$entry = $this->entryMapper->createEntry($diary->getId(), 1713254400, 'day');
		$first = $this->questionMapper->createQuestion($diary->getId(), 'Choice', 'Choice', QuestionTypes::TEXT, null, null, null, true, '');
		$firstAnswer = $this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $first->getId(), 'yes', null);
		$second = $this->questionMapper->updateQuestion($first, 'Choice 2', 'Choice 2', QuestionTypes::TEXT, null, null, null, true, '');
		$this->answerMapper->deleteAnswer($firstAnswer);
		$this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $second->getId(), 'yes', null);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Only the current question version can be changed.');
		$this->questionMapper->updateQuestion($this->questionMapper->getQuestion($first->getId()), 'invalid', 'invalid', QuestionTypes::TEXT, null, null, null, true, '');
	}
}
