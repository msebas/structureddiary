<?php

declare(strict_types=1);

namespace Controller;

use OCA\StructuredDiary\AppInfo\Application;
use OCA\StructuredDiary\Controller\QuestionController;
use OCA\StructuredDiary\Db\Diary;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCA\StructuredDiary\Db\Question;
use OCA\StructuredDiary\Db\QuestionMapper;
use OCA\StructuredDiary\Db\QuestionTypes;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class QuestionControllerTest extends TestCase {
	public function testTypesReturnsSupportedQuestionTypes(): void {
		$controller = new QuestionController(
			Application::APP_ID,
			$this->createMock(IRequest::class),
			$this->createMock(DiaryMapper::class),
			$this->createMock(QuestionMapper::class),
			'alice',
		);

		$response = $controller->types();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(QuestionTypes::definitions(), $response->getData());
		$this->assertContains(['id' => 'INTEGER', 'value' => QuestionTypes::INTEGER], $response->getData());
	}

	public function testUpdatePassesTypeToMapper(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);

		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);
		$question->setLabel('Old');
		$question->setDisplayText('Old');
		$question->setType(QuestionTypes::TEXT);

		$diaryMapper->expects($this->once())->method('getDiaryForUser')->willReturn($this->createStub(Diary::class));
		$diaryMapper->expects($this->once())->method('assertManageAccess');
		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$questionMapper->expects($this->once())
			->method('updateQuestion')
			->with(
				$question,
				'Number',
				null,
				QuestionTypes::NUMBER,
				1.0,
				10.0,
				null,
				true,
				'template',
			)
			->willReturn($question);

		$controller = new QuestionController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$questionMapper,
			'alice',
		);

		$response = $controller->update(11, 'Number', null, QuestionTypes::NUMBER, 1.0, 10.0, null, true, 'template');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdatePassesSeparateLabelAndDisplayTextToMapper(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$diary = new Diary();
		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);

		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::MANAGE)->willReturn($diary);
		$diaryMapper->expects($this->once())->method('assertManageAccess')->with($diary, 'alice');
		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$questionMapper->expects($this->once())
			->method('updateQuestion')
			->with($question, 'Mood label', 'How do you feel today?', null, null, null, null, null, null)
			->willReturn($question);

		$controller = new QuestionController(Application::APP_ID, $request, $diaryMapper, $questionMapper, 'alice');
		$response = $controller->update(11, ' Mood label ', ' How do you feel today? ');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($question, $response->getData());
	}

	public function testUpdateReturnsErrorWhenManagePermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);

		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);

		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willThrowException(new DoesNotExistException('Diary not manageable'));
		$questionMapper->expects($this->never())->method('updateQuestion');

		$controller = new QuestionController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$questionMapper,
			'alice',
		);

		$response = $controller->update(11, 'Mood');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Diary not manageable'], $response->getData());
	}

	public function testShowReturnsErrorWhenReadPermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);

		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);

		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::READ)
			->willThrowException(new DoesNotExistException('Diary not accessible'));

		$controller = new QuestionController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$questionMapper,
			'alice',
		);

		$response = $controller->show(11);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Diary not accessible'], $response->getData());
	}

	public function testShowReturnsQuestionOnSuccess(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);

		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::READ)
			->willReturn($this->createStub(Diary::class));

		$controller = new QuestionController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$questionMapper,
			'alice',
		);

		$response = $controller->show(11);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($question, $response->getData());
	}

	public function testVersionsUsesReadPermissionAndReturnsQuestionChain(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);
		$chain = [$question, new Question()];

		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::READ)->willReturn($this->createStub(Diary::class));
		$questionMapper->expects($this->once())->method('getQuestionChain')->with(11)->willReturn($chain);

		$controller = new QuestionController(Application::APP_ID, $request, $diaryMapper, $questionMapper, 'alice');
		$response = $controller->versions(11);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($chain, $response->getData());
	}

	public function testActiveUsesReadPermissionAndReturnsQuestionsForTimestamp(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$questions = [new Question()];

		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::READ)->willReturn($this->createStub(Diary::class));
		$questionMapper->expects($this->once())->method('getQuestionsForDiaryAtTimestamp')->with(42, 1713254400, true)->willReturn($questions);

		$controller = new QuestionController(Application::APP_ID, $request, $diaryMapper, $questionMapper, 'alice');
		$response = $controller->active(42, 1713254400);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($questions, $response->getData());
	}

	public function testCreateRejectsMissingLabelAndDisplayText(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$diary = new Diary();

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willReturn($diary);
		$diaryMapper->expects($this->once())
			->method('assertManageAccess')
			->with($diary, 'alice');
		$questionMapper->expects($this->never())->method('createQuestion');

		$controller = new QuestionController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$questionMapper,
			'alice',
		);

		$response = $controller->create(42, '   ', null);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'A question label/display text is required.'], $response->getData());
	}

	public function testCreateReturnsErrorWhenManagePermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willThrowException(new DoesNotExistException('Diary not manageable'));
		$questionMapper->expects($this->never())->method('createQuestion');

		$controller = new QuestionController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$questionMapper,
			'alice',
		);

		$response = $controller->create(42, 'Mood');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Diary not manageable'], $response->getData());
	}

	public function testUpdateRejectsEmptySynchronizedLabelAndDisplayText(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);
		$diary = new Diary();

		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willReturn($diary);
		$diaryMapper->expects($this->once())
			->method('assertManageAccess')
			->with($diary, 'alice');
		$questionMapper->expects($this->never())->method('updateQuestion');

		$controller = new QuestionController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$questionMapper,
			'alice',
		);

		$response = $controller->update(11, '   ', null);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Question label/display text cannot be empty.'], $response->getData());
	}

	public function testCreateNormalizesChoicesAndPreservesSeparateDisplayText(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$diary = new Diary();
		$question = new Question();

		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willReturn($diary);
		$diaryMapper->expects($this->once())
			->method('assertManageAccess')
			->with($diary, 'alice');
		$questionMapper->expects($this->once())
			->method('createQuestion')
			->with(
				42,
				'Question label',
				'Mood?',
				QuestionTypes::SELECT,
				null,
				null,
				['yes', 'no'],
				true,
				'template',
			)
			->willReturn($question);

		$controller = new QuestionController(
			Application::APP_ID,
			$request,
			$diaryMapper,
			$questionMapper,
			'alice',
		);

		$response = $controller->create(42, ' Question label ', ' Mood? ', QuestionTypes::SELECT, null, null, [' yes ', '', 'no '], true, 'template');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($question, $response->getData());
	}

	public function testIndexUsesReadPermissionAndReturnsQuestions(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$questions = [new Question()];

		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::READ)->willReturn($this->createStub(Diary::class));
		$questionMapper->expects($this->once())->method('getCurrentQuestionsForDiary')->with(42)->willReturn($questions);

		$controller = new QuestionController(Application::APP_ID, $request, $diaryMapper, $questionMapper, 'alice');
		$response = $controller->index(42);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($questions, $response->getData());
	}

	public function testCreateUsesLabelWhenDisplayTextIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$diary = new Diary();
		$question = new Question();

		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::MANAGE)->willReturn($diary);
		$diaryMapper->expects($this->once())->method('assertManageAccess')->with($diary, 'alice');
		$questionMapper->expects($this->once())
			->method('createQuestion')
			->with(42, 'Mood', 'Mood', QuestionTypes::TEXT, null, null, null, true, '')
			->willReturn($question);

		$controller = new QuestionController(Application::APP_ID, $request, $diaryMapper, $questionMapper, 'alice');
		$response = $controller->create(42, 'Mood');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($question, $response->getData());
	}

	public function testUpdateUsesDisplayTextWhenLabelIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$diary = new Diary();
		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);

		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::MANAGE)->willReturn($diary);
		$diaryMapper->expects($this->once())->method('assertManageAccess')->with($diary, 'alice');
		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$questionMapper->expects($this->once())
			->method('updateQuestion')
			->with($question, null, 'Mood?', null, null, null, null, null, null)
			->willReturn($question);

		$controller = new QuestionController(Application::APP_ID, $request, $diaryMapper, $questionMapper, 'alice');
		$response = $controller->update(11, null, 'Mood?');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($question, $response->getData());
	}

	public function testDeleteUsesManagePermissionAndReturnsDeletedQuestion(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$diary = new Diary();
		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);

		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::MANAGE)->willReturn($diary);
		$diaryMapper->expects($this->once())->method('assertManageAccess')->with($diary, 'alice');
		$questionMapper->expects($this->once())->method('deleteQuestion')->with($question)->willReturn($question);

		$controller = new QuestionController(Application::APP_ID, $request, $diaryMapper, $questionMapper, 'alice');
		$response = $controller->delete(11);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($question, $response->getData());
	}

	public function testReorderUsesManagePermissionAndReturnsReorderedQuestion(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$diary = new Diary();
		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);
		$question->setChainId(11);
		$question->setDiaryQuestionOrder(4);

		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$diaryMapper->expects($this->once())->method('getDiaryForUser')->with(42, 'alice', DiaryPermissions::MANAGE)->willReturn($diary);
		$diaryMapper->expects($this->once())->method('assertManageAccess')->with($diary, 'alice');
		$questionMapper->expects($this->once())->method('reorderQuestion')->with($question, 2)->willReturn($question);

		$controller = new QuestionController(Application::APP_ID, $request, $diaryMapper, $questionMapper, 'alice');
		$response = $controller->reorder(11, 2);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($question, $response->getData());
	}

	public function testReorderReturnsErrorWhenManagePermissionIsMissing(): void {
		$request = $this->createMock(IRequest::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$questionMapper = $this->createMock(QuestionMapper::class);
		$question = new Question();
		$question->setId(11);
		$question->setDiaryId(42);

		$questionMapper->expects($this->once())->method('getQuestion')->with(11)->willReturn($question);
		$diaryMapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'alice', DiaryPermissions::MANAGE)
			->willThrowException(new DoesNotExistException('Diary not manageable'));
		$questionMapper->expects($this->never())->method('reorderQuestion');

		$controller = new QuestionController(Application::APP_ID, $request, $diaryMapper, $questionMapper, 'alice');
		$response = $controller->reorder(11, 2);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Diary not manageable'], $response->getData());
	}
}
