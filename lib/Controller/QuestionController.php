<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use Throwable;
use OCA\StructuredDiary\ResponseDefinitions;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCA\StructuredDiary\Db\QuestionMapper;
use OCA\StructuredDiary\Db\QuestionTypes;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * @psalm-import-type StructuredDiaryQuestion from ResponseDefinitions
 * @psalm-import-type StructuredDiaryQuestionTypeDefinition from ResponseDefinitions
 */
class QuestionController extends ApiOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private DiaryMapper $diaryMapper,
		private QuestionMapper $questionMapper,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List current question versions for a diary
	 *
	 * @return DataResponse<Http::STATUS_OK, list<StructuredDiaryQuestion>, array{}>
	 *
	 * 200: Questions returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/diaries/{diaryId}/questions', requirements: ['apiVersion' => '(v1)'])]
	public function index(int $diaryId): DataResponse {
		try {
			$this->diaryMapper->getDiaryForUser($diaryId, $this->requireUser($this->userId), DiaryPermissions::READ);

			return $this->respond($this->questionMapper->getCurrentQuestionsForDiary($diaryId));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	/**
	 * List supported question types
	 *
	 * @return DataResponse<Http::STATUS_OK, list<StructuredDiaryQuestionTypeDefinition>, array{}>
	 *
	 * 200: Question types returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/question-types', requirements: ['apiVersion' => '(v1)'])]
	public function types(): DataResponse {
		return $this->respond(QuestionTypes::definitions());
	}

	/**
	 * Show one question version
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryQuestion, array{}>
	 *
	 * 200: Question returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/questions/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function show(int $id): DataResponse {
		try {
			$question = $this->questionMapper->getQuestion($id);
			$this->diaryMapper->getDiaryForUser($question->getDiaryId(), $this->requireUser($this->userId), DiaryPermissions::READ);

			return $this->respond($question);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	/**
	 * List all versions of a question chain
	 *
	 * @return DataResponse<Http::STATUS_OK, list<StructuredDiaryQuestion>, array{}>
	 *
	 * 200: Question versions returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/questions/{id}/versions', requirements: ['apiVersion' => '(v1)'])]
	public function versions(int $id): DataResponse {
		try {
			$question = $this->questionMapper->getQuestion($id);
			$this->diaryMapper->getDiaryForUser($question->getDiaryId(), $this->requireUser($this->userId), DiaryPermissions::READ);

			return $this->respond($this->questionMapper->getQuestionChain($id));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	/**
	 * List active questions for a diary at a timestamp
	 *
	 * @return DataResponse<Http::STATUS_OK, list<StructuredDiaryQuestion>, array{}>
	 *
	 * 200: Active questions returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/diaries/{diaryId}/questions/active', requirements: ['apiVersion' => '(v1)'])]
	public function active(int $diaryId, int $timestamp): DataResponse {
		try {
			$this->diaryMapper->getDiaryForUser($diaryId, $this->requireUser($this->userId), DiaryPermissions::READ);

			return $this->respond($this->questionMapper->getQuestionsForDiaryAtTimestamp($diaryId, $timestamp, true));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	/**
	 * Create a question
	 *
	 * @param list<string>|null $choices
	 * @return DataResponse<Http::STATUS_CREATED, StructuredDiaryQuestion, array{}>
	 *
	 * 201: Question created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/diaries/{diaryId}/questions', requirements: ['apiVersion' => '(v1)'])]
	public function create(
		int $diaryId,
		?string $label = null,
		?string $displayText = null,
		string $type = QuestionTypes::TEXT,
		?float $minimum = null,
		?float $maximum = null,
		?array $choices = null,
		bool $active = true,
		string $templateText = ''
	): DataResponse {
		try {
			$userId = $this->requireUser($this->userId);
			$diary = $this->diaryMapper->getDiaryForUser($diaryId, $userId, DiaryPermissions::MANAGE);
			$this->diaryMapper->assertManageAccess($diary, $userId);
			$synced = trim($displayText ?? $label ?? '');
			if ($synced === '') {
				throw new \InvalidArgumentException('A question label/display text is required.');
			}

			return $this->respond(
				$this->questionMapper->createQuestion(
					$diaryId,
					$synced,
					$synced,
					$type,
					$minimum,
					$maximum,
					$this->normalizeStringList($choices),
					$active,
					$templateText
				),
				201
			);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	/**
	 * Create a new version of a question
	 *
	 * @param list<string>|null $choices
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryQuestion, array{}>
	 *
	 * 200: Question updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/questions/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function update(
		int $id,
		?string $label = null,
		?string $displayText = null,
		?string $type = null,
		?float $minimum = null,
		?float $maximum = null,
		?array $choices = null,
		?bool $active = null,
		?string $templateText = null
	): DataResponse {
		try {
			$userId = $this->requireUser($this->userId);
			$question = $this->questionMapper->getQuestion($id);
			$diary = $this->diaryMapper->getDiaryForUser($question->getDiaryId(), $userId, DiaryPermissions::MANAGE);
			$this->diaryMapper->assertManageAccess($diary, $userId);
			$syncedLabel = $label;
			$syncedDisplayText = $displayText;
			if ($label !== null || $displayText !== null) {
				$synced = trim($displayText ?? $label ?? '');
				if ($synced === '') {
					throw new \InvalidArgumentException('Question label/display text cannot be empty.');
				}
				$syncedLabel = $synced;
				$syncedDisplayText = $synced;
			}

			return $this->respond(
				$this->questionMapper->updateQuestion(
					$question,
					$syncedLabel,
					$syncedDisplayText,
					$type,
					$minimum,
					$maximum,
					$this->normalizeStringList($choices),
					$active,
					$templateText
				)
			);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	/**
	 * Change the order of a question
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryQuestion, array{}>
	 *
	 * 200: Question reordered
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/questions/{id}/order', requirements: ['apiVersion' => '(v1)'])]
	public function reorder(int $id, int $diaryQuestionOrder): DataResponse {
		try {
			$userId = $this->requireUser($this->userId);
			$question = $this->questionMapper->getQuestion($id);
			$diary = $this->diaryMapper->getDiaryForUser($question->getDiaryId(), $userId, DiaryPermissions::MANAGE);
			$this->diaryMapper->assertManageAccess($diary, $userId);

			return $this->respond($this->questionMapper->reorderQuestion($question, $diaryQuestionOrder));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	/**
	 * Deactivate a question by creating a new inactive version
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryQuestion, array{}>
	 *
	 * 200: Question deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/questions/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function delete(int $id): DataResponse {
		try {
			$userId = $this->requireUser($this->userId);
			$question = $this->questionMapper->getQuestion($id);
			$diary = $this->diaryMapper->getDiaryForUser($question->getDiaryId(), $userId, DiaryPermissions::MANAGE);
			$this->diaryMapper->assertManageAccess($diary, $userId);

			return $this->respond($this->questionMapper->deleteQuestion($question));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}
}
