<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use Throwable;
use OCA\StructuredDiary\ResponseDefinitions;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCA\StructuredDiary\Db\DiaryShareMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * @psalm-import-type StructuredDiaryDiary from ResponseDefinitions
 * @psalm-import-type StructuredDiaryDiaryShare from ResponseDefinitions
 * @psalm-import-type StructuredDiaryDiaryStats from ResponseDefinitions
 */
class DiaryController extends ApiOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private DiaryMapper $diaryMapper,
		private DiaryShareMapper $shareMapper,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List diaries readable by the current user
	 *
	 * @return DataResponse<Http::STATUS_OK, list<StructuredDiaryDiary>, array{}>
	 *
	 * 200: Diaries returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/diaries', requirements: ['apiVersion' => '(v1)'])]
	public function index(): DataResponse {
		try {
			return $this->respond($this->diaryMapper->getAccessibleDiaries($this->requireUser($this->userId)));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	/**
	 * List diary shares that affect the current user
	 *
	 * @return DataResponse<Http::STATUS_OK, list<StructuredDiaryDiaryShare>, array{}>
	 *
	 * 200: Diary shares returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/diary-shares', requirements: ['apiVersion' => '(v1)'])]
	public function myShares(): DataResponse {
		try {
			return $this->respond($this->shareMapper->getSharesForUser($this->requireUser($this->userId)));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	/**
	 * Show one readable diary
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryDiary, array{}>
	 *
	 * 200: Diary returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/diaries/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function show(int $id): DataResponse {
		try {
			return $this->respond($this->diaryMapper->getDiaryForUser($id, $this->requireUser($this->userId)));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	/**
	 * Return analytics for one readable diary
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryDiaryStats, array{}>
	 *
	 * 200: Diary stats returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/diaries/{id}/stats', requirements: ['apiVersion' => '(v1)'])]
	public function stats(int $id): DataResponse {
		try {
			return $this->respond($this->diaryMapper->getDiaryStats($id, $this->requireUser($this->userId)));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	/**
	 * Create a diary
	 *
	 * @return DataResponse<Http::STATUS_CREATED, StructuredDiaryDiary, array{}>
	 *
	 * 201: Diary created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/diaries', requirements: ['apiVersion' => '(v1)'])]
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

	/**
	 * Update a diary
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryDiary, array{}>
	 *
	 * 200: Diary updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/diaries/{id}', requirements: ['apiVersion' => '(v1)'])]
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

	/**
	 * Delete a diary
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryDiary, array{}>
	 *
	 * 200: Diary deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/diaries/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function delete(int $id): DataResponse {
		try {
			return $this->respond($this->diaryMapper->deleteDiary($id, $this->requireUser($this->userId)));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	/**
	 * List shares for a managed diary
	 *
	 * @return DataResponse<Http::STATUS_OK, list<StructuredDiaryDiaryShare>, array{}>
	 *
	 * 200: Diary shares returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/diaries/{id}/shares', requirements: ['apiVersion' => '(v1)'])]
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

	/**
	 * Create or update a diary share
	 *
	 * @return DataResponse<Http::STATUS_CREATED, StructuredDiaryDiaryShare, array{}>
	 *
	 * 201: Diary share created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/diaries/{id}/shares', requirements: ['apiVersion' => '(v1)'])]
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

	/**
	 * Update a diary share
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryDiaryShare, array{}>
	 *
	 * 200: Diary share updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/diaries/{id}/shares/{shareId}', requirements: ['apiVersion' => '(v1)'])]
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

	/**
	 * Delete a diary share
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryDiaryShare, array{}>
	 *
	 * 200: Diary share deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/diaries/{id}/shares/{shareId}', requirements: ['apiVersion' => '(v1)'])]
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
