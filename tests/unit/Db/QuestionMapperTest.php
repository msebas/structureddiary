<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\Answer;
use OCA\StructuredDiary\Db\Question;
use OCA\StructuredDiary\Db\QuestionMapper;
use OCA\StructuredDiary\Db\QuestionTypes;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class QuestionMapperTest extends TestCase {
	private IDBConnection&MockObject $db;

	protected function setUp(): void {
		parent::setUp();
		$this->db = $this->createMock(IDBConnection::class);
	}

	public function testCreateQuestionInitializesEntityIncludingCreatedAt(): void {
		$mapper = $this->getMockBuilder(QuestionMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert', 'getCurrentTimestamp'])
			->getMock();

		$mapper->expects($this->once())->method('getCurrentTimestamp')->willReturn(1713254400);
		$mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Question $question): Question {
				$this->assertSame(42, $question->getDiaryId());
				$this->assertSame(1713254400, $question->getCreatedAt());
				$this->assertSame('Mood', $question->getLabel());
				$this->assertSame('Mood', $question->getDisplayText());
				$this->assertSame(QuestionTypes::SELECT, $question->getType());
				$this->assertSame(1.0, $question->getMinimum());
				$this->assertSame(5.0, $question->getMaximum());
				$this->assertSame(['yes', 'no'], $question->getChoices());
				$this->assertTrue($question->getActive());
				$this->assertSame('template', $question->getTemplateText());
				$this->assertNull($question->getPreviousVersionId());
				$this->assertNull($question->getNextVersionId());
				return $question;
			});

		$mapper->createQuestion(42, 'Mood', 'Mood', QuestionTypes::SELECT, 1.0, 5.0, ['yes', 'no'], true, 'template');
	}

	public function testCreateQuestionRejectsInvalidDefinitionBeforeInsert(): void {
		$mapper = $this->getMockBuilder(QuestionMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert', 'getCurrentTimestamp'])
			->getMock();

		$mapper->expects($this->never())->method('getCurrentTimestamp');
		$mapper->expects($this->never())->method('insert');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Selection questions require at least one choice.');

		$mapper->createQuestion(42, 'Mood', 'Mood', QuestionTypes::SELECT, null, null, null, true, 'template');
	}

	public function testUpdateQuestionWithoutAnswersUpdatesInPlaceAndAllowsTypeChange(): void {
		$question = $this->createQuestionEntity(10, null, null, QuestionTypes::TEXT);
		$originalCreatedAt = $question->getCreatedAt();

		$mapper = $this->getMockBuilder(QuestionMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['hasAnswersInChain', 'update', 'getCurrentTimestamp'])
			->getMock();

		$mapper->expects($this->never())->method('getCurrentTimestamp');
		$mapper->expects($this->once())->method('hasAnswersInChain')->with($question)->willReturn(false);
		$mapper->expects($this->once())
			->method('update')
			->with($question)
			->willReturnCallback(function (Question $updated) use ($originalCreatedAt): Question {
				$this->assertSame(QuestionTypes::NUMBER, $updated->getType());
				$this->assertSame(1.0, $updated->getMinimum());
				$this->assertSame(9.0, $updated->getMaximum());
				$this->assertSame($originalCreatedAt, $updated->getCreatedAt());
				return $updated;
			});

		$result = $mapper->updateQuestion($question, 'Score', 'Score', QuestionTypes::NUMBER, 1.0, 9.0, null, true, 'template');

		$this->assertSame($question, $result);
		$this->assertSame($originalCreatedAt, $result->getCreatedAt());
	}

	public function testUpdateQuestionWithAnswersCreatesNewVersionAndAllowsTypeChange(): void {
		$current = $this->createQuestionEntity(10, 9, null, QuestionTypes::TEXT);
		$originalCreatedAt = $current->getCreatedAt();

		$mapper = $this->getMockBuilder(QuestionMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['hasAnswersInChain', 'insert', 'update', 'getCurrentTimestamp'])
			->getMock();

		$mapper->method('hasAnswersInChain')->with($current)->willReturn(true);
		$mapper->expects($this->once())->method('getCurrentTimestamp')->willReturn(1713254400);
		$mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Question $newQuestion): Question {
				$this->assertSame(QuestionTypes::SELECT, $newQuestion->getType());
				$this->assertSame(['yes', 'no'], $newQuestion->getChoices());
				$this->assertSame(10, $newQuestion->getPreviousVersionId());
				$this->assertSame(1713254400, $newQuestion->getCreatedAt());
				$newQuestion->setId(11);
				$newQuestion->resetUpdatedFields();
				return $newQuestion;
			});
		$mapper->expects($this->once())
			->method('update')
			->with($current)
			->willReturnCallback(static fn (Question $question): Question => $question);

		$newVersion = $mapper->updateQuestion(
			$current,
			'Mood',
			'Mood',
			QuestionTypes::SELECT,
			null,
			null,
			['yes', 'no'],
			true,
			'pick one',
		);

		$this->assertSame(11, $current->getNextVersionId());
		$this->assertSame($originalCreatedAt, $current->getCreatedAt());
		$this->assertSame(11, $newVersion->getId());
		$this->assertSame(10, $newVersion->getPreviousVersionId());
	}

	public function testDeleteQuestionReconnectsChainAndReassignsAnswersToNextVersion(): void {
		$previous = $this->createQuestionEntity(9, null, 10, QuestionTypes::TEXT);
		$question = $this->createQuestionEntity(10, 9, 11, QuestionTypes::TEXT);
		$next = $this->createQuestionEntity(11, 10, null, QuestionTypes::TEXT);
		$answer = $this->createAnswerEntity(21, 10, 'hello', null);

		$mapper = $this->getMockBuilder(QuestionMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getQuestion', 'getAnswersForQuestion', 'updateAnswerEntity', 'update', 'delete'])
			->getMock();

		$mapper->expects($this->exactly(3))
			->method('getQuestion')
			->willReturnMap([
				[11, $next],
				[9, $previous],
				[11, $next],
			]);
		$mapper->expects($this->once())
			->method('getAnswersForQuestion')
			->with(10)
			->willReturn([$answer]);
		$mapper->expects($this->once())
			->method('updateAnswerEntity')
			->with($answer)
			->willReturnCallback(static fn (Answer $updated): Answer => $updated);
		$mapper->expects($this->exactly(2))
			->method('update')
			->willReturnCallback(static fn (Question $updated): Question => $updated);
		$mapper->expects($this->once())
			->method('delete')
			->with($question)
			->willReturn($question);

		$result = $mapper->deleteQuestion($question);

		$this->assertSame($question, $result);
		$this->assertSame(11, $previous->getNextVersionId());
		$this->assertSame(9, $next->getPreviousVersionId());
		$this->assertSame(11, $answer->getQuestionId());
	}

	public function testDeleteQuestionRejectsIfAnswersWouldBecomeInvalid(): void {
		$question = $this->createQuestionEntity(10, null, 11, QuestionTypes::SELECT, ['yes', 'no']);
		$next = $this->createQuestionEntity(11, 10, null, QuestionTypes::SELECT, ['no']);
		$answer = $this->createAnswerEntity(21, 10, 'yes', null);

		$mapper = $this->getMockBuilder(QuestionMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getQuestion', 'getAnswersForQuestion', 'updateAnswerEntity', 'update', 'delete'])
			->getMock();

		$mapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($next);
		$mapper->expects($this->once())->method('getAnswersForQuestion')->with(10)->willReturn([$answer]);
		$mapper->expects($this->never())->method('updateAnswerEntity');
		$mapper->expects($this->never())->method('update');
		$mapper->expects($this->never())->method('delete');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Questions with answers cannot be deleted without a compatible replacement version.');

		$mapper->deleteQuestion($question);
	}

	public function testDeleteQuestionUsesPreviousVersionIfNextIsInvalidButPreviousFits(): void {
		$previous = $this->createQuestionEntity(9, null, 10, QuestionTypes::SELECT, ['yes', 'no']);
		$question = $this->createQuestionEntity(10, 9, 11, QuestionTypes::SELECT, ['yes', 'no']);
		$next = $this->createQuestionEntity(11, 10, null, QuestionTypes::SELECT, ['no']);
		$answer = $this->createAnswerEntity(21, 10, 'yes', null);

		$mapper = $this->getMockBuilder(QuestionMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getQuestion', 'getAnswersForQuestion', 'updateAnswerEntity', 'update', 'delete'])
			->getMock();

		$mapper->expects($this->exactly(4))
			->method('getQuestion')
			->willReturnMap([
				[11, $next],
				[9, $previous],
				[9, $previous],
				[11, $next],
			]);
		$mapper->expects($this->once())->method('getAnswersForQuestion')->with(10)->willReturn([$answer]);
		$mapper->expects($this->once())
			->method('updateAnswerEntity')
			->with($answer)
			->willReturnCallback(static fn (Answer $updated): Answer => $updated);
		$mapper->expects($this->exactly(2))
			->method('update')
			->willReturnCallback(static fn (Question $updated): Question => $updated);
		$mapper->expects($this->once())->method('delete')->with($question)->willReturn($question);

		$result = $mapper->deleteQuestion($question);

		$this->assertSame($question, $result);
		$this->assertSame(9, $answer->getQuestionId());
		$this->assertSame(11, $previous->getNextVersionId());
		$this->assertSame(9, $next->getPreviousVersionId());
	}

	public function testDeleteQuestionAllowsDeletingStandaloneQuestionWithoutAnswers(): void {
		$question = $this->createQuestionEntity(10, null, null, QuestionTypes::TEXT);

		$mapper = $this->getMockBuilder(QuestionMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getAnswersForQuestion', 'update', 'delete'])
			->getMock();

		$mapper->expects($this->once())->method('getAnswersForQuestion')->with(10)->willReturn([]);
		$mapper->expects($this->never())->method('update');
		$mapper->expects($this->once())->method('delete')->with($question)->willReturn($question);

		$this->assertSame($question, $mapper->deleteQuestion($question));
	}

	public function testDeleteQuestionReconnectsChainWithoutAnswers(): void {
		$previous = $this->createQuestionEntity(9, null, 10, QuestionTypes::TEXT);
		$question = $this->createQuestionEntity(10, 9, 11, QuestionTypes::TEXT);
		$next = $this->createQuestionEntity(11, 10, null, QuestionTypes::TEXT);

		$mapper = $this->getMockBuilder(QuestionMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getQuestion', 'getAnswersForQuestion', 'update', 'delete'])
			->getMock();

		$mapper->expects($this->exactly(3))
			->method('getQuestion')
			->willReturnMap([
				[11, $next],
				[9, $previous],
				[11, $next],
			]);
		$mapper->expects($this->once())->method('getAnswersForQuestion')->with(10)->willReturn([]);
		$mapper->expects($this->exactly(2))->method('update')->willReturnCallback(static fn (Question $updated): Question => $updated);
		$mapper->expects($this->once())->method('delete')->with($question)->willReturn($question);

		$mapper->deleteQuestion($question);

		$this->assertSame(11, $previous->getNextVersionId());
		$this->assertSame(9, $next->getPreviousVersionId());
	}

	public function testUpdateQuestionWithAnswersRejectsChangingHistoricalVersion(): void {
		$question = $this->createQuestionEntity(10, 9, 11, QuestionTypes::TEXT);

		$mapper = $this->getMockBuilder(QuestionMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['hasAnswersInChain', 'insert', 'update', 'getCurrentTimestamp'])
			->getMock();

		$mapper->method('hasAnswersInChain')->with($question)->willReturn(true);
		$mapper->expects($this->never())->method('getCurrentTimestamp');
		$mapper->expects($this->never())->method('insert');
		$mapper->expects($this->never())->method('update');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Only the current question version can be changed.');

		$mapper->updateQuestion($question, 'x', 'x', QuestionTypes::TEXT, null, null, null, true, '');
	}

	private function createQuestionEntity(
		int $id,
		?int $previousVersionId,
		?int $nextVersionId,
		string $type,
		?array $choices = null,
	): Question {
		$question = new Question();
		$question->setId($id);
		$question->setDiaryId(42);
		$question->setCreatedAt(1713000000 + $id);
		$question->setLabel('Question');
		$question->setDisplayText('Question');
		$question->setType($type);
		$question->setMinimum(null);
		$question->setMaximum(null);
		$question->setJsonChoices($choices === null ? null : json_encode($choices, JSON_THROW_ON_ERROR));
		$question->setActive(true);
		$question->setTemplateText('');
		$question->setPreviousVersionId($previousVersionId);
		$question->setNextVersionId($nextVersionId);
		$question->resetUpdatedFields();
		return $question;
	}

	private function createAnswerEntity(int $id, int $questionId, ?string $textContent, ?float $numericContent): Answer {
		$answer = new Answer();
		$answer->setId($id);
		$answer->setDiaryId(42);
		$answer->setEntryId(5);
		$answer->setQuestionId($questionId);
		$answer->setTextContent($textContent);
		$answer->setNumericContent($numericContent);
		$answer->resetUpdatedFields();
		return $answer;
	}
}
