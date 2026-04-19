<?php

declare(strict_types=1);

namespace Controller;

use OCA\StructuredDiary\AppInfo\Application;
use OCA\StructuredDiary\Controller\AnswerController;
use OCA\StructuredDiary\Db\Answer;
use OCA\StructuredDiary\Db\AnswerMapper;
use OCA\StructuredDiary\Db\Diary;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCA\StructuredDiary\Db\Entry;
use OCA\StructuredDiary\Db\EntryMapper;
use OCA\StructuredDiary\Db\Question;
use OCA\StructuredDiary\Db\QuestionMapper;
use OCA\StructuredDiary\Db\QuestionTypes;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class AnswerControllerTest extends TestCase {
	public function testHistoryReturnsFullAnswerChain(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);

		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);
		$question->setType(Question::TYPE_TEXT);
		$question->setActive(true);
		$question->setNextVersionId(null);

		$answerV1 = new Answer();
		$answerV1->setId(10);
		$answerV1->setDiaryId(42);
		$answerV1->setEntryId(5);
		$answerV1->setQuestionId(11);
		$answerV1->setTextContent('v1');
		$answerV1->setPreviousVersionId(null);
		$answerV1->setNextVersionId(11);

		$answerV2 = new Answer();
		$answerV2->setId(11);
		$answerV2->setDiaryId(42);
		$answerV2->setEntryId(5);
		$answerV2->setQuestionId(11);
		$answerV2->setTextContent('v2');
		$answerV2->setPreviousVersionId(10);
		$answerV2->setNextVersionId(null);

		$entryMapper->expects($this->once())
			->method('getEntry')
			->with(5)
			->willReturn($entry);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', 1)
			->willReturn($this->createStub(Diary::class));
		$questionMapper->expects($this->once())
			->method('getQuestion')
			->with(11)
			->willReturn($question);
		$answerMapper->expects($this->once())
			->method('getAnswerChainForEntryQuestion')
			->with(5, 11)
			->willReturn([$answerV1, $answerV2]);

		$controller = new AnswerController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$entryMapper,
			$questionMapper,
			$answerMapper,
			'alice',
		);

		$response = $controller->history(5, 11);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals([$answerV1, $answerV2], $response->getData());
	}

	public function testIndexReturnsErrorWhenReadPermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::READ)
			->willThrowException(new DoesNotExistException('Diary not accessible'));
		$answerMapper->expects($this->never())->method('getCurrentAnswersForEntry');

		$controller = new AnswerController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$entryMapper,
			$questionMapper,
			$answerMapper,
			'alice',
		);

		$response = $controller->index(5);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Diary not accessible'], $response->getData());
	}

	public function testCreateRejectsInvalidBooleanAnswer(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);

		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);
		$question->setType(QuestionTypes::BOOLEAN);
		$question->setActive(true);
		$question->setNextVersionId(null);

		$entryMapper->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->method('getDiaryForUser')->willReturn($this->createStub(Diary::class));
		$questionMapper->method('getQuestion')->with(11)->willReturn($question);
		$answerMapper->expects($this->never())->method('createAnswer');

		$controller = new AnswerController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$entryMapper,
			$questionMapper,
			$answerMapper,
			'alice',
		);

		$response = $controller->create(5, 11, null, 2.0);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Boolean answers must use numeric_content 0 or 1.'], $response->getData());
	}

	public function testCreateRejectsFractionForIntegerAnswer(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);

		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);
		$question->setType(QuestionTypes::INTEGER);
		$question->setActive(true);
		$question->setNextVersionId(null);

		$entryMapper->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->method('getDiaryForUser')->willReturn($this->createStub(Diary::class));
		$questionMapper->method('getQuestion')->with(11)->willReturn($question);
		$answerMapper->expects($this->never())->method('createAnswer');

		$controller = new AnswerController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$entryMapper,
			$questionMapper,
			$answerMapper,
			'alice',
		);

		$response = $controller->create(5, 11, null, 2.5);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Integer answers must use whole numbers.'], $response->getData());
	}

	public function testCreateReturnsErrorWhenWritePermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::WRITE)
			->willThrowException(new DoesNotExistException('Diary not writable'));
		$questionMapper->expects($this->never())->method('getQuestion');
		$answerMapper->expects($this->never())->method('createAnswer');

		$controller = new AnswerController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$entryMapper,
			$questionMapper,
			$answerMapper,
			'alice',
		);

		$response = $controller->create(5, 11, 'text');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Diary not writable'], $response->getData());
	}

	public function testUpdateReturnsErrorWhenWritePermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);
		$answer = new Answer();
		$answer->setId(5);
		$answer->setDiaryId(42);
		$answer->setQuestionId(9);

		$answerMapper->expects($this->once())->method('getAnswer')->with(5)->willReturn($answer);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::WRITE)
			->willThrowException(new DoesNotExistException('Diary not writable'));
		$questionMapper->expects($this->never())->method('getQuestion');
		$answerMapper->expects($this->never())->method('updateAnswer');

		$controller = new AnswerController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$entryMapper,
			$questionMapper,
			$answerMapper,
			'alice',
		);

		$response = $controller->update(5, 'text');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Diary not writable'], $response->getData());
	}

	public function testUpdateRejectsInvalidPayloadForQuestionType(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);
		$answer = new Answer();
		$answer->setId(5);
		$answer->setDiaryId(42);
		$answer->setQuestionId(11);
		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);
		$question->setType(QuestionTypes::BOOLEAN);

		$answerMapper->expects($this->once())->method('getAnswer')->with(5)->willReturn($answer);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::WRITE)
			->willReturn($this->createStub(Diary::class));
		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$answerMapper->expects($this->never())->method('updateAnswer');

		$controller = new AnswerController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$entryMapper,
			$questionMapper,
			$answerMapper,
			'alice',
		);

		$response = $controller->update(5, null, 2.0);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Boolean answers must use numeric_content 0 or 1.'], $response->getData());
	}

	public function testShowReturnsErrorWhenReadPermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);
		$answer = new Answer();
		$answer->setId(5);
		$answer->setDiaryId(42);

		$answerMapper->expects($this->once())->method('getAnswer')->with(5)->willReturn($answer);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::READ)
			->willThrowException(new DoesNotExistException('Diary not accessible'));

		$controller = new AnswerController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$entryMapper,
			$questionMapper,
			$answerMapper,
			'alice',
		);

		$response = $controller->show(5);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Diary not accessible'], $response->getData());
	}

	public function testCreateRejectsQuestionFromDifferentDiary(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);

		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(99);
		$question->setType(QuestionTypes::TEXT);
		$question->setActive(true);
		$question->setNextVersionId(null);

		$entryMapper->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::WRITE)->willReturn($this->createStub(Diary::class));
		$questionMapper->method('getQuestion')->with(11)->willReturn($question);
		$answerMapper->expects($this->never())->method('createAnswer');

		$controller = new AnswerController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$entryMapper,
			$questionMapper,
			$answerMapper,
			'alice',
		);

		$response = $controller->create(5, 11, 'text');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Question and entry must belong to the same diary.'], $response->getData());
	}

	public function testCreateRejectsInactiveQuestion(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);

		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);
		$question->setType(QuestionTypes::TEXT);
		$question->setActive(false);
		$question->setNextVersionId(null);

		$entryMapper->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::WRITE)->willReturn($this->createStub(Diary::class));
		$questionMapper->method('getQuestion')->with(11)->willReturn($question);
		$answerMapper->expects($this->never())->method('createAnswer');

		$controller = new AnswerController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$entryMapper,
			$questionMapper,
			$answerMapper,
			'alice',
		);

		$response = $controller->create(5, 11, 'text');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Inactive questions cannot be answered.'], $response->getData());
	}

	public function testCreateRejectsHistoricalQuestionVersion(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);

		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);
		$question->setType(QuestionTypes::TEXT);
		$question->setActive(true);
		$question->setNextVersionId(12);

		$entryMapper->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::WRITE)->willReturn($this->createStub(Diary::class));
		$questionMapper->method('getQuestion')->with(11)->willReturn($question);
		$answerMapper->expects($this->never())->method('createAnswer');

		$controller = new AnswerController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$entryMapper,
			$questionMapper,
			$answerMapper,
			'alice',
		);

		$response = $controller->create(5, 11, 'text');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Answers may only be created for the current question version.'], $response->getData());
	}

	public function testHistoryRejectsQuestionFromDifferentDiary(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);

		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(99);
		$question->setType(QuestionTypes::TEXT);
		$question->setActive(true);
		$question->setNextVersionId(null);

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::READ)
			->willReturn($this->createStub(Diary::class));
		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$answerMapper->expects($this->never())->method('getAnswerChainForEntryQuestion');

		$controller = new AnswerController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$entryMapper,
			$questionMapper,
			$answerMapper,
			'alice',
		);

		$response = $controller->history(5, 11);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Question and entry must belong to the same diary.'], $response->getData());
	}

	public function testHistoryRejectsHistoricalQuestionVersion(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);

		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);
		$question->setType(QuestionTypes::TEXT);
		$question->setActive(true);
		$question->setNextVersionId(12);

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::READ)
			->willReturn($this->createStub(Diary::class));
		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$answerMapper->expects($this->never())->method('getAnswerChainForEntryQuestion');

		$controller = new AnswerController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$entryMapper,
			$questionMapper,
			$answerMapper,
			'alice',
		);

		$response = $controller->history(5, 11);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Answers may only be created for the current question version.'], $response->getData());
	}

	public function testHistoryRejectsInactiveQuestion(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);

		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);

		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);
		$question->setType(QuestionTypes::TEXT);
		$question->setActive(false);
		$question->setNextVersionId(null);

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::READ)
			->willReturn($this->createStub(Diary::class));
		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$answerMapper->expects($this->never())->method('getAnswerChainForEntryQuestion');

		$controller = new AnswerController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$entryMapper,
			$questionMapper,
			$answerMapper,
			'alice',
		);

		$response = $controller->history(5, 11);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Inactive questions cannot be answered.'], $response->getData());
	}

	public function testIndexUsesReadPermissionAndReturnsAnswers(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);
		$answers = [new Answer()];

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::READ)->willReturn($this->createStub(Diary::class));
		$answerMapper->expects($this->once())->method('getCurrentAnswersForEntry')->with(5)->willReturn($answers);

		$controller = new AnswerController(Application::APP_ID, $request, $diaryMapper, $entryMapper, $questionMapper, $answerMapper, 'alice');
		$response = $controller->index(5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($answers, $response->getData());
	}

	public function testShowReturnsAnswerOnSuccess(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);
		$answer = new Answer();
		$answer->setId(5);
		$answer->setDiaryId(42);

		$answerMapper->expects($this->once())->method('getAnswer')->with(5)->willReturn($answer);
		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::READ)->willReturn($this->createStub(Diary::class));

		$controller = new AnswerController(Application::APP_ID, $request, $diaryMapper, $entryMapper, $questionMapper, $answerMapper, 'alice');
		$response = $controller->show(5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($answer, $response->getData());
	}

	public function testCreateReturnsCreatedAnswerOnSuccess(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);
		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);
		$question->setType(QuestionTypes::TEXT);
		$question->setActive(true);
		$question->setNextVersionId(null);
		$answer = new Answer();

		$entryMapper->expects($this->once())->method('getEntry')->with(5)->willReturn($entry);
		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::WRITE)->willReturn($this->createStub(Diary::class));
		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$answerMapper->expects($this->once())->method('createAnswer')->with(42, 5, 11, 'text', null)->willReturn($answer);

		$controller = new AnswerController(Application::APP_ID, $request, $diaryMapper, $entryMapper, $questionMapper, $answerMapper, 'alice');
		$response = $controller->create(5, 11, 'text');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($answer, $response->getData());
	}

	public function testUpdateReturnsUpdatedAnswerOnSuccess(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);
		$answer = new Answer();
		$answer->setId(5);
		$answer->setDiaryId(42);
		$answer->setQuestionId(11);
		$question = new Question();
		$question->setId(11);
		$question->setType(QuestionTypes::TEXT);

		$answerMapper->expects($this->once())->method('getAnswer')->with(5)->willReturn($answer);
		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::WRITE)->willReturn($this->createStub(Diary::class));
		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$answerMapper->expects($this->once())->method('updateAnswer')->with($answer, 'updated', null)->willReturn($answer);

		$controller = new AnswerController(Application::APP_ID, $request, $diaryMapper, $entryMapper, $questionMapper, $answerMapper, 'alice');
		$response = $controller->update(5, 'updated');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($answer, $response->getData());
	}

	public function testDeleteReturnsDeletedAnswerOnSuccess(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$entryMapper = $this->createMock(EntryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$answerMapper = $this->createMock(AnswerMapper::class);
		$answer = new Answer();
		$answer->setId(5);
		$answer->setDiaryId(42);

		$answerMapper->expects($this->once())->method('getAnswer')->with(5)->willReturn($answer);
		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::WRITE)->willReturn($this->createStub(Diary::class));
		$answerMapper->expects($this->once())->method('deleteAnswer')->with($answer)->willReturn($answer);

		$controller = new AnswerController(Application::APP_ID, $request, $diaryMapper, $entryMapper, $questionMapper, $answerMapper, 'alice');
		$response = $controller->delete(5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($answer, $response->getData());
	}
}
