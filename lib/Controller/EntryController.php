<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use Throwable;
use OCA\StructuredDiary\ResponseDefinitions;
use OCA\StructuredDiary\Db\AnswerMapper;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCA\StructuredDiary\Db\EntryMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * @psalm-import-type StructuredDiaryAnswerCount from ResponseDefinitions
 * @psalm-import-type StructuredDiaryEntry from ResponseDefinitions
 */
class EntryController extends ApiOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private DiaryMapper $diaryMapper,
		private EntryMapper $entryMapper,
		private AnswerMapper $answerMapper,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List entries for a diary
	 *
	 * @return DataResponse<Http::STATUS_OK, list<StructuredDiaryEntry>, array{}>
	 *
	 * 200: Entries returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/diaries/{diaryId}/entries', requirements: ['apiVersion' => '(v1)'])]
	public function index(int $diaryId, ?int $fromTimestamp = null, ?int $untilTimestamp = null): DataResponse {
		try {
			$this->diaryMapper->getDiaryForUser($diaryId, $this->requireUser($this->userId), DiaryPermissions::READ);

			return $this->respond($this->entryMapper->getEntriesForDiary($diaryId, $fromTimestamp, $untilTimestamp));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	/**
	 * Show one entry
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryEntry, array{}>
	 *
	 * 200: Entry returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/entries/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function show(int $id): DataResponse {
		try {
			$entry = $this->entryMapper->getEntry($id);
			$this->diaryMapper->getDiaryForUser($entry->getDiaryId(), $this->requireUser($this->userId), DiaryPermissions::READ);

			return $this->respond($entry);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	/**
	 * Create an entry
	 *
	 * @return DataResponse<Http::STATUS_CREATED, StructuredDiaryEntry, array{}>
	 *
	 * 201: Entry created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/diaries/{diaryId}/entries', requirements: ['apiVersion' => '(v1)'])]
	public function create(int $diaryId, int $timestamp, ?string $title = null): DataResponse {
		try {
			$this->diaryMapper->getDiaryForUser($diaryId, $this->requireUser($this->userId), DiaryPermissions::WRITE);

			return $this->respond($this->entryMapper->createEntry($diaryId, $timestamp, $title), 201);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	/**
	 * Update an entry
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryEntry, array{}>
	 *
	 * 200: Entry updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/entries/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function update(int $id, ?int $timestamp = null, ?string $title = null): DataResponse {
		try {
			$entry = $this->entryMapper->getEntry($id);
			$this->diaryMapper->getDiaryForUser($entry->getDiaryId(), $this->requireUser($this->userId), DiaryPermissions::WRITE);

			return $this->respond($this->entryMapper->updateEntry($entry, $timestamp, $title));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	/**
	 * Delete an entry and all answers including answer history
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryEntry, array{}>
	 *
	 * 200: Entry deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/entries/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function delete(int $id): DataResponse {
		try {
			$entry = $this->entryMapper->getEntry($id);
			$this->diaryMapper->getDiaryForUser($entry->getDiaryId(), $this->requireUser($this->userId), DiaryPermissions::WRITE);

			$this->answerMapper->deleteAnswersForEntry($entry->getId());

			return $this->respond($this->entryMapper->deleteEntry($entry));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	/**
	 * Count all answers for an entry, including answer history
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryAnswerCount, array{}>
	 *
	 * 200: Answer count returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/entries/{id}/answer-count', requirements: ['apiVersion' => '(v1)'])]
	public function answerCount(int $id): DataResponse {
		try {
			$entry = $this->entryMapper->getEntry($id);
			$this->diaryMapper->getDiaryForUser($entry->getDiaryId(), $this->requireUser($this->userId), DiaryPermissions::READ);

			return $this->respond(['count' => $this->answerMapper->countAnswersForEntry($entry->getId())]);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}
}
