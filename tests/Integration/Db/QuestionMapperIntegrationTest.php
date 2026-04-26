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
		$this->assertSame($questionB->getChainId(), $questionB->getId());
		$this->assertSame($questionANext->getChainId(), $questionA->getChainId());
		$this->assertSame($questionB->getDiaryQuestionOrder(), $questionB->getId());
		$this->assertSame($questionANext->getDiaryQuestionOrder(), $questionA->getDiaryQuestionOrder());
		$this->assertSame([$questionANext->getId(), $questionB->getId()], array_map(static fn ($question) => $question->getId(), $current));
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
		$this->assertCount(1, array_unique(array_map(static fn ($question) => $question->getChainId(), $chain)));
	}

	public function testGetQuestionsForDiaryAtTimestampReturnsQuestionsVisibleAtThatTime(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$first = $this->questionMapper->createQuestion($diary->getId(), 'Mood', 'Mood', QuestionTypes::TEXT, null, null, null, true, '');
		sleep(1);
		$other = $this->questionMapper->createQuestion($diary->getId(), 'Energy', 'Energy', QuestionTypes::TEXT, null, null, null, false, '');

		$before = $this->questionMapper->getQuestionsForDiaryAtTimestamp($diary->getId(), $first->getCreatedAt(), false);
		$after = $this->questionMapper->getQuestionsForDiaryAtTimestamp($diary->getId(), $other->getCreatedAt(), false);

		$this->assertSame([$first->getId()], array_map(static fn ($question) => $question->getId(), $before));
		$this->assertSame([$first->getId(), $other->getId()], array_map(static fn ($question) => $question->getId(), $after));
	}

	public function testCreateQuestionPersistsCreatedAt(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');

		$question = $this->questionMapper->createQuestion($diary->getId(), 'Mood', 'Mood', QuestionTypes::TEXT, null, null, null, true, '');
		$reloaded = $this->questionMapper->getQuestion($question->getId());

		$this->assertGreaterThan(0, $reloaded->getCreatedAt());
		$this->assertSame($reloaded->getId(), $reloaded->getChainId());
		$this->assertSame($reloaded->getId(), $reloaded->getDiaryQuestionOrder());
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
		$this->assertSame($question->getChainId(), $updated->getChainId());
		$this->assertSame($question->getDiaryQuestionOrder(), $updated->getDiaryQuestionOrder());
		$this->assertNull($updated->getPreviousVersionId());
		$this->assertNull($updated->getNextVersionId());

		$reloaded = $this->questionMapper->getQuestion($question->getId());
		$this->assertSame(QuestionTypes::NUMBER, $reloaded->getType());
		$this->assertSame($question->getChainId(), $reloaded->getChainId());
		$this->assertSame($question->getDiaryQuestionOrder(), $reloaded->getDiaryQuestionOrder());
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
		$this->assertSame($question->getChainId(), $newVersion->getChainId());
		$this->assertSame($question->getDiaryQuestionOrder(), $newVersion->getDiaryQuestionOrder());
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

	public function testCreateQuestionUsesUniqueDefaultOrderPerDiary(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');

		$first = $this->questionMapper->createQuestion($diary->getId(), 'Q1', 'Q1', QuestionTypes::TEXT, null, null, null, true, '');
		$second = $this->questionMapper->createQuestion($diary->getId(), 'Q2', 'Q2', QuestionTypes::TEXT, null, null, null, true, '');

		$this->assertNotSame($first->getDiaryQuestionOrder(), $second->getDiaryQuestionOrder());
		$this->assertSame($first->getId(), $first->getDiaryQuestionOrder());
		$this->assertSame($second->getId(), $second->getDiaryQuestionOrder());
	}

	public function testReorderQuestionCreatesNewVersionAndReordersCurrentQuestions(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$first = $this->questionMapper->createQuestion($diary->getId(), 'Q1', 'Q1', QuestionTypes::TEXT, null, null, null, true, '');
		$second = $this->questionMapper->createQuestion($diary->getId(), 'Q2', 'Q2', QuestionTypes::TEXT, null, null, null, true, '');
		$third = $this->questionMapper->createQuestion($diary->getId(), 'Q3', 'Q3', QuestionTypes::TEXT, null, null, null, true, '');

		$moved = $this->questionMapper->reorderQuestion($third, 2);
		$current = $this->questionMapper->getCurrentQuestionsForDiary($diary->getId());
		$historicalThird = $this->questionMapper->getQuestion($third->getId());
		$chain = $this->questionMapper->getQuestionChain($moved->getId());

		$this->assertNotSame($third->getId(), $moved->getId());
		$this->assertSame($third->getChainId(), $moved->getChainId());
		$this->assertSame($third->getId(), $moved->getPreviousVersionId());
		$this->assertSame($moved->getId(), $historicalThird->getNextVersionId());
		$this->assertSame(
			[$first->getId(), $moved->getId(), $second->getId()],
			array_map(static fn ($question) => $question->getId(), $current)
		);
		$this->assertSame(
			[1, 2, 3],
			array_map(static fn ($question) => $question->getDiaryQuestionOrder(), $current)
		);
		$this->assertSame([$third->getId(), $moved->getId()], array_map(static fn ($question) => $question->getId(), $chain));
	}

	public function testReorderQuestionRejectsHistoricalVersion(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$entry = $this->entryMapper->createEntry($diary->getId(), 1713254400, 'day');
		$question = $this->questionMapper->createQuestion($diary->getId(), 'Q1', 'Q1', QuestionTypes::TEXT, null, null, null, true, '');
		$this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $question->getId(), 'v1', null);
		$current = $this->questionMapper->updateQuestion($question, 'Q2', 'Q2', QuestionTypes::TEXT, null, null, null, true, '');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Only the current question version can be reordered.');

		$this->questionMapper->reorderQuestion($this->questionMapper->getQuestion($question->getId()), $current->getDiaryQuestionOrder());
	}

	public function testUpdateQuestionRejectsDuplicateCurrentOrder(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$first = $this->questionMapper->createQuestion($diary->getId(), 'Q1', 'Q1', QuestionTypes::TEXT, null, null, null, true, '');
		$second = $this->questionMapper->createQuestion($diary->getId(), 'Q2', 'Q2', QuestionTypes::TEXT, null, null, null, true, '');

		$second->setDiaryQuestionOrder($first->getDiaryQuestionOrder());

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Current question order values must be unique per diary.');

		$this->questionMapper->updateQuestion($second, 'Q2 updated', 'Q2 updated', QuestionTypes::TEXT, null, null, null, true, '');
	}

	public function testUpdateQuestionWithoutAnswersNoOpDoesNotWriteNewVersion(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$question = $this->questionMapper->createQuestion($diary->getId(), 'Mood', 'Mood', QuestionTypes::TEXT, null, null, null, true, '');

		$result = $this->questionMapper->updateQuestion($question, 'Mood', 'Mood', QuestionTypes::TEXT, null, null, null, true, '');
		$current = $this->questionMapper->getCurrentQuestionsForDiary($diary->getId());
		$chain = $this->questionMapper->getQuestionChain($question->getId());

		$this->assertSame($question->getId(), $result->getId());
		$this->assertCount(1, $current);
		$this->assertSame($question->getId(), $current[0]->getId());
		$this->assertSame([$question->getId()], array_map(static fn ($item) => $item->getId(), $chain));
		$this->assertNull($this->questionMapper->getQuestion($question->getId())->getNextVersionId());
	}

	public function testUpdateQuestionWithAnswersNoOpDoesNotWriteNewVersion(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$entry = $this->entryMapper->createEntry($diary->getId(), 1713254400, 'day');
		$question = $this->questionMapper->createQuestion($diary->getId(), 'Mood', 'Mood', QuestionTypes::TEXT, null, null, null, true, '');
		$this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $question->getId(), 'good', null);

		$result = $this->questionMapper->updateQuestion($question, 'Mood', 'Mood', QuestionTypes::TEXT, null, null, null, true, '');
		$current = $this->questionMapper->getCurrentQuestionsForDiary($diary->getId());
		$chain = $this->questionMapper->getQuestionChain($question->getId());

		$this->assertSame($question->getId(), $result->getId());
		$this->assertCount(1, $current);
		$this->assertSame($question->getId(), $current[0]->getId());
		$this->assertSame([$question->getId()], array_map(static fn ($item) => $item->getId(), $chain));
		$this->assertNull($this->questionMapper->getQuestion($question->getId())->getNextVersionId());
	}
}
