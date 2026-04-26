<?php

declare(strict_types=1);

namespace OCA\Tests\StructuredDiary\Integration\Db;

use OCA\StructuredDiary\Db\AnswerMapper;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\EntryMapper;
use OCA\StructuredDiary\Db\QuestionMapper;
use OCA\StructuredDiary\Db\QuestionTypes;
use OCA\Tests\StructuredDiary\Integration\TestUtil\IntegrationTestParentClass;

/**
 * @runTestsInSeparateProcesses
 */
final class AnswerMapperIntegrationTest extends IntegrationTestParentClass {
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

	public function testCreateUpdateAndFetchAnswerChainPersistsChronologicalVersions(): void {
		[$diaryId, $entryId, $questionId] = $this->createDiaryEntryAndQuestion();

		$first = $this->answerMapper->createAnswer($diaryId, $entryId, $questionId, 'first', null);
		$second = $this->answerMapper->updateAnswer($first, 'second', null);

		$current = $this->answerMapper->getCurrentAnswersForEntry($entryId);
		$chain = $this->answerMapper->getAnswerChainForEntryQuestion($entryId, $questionId);

		$this->assertCount(1, $current);
		$this->assertSame($second->getId(), $current[0]->getId());
		$this->assertSame([$first->getId(), $second->getId()], array_map(static fn ($answer) => $answer->getId(), $chain));
		$this->assertSame([null, $first->getId()], array_map(static fn ($answer) => $answer->getPreviousVersionId(), $chain));
		$this->assertGreaterThanOrEqual($first->getCreatedAt(), $second->getCreatedAt());
	}

	public function testCreateAnswerPersistsCreatedAt(): void {
		[$diaryId, $entryId, $questionId] = $this->createDiaryEntryAndQuestion();

		$answer = $this->answerMapper->createAnswer($diaryId, $entryId, $questionId, 'first', null);
		$reloaded = $this->answerMapper->getAnswer($answer->getId());

		$this->assertGreaterThan(0, $reloaded->getCreatedAt());
	}

	public function testGetCurrentAnswersForEntryReturnsOnlyCurrentAnswersSortedByQuestionId(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$entry = $this->entryMapper->createEntry($diary->getId(), 1713254400, 'day');
		$questionB = $this->questionMapper->createQuestion($diary->getId(), 'B', 'B', QuestionTypes::TEXT, null, null, null, true, '');
		$questionA = $this->questionMapper->createQuestion($diary->getId(), 'A', 'A', QuestionTypes::TEXT, null, null, null, true, '');

		$firstForB = $this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $questionB->getId(), 'b1', null);
		$currentForB = $this->answerMapper->updateAnswer($firstForB, 'b2', null);
		$currentForA = $this->answerMapper->createAnswer($diary->getId(), $entry->getId(), $questionA->getId(), 'a1', null);

		$current = $this->answerMapper->getCurrentAnswersForEntry($entry->getId());

		$this->assertCount(2, $current);
		$expectedQuestionIds = [$questionA->getId(), $questionB->getId()];
		sort($expectedQuestionIds);
		$this->assertSame(
			$expectedQuestionIds,
			array_map(static fn ($answer) => $answer->getQuestionId(), $current)
		);
		$this->assertSame(
			$questionA->getId() < $questionB->getId() ? [$currentForA->getId(), $currentForB->getId()] : [$currentForB->getId(), $currentForA->getId()],
			array_map(static fn ($answer) => $answer->getId(), $current)
		);
	}

	public function testCreateAnswerRejectsDuplicateCurrentAnswer(): void {
		[$diaryId, $entryId, $questionId] = $this->createDiaryEntryAndQuestion();
		$this->answerMapper->createAnswer($diaryId, $entryId, $questionId, 'first', null);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('An answer for this question already exists in the entry.');

		$this->answerMapper->createAnswer($diaryId, $entryId, $questionId, 'second', null);
	}

	public function testDeleteAnswerReconnectsChainForMiddleAndTailVersions(): void {
		[$diaryId, $entryId, $questionId] = $this->createDiaryEntryAndQuestion();

		$first = $this->answerMapper->createAnswer($diaryId, $entryId, $questionId, 'first', null);
		$second = $this->answerMapper->updateAnswer($first, 'second', null);
		$third = $this->answerMapper->updateAnswer($second, 'third', null);

		$this->answerMapper->deleteAnswer($second);

		$chainAfterMiddleDelete = $this->answerMapper->getAnswerChainForEntryQuestion($entryId, $questionId);
		$this->assertSame([$first->getId(), $third->getId()], array_map(static fn ($answer) => $answer->getId(), $chainAfterMiddleDelete));
		$this->assertSame($third->getId(), $this->answerMapper->getAnswer($first->getId())->getNextVersionId());
		$this->assertSame($first->getId(), $this->answerMapper->getAnswer($third->getId())->getPreviousVersionId());

		$this->answerMapper->deleteAnswer($this->answerMapper->getAnswer($third->getId()));

		$current = $this->answerMapper->getCurrentAnswersForEntry($entryId);
		$this->assertCount(1, $current);
		$this->assertSame($first->getId(), $current[0]->getId());
		$this->assertNull($this->answerMapper->getAnswer($first->getId())->getNextVersionId());
	}

	public function testGetAnswerChainReturnsEmptyArrayWhenQuestionHasNoAnswer(): void {
		[, $entryId, $questionId] = $this->createDiaryEntryAndQuestion();

		$this->assertSame([], $this->answerMapper->getAnswerChainForEntryQuestion($entryId, $questionId));
	}

	public function testDeleteFirstAnswerPromotesNextVersionToChainStart(): void {
		[$diaryId, $entryId, $questionId] = $this->createDiaryEntryAndQuestion();

		$first = $this->answerMapper->createAnswer($diaryId, $entryId, $questionId, 'first', null);
		$second = $this->answerMapper->updateAnswer($first, 'second', null);

		$this->answerMapper->deleteAnswer($this->answerMapper->getAnswer($first->getId()));

		$chain = $this->answerMapper->getAnswerChainForEntryQuestion($entryId, $questionId);
		$this->assertCount(1, $chain);
		$this->assertSame($second->getId(), $chain[0]->getId());
		$this->assertNull($this->answerMapper->getAnswer($second->getId())->getPreviousVersionId());
	}

	public function testDeletingStandaloneAnswerRemovesItCompletely(): void {
		[$diaryId, $entryId, $questionId] = $this->createDiaryEntryAndQuestion();

		$answer = $this->answerMapper->createAnswer($diaryId, $entryId, $questionId, 'first', null);
		$this->answerMapper->deleteAnswer($answer);

		$this->assertSame([], $this->answerMapper->getCurrentAnswersForEntry($entryId));
		$this->assertSame([], $this->answerMapper->getAnswerChainForEntryQuestion($entryId, $questionId));
		$this->expectException(\OCP\AppFramework\Db\DoesNotExistException::class);
		$this->answerMapper->getAnswer($answer->getId());
	}

	public function testCreateAnswerWorksAgainAfterDeletingStandaloneAnswer(): void {
		[$diaryId, $entryId, $questionId] = $this->createDiaryEntryAndQuestion();

		$first = $this->answerMapper->createAnswer($diaryId, $entryId, $questionId, 'first', null);
		$this->answerMapper->deleteAnswer($first);
		$second = $this->answerMapper->createAnswer($diaryId, $entryId, $questionId, 'second', null);

		$current = $this->answerMapper->getCurrentAnswersForEntry($entryId);
		$chain = $this->answerMapper->getAnswerChainForEntryQuestion($entryId, $questionId);

		$this->assertCount(1, $current);
		$this->assertSame($second->getId(), $current[0]->getId());
		$this->assertSame([$second->getId()], array_map(static fn ($answer) => $answer->getId(), $chain));
	}

	public function testGetCurrentAnswersForEntryReturnsEmptyArrayWhenEntryHasNoAnswers(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$entry = $this->entryMapper->createEntry($diary->getId(), 1713254400, 'day');

		$this->assertSame([], $this->answerMapper->getCurrentAnswersForEntry($entry->getId()));
	}

	public function testDeletingCurrentTailVersionLeavesPreviousAsOnlyCurrentAnswer(): void {
		[$diaryId, $entryId, $questionId] = $this->createDiaryEntryAndQuestion();

		$first = $this->answerMapper->createAnswer($diaryId, $entryId, $questionId, 'first', null);
		$second = $this->answerMapper->updateAnswer($first, 'second', null);

		$this->answerMapper->deleteAnswer($second);

		$current = $this->answerMapper->getCurrentAnswersForEntry($entryId);
		$chain = $this->answerMapper->getAnswerChainForEntryQuestion($entryId, $questionId);

		$this->assertCount(1, $current);
		$this->assertSame($first->getId(), $current[0]->getId());
		$this->assertSame([$first->getId()], array_map(static fn ($answer) => $answer->getId(), $chain));
		$this->assertNull($this->answerMapper->getAnswer($first->getId())->getNextVersionId());
	}

	public function testUpdateAnswerRejectsHistoricalVersion(): void {
		[$diaryId, $entryId, $questionId] = $this->createDiaryEntryAndQuestion();

		$first = $this->answerMapper->createAnswer($diaryId, $entryId, $questionId, 'first', null);
		$this->answerMapper->updateAnswer($first, 'second', null);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Only the current answer version can be changed.');

		$this->answerMapper->updateAnswer($this->answerMapper->getAnswer($first->getId()), 'third', null);
	}

	public function testUpdateAnswerNoOpDoesNotCreateNewVersion(): void {
		[$diaryId, $entryId, $questionId] = $this->createDiaryEntryAndQuestion();

		$first = $this->answerMapper->createAnswer($diaryId, $entryId, $questionId, 'first', null);
		$result = $this->answerMapper->updateAnswer($first, 'first', null);
		$current = $this->answerMapper->getCurrentAnswersForEntry($entryId);
		$chain = $this->answerMapper->getAnswerChainForEntryQuestion($entryId, $questionId);

		$this->assertSame($first->getId(), $result->getId());
		$this->assertCount(1, $current);
		$this->assertSame($first->getId(), $current[0]->getId());
		$this->assertSame([$first->getId()], array_map(static fn ($answer) => $answer->getId(), $chain));
		$this->assertNull($this->answerMapper->getAnswer($first->getId())->getNextVersionId());
	}

	private function createDiaryEntryAndQuestion(): array {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$entry = $this->entryMapper->createEntry($diary->getId(), 1713254400, 'day');
		$question = $this->questionMapper->createQuestion($diary->getId(), 'Mood', 'Mood', QuestionTypes::TEXT, null, null, null, true, '');

		return [$diary->getId(), $entry->getId(), $question->getId()];
	}
}
