<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use Throwable;
use OCA\StructuredDiary\Db\AnswerMapper;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCA\StructuredDiary\Db\EntryMapper;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class EntryController extends ApiController {
	public const REQUIREMENTS = ['apiVersion' => 'v1'];

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

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/diaries/{diaryId}/entries', requirements: self::REQUIREMENTS)]
	public function index(int $diaryId, ?int $fromTimestamp = null, ?int $untilTimestamp = null): DataResponse {
		try {
			$this->diaryMapper->getDiaryForUser($diaryId, $this->requireUser($this->userId), DiaryPermissions::READ);

			return $this->respond($this->entryMapper->getEntriesForDiary($diaryId, $fromTimestamp, $untilTimestamp));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/entries/{id}', requirements: self::REQUIREMENTS)]
	public function show(int $id): DataResponse {
		try {
			$entry = $this->entryMapper->getEntry($id);
			$this->diaryMapper->getDiaryForUser($entry->getDiaryId(), $this->requireUser($this->userId), DiaryPermissions::READ);

			return $this->respond($entry);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/diaries/{diaryId}/entries', requirements: self::REQUIREMENTS)]
	public function create(int $diaryId, int $timestamp, ?string $title = null): DataResponse {
		try {
			$this->diaryMapper->getDiaryForUser($diaryId, $this->requireUser($this->userId), DiaryPermissions::WRITE);

			return $this->respond($this->entryMapper->createEntry($diaryId, $timestamp, $title), 201);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/entries/{id}', requirements: self::REQUIREMENTS)]
	public function update(int $id, ?int $timestamp = null, ?string $title = null): DataResponse {
		try {
			$entry = $this->entryMapper->getEntry($id);
			$this->diaryMapper->getDiaryForUser($entry->getDiaryId(), $this->requireUser($this->userId), DiaryPermissions::WRITE);

			return $this->respond($this->entryMapper->updateEntry($entry, $timestamp, $title));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/entries/{id}', requirements: self::REQUIREMENTS)]
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

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/entries/{id}/answer-count', requirements: self::REQUIREMENTS)]
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
