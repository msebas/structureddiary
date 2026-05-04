<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use Throwable;
use OCA\StructuredDiary\ResponseDefinitions;
use OCA\StructuredDiary\Db\Answer;
use OCA\StructuredDiary\Db\AnswerMapper;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCA\StructuredDiary\Db\EntryMapper;
use OCA\StructuredDiary\Db\Question;
use OCA\StructuredDiary\Db\QuestionMapper;
use OCA\StructuredDiary\Db\QuestionTypeValidator;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * @psalm-import-type StructuredDiaryAnswer from ResponseDefinitions
 */
class AnswerController extends ApiOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private DiaryMapper $diaryMapper,
		private EntryMapper $entryMapper,
		private QuestionMapper $questionMapper,
		private AnswerMapper $answerMapper,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List current answers for an entry
	 *
	 * @return DataResponse<Http::STATUS_OK, list<StructuredDiaryAnswer>, array{}>
	 *
	 * 200: Answers returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/entries/{entryId}/answers', requirements: ['apiVersion' => '(v1)'])]
	public function index(int $entryId): DataResponse {
		try {
			$entry = $this->entryMapper->getEntry($entryId);
			$this->diaryMapper->getDiaryForUser($entry->getDiaryId(), $this->requireUser($this->userId), DiaryPermissions::READ);

			return $this->respond($this->answerMapper->getCurrentAnswersForEntry($entryId));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	/**
	 * List answer history for one entry/question pair
	 *
	 * @return DataResponse<Http::STATUS_OK, list<StructuredDiaryAnswer>, array{}>
	 *
	 * 200: Answer history returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/entries/{entryId}/questions/{questionId}/answers/history', requirements: ['apiVersion' => '(v1)'])]
	public function history(int $entryId, int $questionId): DataResponse {
		try {
			$entry = $this->entryMapper->getEntry($entryId);
			$this->diaryMapper->getDiaryForUser($entry->getDiaryId(), $this->requireUser($this->userId), DiaryPermissions::READ);
			$question = $this->questionMapper->getQuestion($questionId);
			$this->assertQuestionMatchesEntry($question, $entry->getDiaryId());

			return $this->respond($this->answerMapper->getAnswerChainForEntryQuestion($entryId, $questionId));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	/**
	 * Show one answer
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryAnswer, array{}>
	 *
	 * 200: Answer returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/answers/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function show(int $id): DataResponse {
		try {
			$answer = $this->answerMapper->getAnswer($id);
			$this->diaryMapper->getDiaryForUser($answer->getDiaryId(), $this->requireUser($this->userId), DiaryPermissions::READ);

			return $this->respond($answer);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	/**
	 * Create an answer
	 *
	 * @return DataResponse<Http::STATUS_CREATED, StructuredDiaryAnswer, array{}>
	 *
	 * 201: Answer created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/entries/{entryId}/answers', requirements: ['apiVersion' => '(v1)'])]
	public function create(int $entryId, int $questionId, ?string $textContent = null, ?float $numericContent = null): DataResponse {
		try {
			$entry = $this->entryMapper->getEntry($entryId);
			$this->diaryMapper->getDiaryForUser($entry->getDiaryId(), $this->requireUser($this->userId), DiaryPermissions::WRITE);
			$question = $this->questionMapper->getQuestion($questionId);
			$this->assertQuestionMatchesEntry($question, $entry->getDiaryId());
			QuestionTypeValidator::validateAnswerPayload($question, $textContent, $numericContent);

			return $this->respond(
				$this->answerMapper->createAnswer($entry->getDiaryId(), $entryId, $questionId, $textContent, $numericContent),
				201
			);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	/**
	 * Create a new answer version
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryAnswer, array{}>
	 *
	 * 200: Answer updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/answers/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function update(int $id, ?string $textContent = null, ?float $numericContent = null): DataResponse {
		try {
			/** @var Answer $answer */
			$answer = $this->answerMapper->getAnswer($id);
			$this->diaryMapper->getDiaryForUser($answer->getDiaryId(), $this->requireUser($this->userId), DiaryPermissions::WRITE);
			/** @var Question $question */
			$question = $this->questionMapper->getQuestion($answer->getQuestionId());
			QuestionTypeValidator::validateAnswerPayload($question, $textContent, $numericContent);

			return $this->respond($this->answerMapper->updateAnswer($answer, $textContent, $numericContent));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	/**
	 * Delete an answer
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryAnswer, array{}>
	 *
	 * 200: Answer deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/answers/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function delete(int $id): DataResponse {
		try {
			$answer = $this->answerMapper->getAnswer($id);
			$this->diaryMapper->getDiaryForUser($answer->getDiaryId(), $this->requireUser($this->userId), DiaryPermissions::WRITE);

			return $this->respond($this->answerMapper->deleteAnswer($answer));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	private function assertQuestionMatchesEntry(Question $question, int $diaryId): void {
		if ($question->getDiaryId() !== $diaryId) {
			throw new \InvalidArgumentException('Question and entry must belong to the same diary.');
		}
		if ($question->getNextVersionId() !== null) {
			throw new \InvalidArgumentException('Answers may only be created for the current question version.');
		}
		if (!$question->getActive()) {
			throw new \InvalidArgumentException('Inactive questions cannot be answered.');
		}
	}
}
