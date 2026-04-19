<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use Throwable;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCA\StructuredDiary\Db\DiaryShareMapper;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class DiaryController extends ApiController {
	public const REQUIREMENTS = ['apiVersion' => 'v1'];

	public function __construct(
		string $appName,
		IRequest $request,
		private DiaryMapper $diaryMapper,
		private DiaryShareMapper $shareMapper,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/diaries', requirements: self::REQUIREMENTS)]
	public function index(): DataResponse {
		try {
			return $this->respond($this->diaryMapper->getAccessibleDiaries($this->requireUser($this->userId)));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/diaries/{id}', requirements: self::REQUIREMENTS)]
	public function show(int $id): DataResponse {
		try {
			return $this->respond($this->diaryMapper->getDiaryForUser($id, $this->requireUser($this->userId)));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/diaries/{id}/stats', requirements: self::REQUIREMENTS)]
	public function stats(int $id): DataResponse {
		try {
			return $this->respond($this->diaryMapper->getDiaryStats($id, $this->requireUser($this->userId)));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/diaries', requirements: self::REQUIREMENTS)]
	public function create(
		string $title,
		string $description = '',
		bool $reminderActive = false,
		int $reminderTime = 0,
		int $reminderCount = 3,
		int $reminderDelay = 2700,
		string $reminderSignalFirst = '',
		string $reminderSignalRepeat = '',
		int $entrySchedule = 86400,
	): DataResponse {
		try {
			$title = trim($title);
			if ($title === '') {
				throw new \InvalidArgumentException('Diary title is required.');
			}

			return $this->respond(
				$this->diaryMapper->createDiary(
					$this->requireUser($this->userId),
					$title,
					$description,
					$reminderActive,
					$reminderTime,
					$reminderCount,
					$reminderDelay,
					$reminderSignalFirst,
					$reminderSignalRepeat,
					$entrySchedule,
				),
				201
			);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/diaries/{id}', requirements: self::REQUIREMENTS)]
	public function update(
		int $id,
		?string $title = null,
		?string $description = null,
		?string $ownerUserId = null,
		?bool $reminderActive = null,
		?int $reminderTime = null,
		?int $reminderCount = null,
		?int $reminderDelay = null,
		?string $reminderSignalFirst = null,
		?string $reminderSignalRepeat = null,
		?int $entrySchedule = null,
	): DataResponse {
		try {
			if ($title !== null) {
				$title = trim($title);
				if ($title === '') {
					throw new \InvalidArgumentException('Diary title cannot be empty.');
				}
			}

			return $this->respond(
				$this->diaryMapper->updateDiary(
					$id,
					$this->requireUser($this->userId),
					$title,
					$description,
					$ownerUserId,
					$reminderActive,
					$reminderTime,
					$reminderCount,
					$reminderDelay,
					$reminderSignalFirst,
					$reminderSignalRepeat,
					$entrySchedule,
				)
			);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/diaries/{id}', requirements: self::REQUIREMENTS)]
	public function delete(int $id): DataResponse {
		try {
			return $this->respond($this->diaryMapper->deleteDiary($id, $this->requireUser($this->userId)));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/diaries/{id}/shares', requirements: self::REQUIREMENTS)]
	public function shares(int $id): DataResponse {
		try {
			$userId = $this->requireUser($this->userId);
			$diary = $this->diaryMapper->getDiaryForUser($id, $userId, DiaryPermissions::MANAGE);
			$this->diaryMapper->assertManageAccess($diary, $userId);

			return $this->respond($this->shareMapper->getSharesForDiary($id));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/diaries/{id}/shares', requirements: self::REQUIREMENTS)]
	public function createShare(int $id, string $sharedWith, int $permission): DataResponse {
		try {
			$userId = $this->requireUser($this->userId);
			$sharedWith = trim($sharedWith);
			$diary = $this->diaryMapper->getDiaryForUser($id, $userId, DiaryPermissions::MANAGE);
			$this->diaryMapper->assertManageAccess($diary, $userId);
			if ($sharedWith === '') {
				throw new \InvalidArgumentException('sharedWith is required.');
			}
			if ($sharedWith === $userId) {
				throw new \InvalidArgumentException('The owner cannot be shared explicitly.');
			}
			if (!in_array($permission, DiaryPermissions::all(), true)) {
				throw new \InvalidArgumentException('Unsupported permission value.');
			}

			return $this->respond($this->shareMapper->upsertShare($id, $sharedWith, $permission), 201);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/diaries/{id}/shares/{shareId}', requirements: self::REQUIREMENTS)]
	public function updateShare(int $id, int $shareId, int $permission): DataResponse {
		try {
			$userId = $this->requireUser($this->userId);
			$diary = $this->diaryMapper->getDiaryForUser($id, $userId, DiaryPermissions::MANAGE);
			$this->diaryMapper->assertManageAccess($diary, $userId);
			if (!in_array($permission, DiaryPermissions::all(), true)) {
				throw new \InvalidArgumentException('Unsupported permission value.');
			}
			$share = $this->shareMapper->getShare($shareId);
			if ($share->getDiaryId() !== $id) {
				throw new \InvalidArgumentException('Share does not belong to this diary.');
			}

			return $this->respond($this->shareMapper->upsertShare($id, $share->getSharedWith(), $permission));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/diaries/{id}/shares/{shareId}', requirements: self::REQUIREMENTS)]
	public function deleteShare(int $id, int $shareId): DataResponse {
		try {
			$userId = $this->requireUser($this->userId);
			$diary = $this->diaryMapper->getDiaryForUser($id, $userId, DiaryPermissions::MANAGE);
			$this->diaryMapper->assertManageAccess($diary, $userId);
			$share = $this->shareMapper->getShare($shareId);
			if ($share->getDiaryId() !== $id) {
				throw new \InvalidArgumentException('Share does not belong to this diary.');
			}

			return $this->respond($this->shareMapper->deleteShare($shareId));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}
}
