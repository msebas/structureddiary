<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\Answer;
use OCA\StructuredDiary\Db\AnswerMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AnswerMapperTest extends TestCase {
	private IDBConnection&MockObject $db;

	protected function setUp(): void {
		parent::setUp();
		$this->db = $this->createMock(IDBConnection::class);
	}

	public function testCreateAnswerInitializesEntityIncludingCreatedAt(): void {
		$mapper = $this->getMockBuilder(AnswerMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert', 'getCurrentTimestamp', 'getCurrentAnswerForEntryQuestion'])
			->getMock();

		$mapper->expects($this->once())->method('getCurrentAnswerForEntryQuestion')->with(5, 11)->willReturn(null);
		$mapper->expects($this->once())->method('getCurrentTimestamp')->willReturn(1713254400);
		$mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Answer $answer): Answer {
				$this->assertSame(42, $answer->getDiaryId());
				$this->assertSame(5, $answer->getEntryId());
				$this->assertSame(11, $answer->getQuestionId());
				$this->assertSame(1713254400, $answer->getCreatedAt());
				$this->assertSame('text', $answer->getTextContent());
				$this->assertSame(2.5, $answer->getNumericContent());
				$this->assertNull($answer->getPreviousVersionId());
				$this->assertNull($answer->getNextVersionId());
				return $answer;
			});

		$mapper->createAnswer(42, 5, 11, 'text', 2.5);
	}

	public function testCreateAnswerRejectsDuplicateCurrentAnswerBeforeInsert(): void {
		$current = $this->createAnswerEntity(10, null, null, 'v1', 1.0);

		$mapper = $this->getMockBuilder(AnswerMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert', 'getCurrentTimestamp', 'getCurrentAnswerForEntryQuestion'])
			->getMock();

		$mapper->expects($this->once())->method('getCurrentAnswerForEntryQuestion')->with(5, 11)->willReturn($current);
		$mapper->expects($this->never())->method('getCurrentTimestamp');
		$mapper->expects($this->never())->method('insert');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('An answer for this question already exists in the entry.');

		$mapper->createAnswer(42, 5, 11, 'text', 2.5);
	}

	public function testUpdateAnswerCreatesNewVersionAndLinksPreviousAnswer(): void {
		$original = $this->createAnswerEntity(10, null, null, 'v1', 1.0);
		$originalCreatedAt = $original->getCreatedAt();

		$mapper = $this->getMockBuilder(AnswerMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert', 'update', 'getCurrentTimestamp'])
			->getMock();

		$mapper->expects($this->once())->method('getCurrentTimestamp')->willReturn(1713254400);
		$mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Answer $newAnswer): Answer {
				$this->assertSame(42, $newAnswer->getDiaryId());
				$this->assertSame(5, $newAnswer->getEntryId());
				$this->assertSame(11, $newAnswer->getQuestionId());
				$this->assertSame('v2', $newAnswer->getTextContent());
				$this->assertSame(2.0, $newAnswer->getNumericContent());
				$this->assertSame(10, $newAnswer->getPreviousVersionId());
				$this->assertNull($newAnswer->getNextVersionId());
				$this->assertSame(1713254400, $newAnswer->getCreatedAt());
				$newAnswer->setId(11);
				$newAnswer->resetUpdatedFields();

				return $newAnswer;
			});

		$mapper->expects($this->once())
			->method('update')
			->with($original)
			->willReturnCallback(function (Answer $updatedOriginal): Answer {
				$this->assertSame(11, $updatedOriginal->getNextVersionId());

				return $updatedOriginal;
			});

		$newVersion = $mapper->updateAnswer($original, 'v2', 2.0);

		$this->assertSame(10, $original->getId());
		$this->assertSame($originalCreatedAt, $original->getCreatedAt());
		$this->assertSame(11, $original->getNextVersionId());
		$this->assertSame(11, $newVersion->getId());
		$this->assertSame(10, $newVersion->getPreviousVersionId());
		$this->assertNull($newVersion->getNextVersionId());
	}

	public function testUpdateAnswerCanBuildAndExposeFullVersionChain(): void {
		$first = $this->createAnswerEntity(10, null, null, 'v1', 1.0);
		$insertedAnswers = [];

		$mapper = $this->getMockBuilder(AnswerMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert', 'update', 'getCurrentTimestamp'])
			->getMock();

		$mapper->expects($this->exactly(2))
			->method('getCurrentTimestamp')
			->willReturnOnConsecutiveCalls(1713254400, 1713254500);
		$mapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function (Answer $newAnswer) use (&$insertedAnswers): Answer {
				$newId = count($insertedAnswers) === 0 ? 11 : 12;
				$newAnswer->setId($newId);
				$newAnswer->resetUpdatedFields();
				$insertedAnswers[] = $newAnswer;

				return $newAnswer;
			});

		$mapper->expects($this->exactly(2))
			->method('update')
			->willReturnCallback(static fn (Answer $answer): Answer => $answer);

		$second = $mapper->updateAnswer($first, 'v2', 2.0);
		$third = $mapper->updateAnswer($second, 'v3', 3.0);

		$this->assertCount(2, $insertedAnswers);
		$this->assertSame($second, $insertedAnswers[0]);
		$this->assertSame($third, $insertedAnswers[1]);

		$this->assertSame(10, $first->getId());
		$this->assertNull($first->getPreviousVersionId());
		$this->assertSame(11, $first->getNextVersionId());

		$this->assertSame(11, $second->getId());
		$this->assertSame(10, $second->getPreviousVersionId());
		$this->assertSame(12, $second->getNextVersionId());
		$this->assertSame(1713254400, $second->getCreatedAt());
		$this->assertSame('v2', $second->getTextContent());

		$this->assertSame(12, $third->getId());
		$this->assertSame(11, $third->getPreviousVersionId());
		$this->assertNull($third->getNextVersionId());
		$this->assertSame(1713254500, $third->getCreatedAt());
		$this->assertSame('v3', $third->getTextContent());
	}

	public function testGetAnswerChainForEntryQuestionReturnsChronologicalChain(): void {
		$first = $this->createAnswerEntity(10, null, 11, 'v1', 1.0);
		$second = $this->createAnswerEntity(11, 10, 12, 'v2', 2.0);
		$third = $this->createAnswerEntity(12, 11, null, 'v3', 3.0);

		$mapper = $this->getMockBuilder(AnswerMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getCurrentAnswerForEntryQuestion', 'getAnswer'])
			->getMock();

		$mapper->expects($this->once())
			->method('getCurrentAnswerForEntryQuestion')
			->with(5, 11)
			->willReturn($third);

		$mapper->expects($this->exactly(2))
			->method('getAnswer')
			->willReturnMap([
				[11, $second],
				[10, $first],
			]);

		$chain = $mapper->getAnswerChainForEntryQuestion(5, 11);

		$this->assertSame([$first, $second, $third], $chain);
	}

	public function testGetAnswerChainForEntryQuestionReturnsEmptyArrayWhenNoAnswerExists(): void {
		$mapper = $this->getMockBuilder(AnswerMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getCurrentAnswerForEntryQuestion', 'getAnswer'])
			->getMock();

		$mapper->expects($this->once())
			->method('getCurrentAnswerForEntryQuestion')
			->with(5, 11)
			->willReturn(null);

		$mapper->expects($this->never())->method('getAnswer');

		$this->assertSame([], $mapper->getAnswerChainForEntryQuestion(5, 11));
	}

	public function testUpdateAnswerRejectsNonCurrentVersion(): void {
		$answer = $this->createAnswerEntity(10, null, 11, 'v1', 1.0);

		$mapper = $this->getMockBuilder(AnswerMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert', 'update', 'getCurrentTimestamp'])
			->getMock();

		$mapper->expects($this->never())->method('getCurrentTimestamp');
		$mapper->expects($this->never())->method('insert');
		$mapper->expects($this->never())->method('update');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Only the current answer version can be changed.');

		$mapper->updateAnswer($answer, 'v2', 2.0);
	}

	public function testDeleteAnswerReconnectsNeighboursWhenDeletingMiddleVersion(): void {
		$first = $this->createAnswerEntity(10, null, 11, 'v1', 1.0);
		$middle = $this->createAnswerEntity(11, 10, 12, 'v2', 2.0);
		$third = $this->createAnswerEntity(12, 11, null, 'v3', 3.0);

		$mapper = $this->getMockBuilder(AnswerMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getAnswer', 'update', 'delete'])
			->getMock();

		$mapper->expects($this->exactly(2))
			->method('getAnswer')
			->willReturnMap([
				[10, $first],
				[12, $third],
			]);

		$mapper->expects($this->exactly(2))
			->method('update')
			->with($this->callback(static fn (Answer $answer): bool => in_array($answer->getId(), [10, 12], true)))
			->willReturnCallback(static fn (Answer $answer): Answer => $answer);

		$mapper->expects($this->once())
			->method('delete')
			->with($middle)
			->willReturn($middle);

		$result = $mapper->deleteAnswer($middle);

		$this->assertSame($middle, $result);
		$this->assertSame(12, $first->getNextVersionId());
		$this->assertSame(10, $third->getPreviousVersionId());
	}

	public function testDeleteAnswerPromotesNextVersionWhenDeletingChainStart(): void {
		$first = $this->createAnswerEntity(10, null, 11, 'v1', 1.0);
		$second = $this->createAnswerEntity(11, 10, 12, 'v2', 2.0);
		$third = $this->createAnswerEntity(12, 11, null, 'v3', 3.0);

		$mapper = $this->getMockBuilder(AnswerMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getAnswer', 'update', 'delete'])
			->getMock();

		$mapper->expects($this->once())
			->method('getAnswer')
			->with(11)
			->willReturn($second);

		$mapper->expects($this->once())
			->method('update')
			->with($second)
			->willReturnCallback(static fn (Answer $answer): Answer => $answer);

		$mapper->expects($this->once())
			->method('delete')
			->with($first)
			->willReturn($first);

		$result = $mapper->deleteAnswer($first);

		$this->assertSame($first, $result);
		$this->assertNull($second->getPreviousVersionId());
		$this->assertSame(12, $second->getNextVersionId());
		$this->assertSame(11, $third->getPreviousVersionId());
	}

	public function testDeleteAnswerAllowsStandaloneAnswer(): void {
		$standalone = $this->createAnswerEntity(10, null, null, 'v1', 1.0);

		$mapper = $this->getMockBuilder(AnswerMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['delete'])
			->getMock();

		$mapper->expects($this->once())
			->method('delete')
			->with($standalone)
			->willReturn($standalone);

		$result = $mapper->deleteAnswer($standalone);

		$this->assertSame($standalone, $result);
	}

	public function testDeleteAnswerAllowsDeletingCurrentTailVersion(): void {
		$first = $this->createAnswerEntity(10, null, 11, 'v1', 1.0);
		$tail = $this->createAnswerEntity(11, 10, null, 'v2', 2.0);

		$mapper = $this->getMockBuilder(AnswerMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getAnswer', 'update', 'delete'])
			->getMock();

		$mapper->expects($this->once())
			->method('getAnswer')
			->with(10)
			->willReturn($first);

		$mapper->expects($this->once())
			->method('update')
			->with($first)
			->willReturnCallback(static fn (Answer $answer): Answer => $answer);

		$mapper->expects($this->once())
			->method('delete')
			->with($tail)
			->willReturn($tail);

		$result = $mapper->deleteAnswer($tail);

		$this->assertSame($tail, $result);
		$this->assertNull($first->getNextVersionId());
	}

	private function createAnswerEntity(
		int $id,
		?int $previousVersionId,
		?int $nextVersionId,
		?string $textContent,
		?float $numericContent,
	): Answer {
		$answer = new Answer();
		$answer->setId($id);
		$answer->setDiaryId(42);
		$answer->setEntryId(5);
		$answer->setQuestionId(11);
		$answer->setCreatedAt(1713000000 + $id);
		$answer->setTextContent($textContent);
		$answer->setNumericContent($numericContent);
		$answer->setPreviousVersionId($previousVersionId);
		$answer->setNextVersionId($nextVersionId);
		$answer->resetUpdatedFields();

		return $answer;
	}
}
