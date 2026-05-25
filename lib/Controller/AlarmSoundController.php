<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use Throwable;
use OCA\StructuredDiary\Db\AlarmSoundMapper;
use OCA\StructuredDiary\ResponseDefinitions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * @psalm-import-type StructuredDiaryAlarmSound from ResponseDefinitions
 */
class AlarmSoundController extends ApiOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private AlarmSoundMapper $alarmSoundMapper,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List alarm sounds
	 *
	 * @return DataResponse<Http::STATUS_OK, list<StructuredDiaryAlarmSound>, array{}>
	 *
	 * 200: Alarm sounds returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/alarm-sounds', requirements: ['apiVersion' => '(v1)'])]
	public function index(): DataResponse {
		try {
			$this->requireUser($this->userId);

			return $this->respond($this->alarmSoundMapper->getAlarmSounds());
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), 404);
		}
	}

	/**
	 * Create or refresh an alarm sound
	 *
	 * @param list<string>|null $osAffinity
	 * @return DataResponse<Http::STATUS_CREATED, StructuredDiaryAlarmSound, array{}>
	 *
	 * 201: Alarm sound created or refreshed
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/alarm-sounds', requirements: ['apiVersion' => '(v1)'])]
	public function create(
		string $name,
		?string $path = null,
		?array $osAffinity = null,
		bool $isDefault = false,
	): DataResponse {
		try {
			$this->requireUser($this->userId);

			return $this->respond(
				$this->alarmSoundMapper->upsertAlarmSound(
					$name,
					$path,
					$this->normalizeStringList($osAffinity) ?? [],
					$isDefault,
				),
				Http::STATUS_CREATED,
			);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	/**
	 * Replace an alarm sound
	 *
	 * @param list<string>|null $osAffinity
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryAlarmSound, array{}>
	 *
	 * 200: Alarm sound updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/alarm-sounds/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function update(
		int $id,
		?string $name = null,
		?string $path = null,
		?array $osAffinity = null,
		?bool $isDefault = null,
	): DataResponse {
		return $this->change($id, $name, $path, $osAffinity, $isDefault);
	}

	/**
	 * Patch an alarm sound
	 *
	 * @param list<string>|null $osAffinity
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryAlarmSound, array{}>
	 *
	 * 200: Alarm sound patched
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/api/{apiVersion}/alarm-sounds/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function patch(
		int $id,
		?string $name = null,
		?string $path = null,
		?array $osAffinity = null,
		?bool $isDefault = null,
	): DataResponse {
		return $this->change($id, $name, $path, $osAffinity, $isDefault);
	}

	/**
	 * @param list<string>|null $osAffinity
	 */
	private function change(int $id, ?string $name, ?string $path, ?array $osAffinity, ?bool $isDefault): DataResponse {
		try {
			$this->requireUser($this->userId);

			return $this->respond(
				$this->alarmSoundMapper->updateAlarmSound(
					$id,
					$name,
					$path,
					$this->normalizeStringList($osAffinity),
					$isDefault,
				)
			);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}
}
