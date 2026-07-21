<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisArtifact;
use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\ResponseDefinitions;
use OCA\StructuredDiary\Service\AnalysisArtifactStorageService;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCA\StructuredDiary\Service\AnalysisExportService;
use OCA\StructuredDiary\Service\AnalysisJobFinalizationService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\RequestHeader;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IDBConnection;
use Throwable;

	/**
	 * @psalm-import-type StructuredDiaryAnalysisJob from ResponseDefinitions
	 * @psalm-import-type StructuredDiaryPythonAnalysisJob from ResponseDefinitions
	 * @psalm-import-type StructuredDiaryAnalysisArtifact from ResponseDefinitions
 * @psalm-import-type StructuredDiaryHealthcheck from ResponseDefinitions
 * @psalm-import-type StructuredDiaryAnalysisExportDiary from ResponseDefinitions
 * @psalm-import-type StructuredDiaryAnalysisExportEntries from ResponseDefinitions
 */
#[OpenAPI(scope: 'python_analysis')]
class PythonAnalysisController extends ApiOCSController {
	private const DEFAULT_MAX_JOB_SIZE = 134217728;
	private const DEFAULT_MAX_JOB_ARTIFACTS = 128;
	private const LONG_POLL_MAX_TIMEOUT = 3600;
	private const LONG_POLL_CHECK_INTERVAL = 1;

	public function __construct(
		string $appName,
		IRequest $request,
		private AnalysisJobMapper $jobMapper,
		private AnalysisArtifactMapper $artifactMapper,
		private AnalysisExportService $exportService,
		private AnalysisArtifactStorageService $artifactStorage,
		private AnalysisConfigService $configService,
		private AnalysisJobFinalizationService $finalizationService,
		private IDBConnection $db,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Poll analysis jobs for the analysis service
	 *
	 * @return DataResponse<Http::STATUS_OK, list<StructuredDiaryPythonAnalysisJob>, array{}>
	 *
	 * 200: Analysis jobs returned
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequestHeader(name: 'x-structureddiary-nextcloud-api-token', description: 'API token registered for this Nextcloud instance')]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/service/jobs/poll', requirements: ['apiVersion' => '(v1)'])]
	public function poll(null|int|string $changedSince = null, null|int|string $longPollTimeout = null, null|int|string $wait = null): DataResponse {
		try {
			$this->assertNextcloudApiToken($this->request->getHeader('x-structureddiary-nextcloud-api-token'));
			$changedSinceTimestamp = $this->parseChangedSince($changedSince);
			$timeout = $this->parseLongPollTimeout($longPollTimeout ?? $wait);
			$jobs = $this->jobMapper->getJobsForPython($changedSinceTimestamp);

			if ($changedSinceTimestamp === null || $jobs !== [] || $timeout === 0) {
				return $this->respond($this->jsonPythonJobs($jobs));
			}

			$deadline = $this->getCurrentTimestamp() + $timeout;
			while ($jobs === [] && $this->getCurrentTimestamp() < $deadline) {
				$this->waitBeforeNextLongPollCheck();
				$jobs = $this->jobMapper->getJobsForPython($changedSinceTimestamp);
			}

			return $this->respond($this->jsonPythonJobs($jobs));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

    /**
     * Lists analysis jobs for the analysis service that can be deleted
     *
     * @return DataResponse<Http::STATUS_OK, list<string>, array{}>
     *
     * 200: Analysis jobs returned
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    #[RequestHeader(name: 'x-structureddiary-nextcloud-api-token', description: 'API token registered for this Nextcloud instance')]
    #[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/service/jobs/to_delete', requirements: ['apiVersion' => '(v1)'])]
    public function toDelete(): DataResponse {
        try {
            $this->assertNextcloudApiToken($this->request->getHeader('x-structureddiary-nextcloud-api-token'));
            $job_uuids = $this->jobMapper->getJobsForPythonToDelete();
            return $this->respond($job_uuids);
        } catch (Throwable $e) {
            return $this->respondError($e->getMessage(), Http::STATUS_BAD_REQUEST);
        }
    }

	/**
	 * Check analysis service access
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryHealthcheck, array{}>
	 *
	 * 200: Analysis service access confirmed
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequestHeader(name: 'x-structureddiary-service-secret', description: 'Secret configured for the analysis service')]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/service/jobs/healthcheck', requirements: ['apiVersion' => '(v1)'])]
	public function healthcheck(): DataResponse {
		try {
			$configuredSecret = $this->configService->getServiceSecret();
			$providedSecret = $this->request->getHeader('x-structureddiary-service-secret');
			if ($configuredSecret === '' || !hash_equals($configuredSecret, $providedSecret)) {
				return $this->respondError('Invalid service secret.', Http::STATUS_UNAUTHORIZED);
			}

			return $this->respond(['ok' => true]);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

    /**
     * Update analysis job status from the analysis service
     *
     * @return DataResponse<Http::STATUS_OK, StructuredDiaryAnalysisJob, array{}>
     *
     * 200: Analysis job status updated
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    #[RequestHeader(name: 'x-structureddiary-job-token', description: 'Token assigned to the analysis job')]
    #[RequestHeader(name: 'x-structureddiary-job-uuid', description: 'UUID assigned by Nextcloud to the analysis job')]
    #[ApiRoute(verb: 'PATCH', url: '/api/{apiVersion}/service/jobs/{uuid}/status', requirements: ['apiVersion' => '(v1)'])]
    public function updateStatus(
        string $uuid,
        ?string $status = null,
        ?float $progress = null,
        ?string $statusMessage = null,
        ?string $errorMessage = null,
    ): DataResponse {
        try {
            $job = $this->jobMapper->getJobByUuid($uuid);
            $this->jobMapper->assertPythonAccess($job, $this->request->getHeader('x-structureddiary-job-token'), $this->request->getHeader('x-structureddiary-job-uuid'), false, time());
            $updated = $this->jobMapper->updateFromPython($job, $status, $progress, $statusMessage, $errorMessage);

            return $this->respond($updated);
        } catch (Throwable $e) {
            return $this->respondError($e->getMessage(), Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * Notify the NC instance that python service has deleted stored files after finalization was successfully
     *
     * @return DataResponse<Http::STATUS_OK, StructuredDiaryAnalysisJob, array{}>
     *
     * 200: Analysis job status updated
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    #[RequestHeader(name: 'x-structureddiary-job-token', description: 'Token assigned to the analysis job')]
    #[RequestHeader(name: 'x-structureddiary-job-uuid', description: 'UUID assigned by Nextcloud to the analysis job')]
    #[ApiRoute(verb: 'PATCH', url: '/api/{apiVersion}/service/jobs/{uuid}/set_deleted', requirements: ['apiVersion' => '(v1)'])]
    public function setDeleted(
        string $uuid
    ): DataResponse {
        try {
            $job = $this->jobMapper->getJobByUuid($uuid);
            $this->jobMapper->assertPythonAccess($job, $this->request->getHeader('x-structureddiary-job-token'), $this->request->getHeader('x-structureddiary-job-uuid'), false, time());
            $this->jobMapper->finishPythonDelete($job);
            $updated = $this->jobMapper->getJobByUuid($uuid);
            return $this->respond($updated);
        } catch (Throwable $e) {
            return $this->respondError($e->getMessage(), Http::STATUS_BAD_REQUEST);
        }
    }

	/**
	 * Export analysis diary metadata
	 *
	 * @return JSONResponse<Http::STATUS_OK, StructuredDiaryAnalysisExportDiary, array{}>
	 *
	 * 200: Analysis diary metadata returned
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequestHeader(name: 'x-structureddiary-job-token', description: 'Token assigned to the analysis job')]
	#[RequestHeader(name: 'x-structureddiary-job-uuid', description: 'UUID assigned by Nextcloud to the analysis job')]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/service/jobs/{uuid}/diary', requirements: ['apiVersion' => '(v1)'])]
	public function diary(string $uuid): JSONResponse {
		try {
			$job = $this->jobMapper->getJobByUuid($uuid);
			$this->jobMapper->assertPythonAccess($job, $this->request->getHeader('x-structureddiary-job-token'), $this->request->getHeader('x-structureddiary-job-uuid'), true, time());

			return new JSONResponse($this->exportService->exportDiary($job));
		} catch (Throwable $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Export analysis entries
	 *
	 * @return JSONResponse<Http::STATUS_OK, StructuredDiaryAnalysisExportEntries, array{}>
	 *
	 * 200: Analysis entries returned
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequestHeader(name: 'x-structureddiary-job-token', description: 'Token assigned to the analysis job')]
	#[RequestHeader(name: 'x-structureddiary-job-uuid', description: 'UUID assigned by Nextcloud to the analysis job')]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/service/jobs/{uuid}/entries', requirements: ['apiVersion' => '(v1)'])]
	public function entries(string $uuid, int $offset = 0): JSONResponse {
		try {
			$job = $this->jobMapper->getJobByUuid($uuid);
			$this->jobMapper->assertPythonAccess($job, $this->request->getHeader('x-structureddiary-job-token'), $this->request->getHeader('x-structureddiary-job-uuid'), true, time());

			return new JSONResponse($this->exportService->exportEntries($job, $offset));
		} catch (Throwable $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Create the complete artifact list for an analysis job
	 *
	 * @param list<array<string, mixed>>|null $artifacts
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_CREATED, list<array{id: int|null, parent_id: int|null, python_id: int|null, python_parent_id: int|null, checksum: string|null}>, array{}>
	 *
	 * 200: Existing analysis artifacts returned
	 * 201: Analysis artifacts created
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequestHeader(name: 'x-structureddiary-job-token', description: 'Token assigned to the analysis job')]
	#[RequestHeader(name: 'x-structureddiary-job-uuid', description: 'UUID assigned by Nextcloud to the analysis job')]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/service/jobs/{uuid}/artifacts/create', requirements: ['apiVersion' => '(v1)'])]
	public function createArtifacts(string $uuid, ?array $artifacts = null): DataResponse {
		$transactionOpen = false;
		try {
			$this->db->beginTransaction();
			$transactionOpen = true;
			$job = $this->jobMapper->getJobByUuid($uuid);
			$this->assertArtifactWriteAccess($job, $this->request->getHeader('x-structureddiary-job-token'), $this->request->getHeader('x-structureddiary-job-uuid'));
			if (!$this->jobMapper->claimArtifactManifest($job)) {
				$existing = $this->artifactMapper->getArtifactsForJob($job->getId());
				$this->db->commit();
				$transactionOpen = false;
				return $this->respond(array_map(static fn (AnalysisArtifact $artifact): array => $artifact->jsonPythonSerialize(), $existing));
			}

			$created = $this->createArtifactsInParentOrder($job, $this->normalizeArtifactPayloads($artifacts));
			$this->db->commit();
			$transactionOpen = false;

			return $this->respond(array_map(static fn (AnalysisArtifact $artifact): array => $artifact->jsonPythonSerialize(), $created), Http::STATUS_CREATED);
		} catch (Throwable $e) {
			if ($transactionOpen) {
				$this->db->rollBack();
			}
			return $this->respondError($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * List analysis job artifacts for the analysis service
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array{id: int|null, parent_id: int|null, python_id: int|null, python_parent_id: int|null, checksum: string|null}>, array{}>
	 *
	 * 200: Analysis artifacts returned
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequestHeader(name: 'x-structureddiary-job-token', description: 'Token assigned to the analysis job')]
	#[RequestHeader(name: 'x-structureddiary-job-uuid', description: 'UUID assigned by Nextcloud to the analysis job')]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/service/jobs/{uuid}/artifacts', requirements: ['apiVersion' => '(v1)'])]
	public function getArtifacts(string $uuid): DataResponse {
		try {
			$job = $this->jobMapper->getJobByUuid($uuid);
			$this->assertArtifactWriteAccess($job, $this->request->getHeader('x-structureddiary-job-token'), $this->request->getHeader('x-structureddiary-job-uuid'));
			$artifacts = $this->artifactMapper->getArtifactsForJob($job->getId());
			return $this->respond(array_map(static fn (AnalysisArtifact $artifact): array => $artifact->jsonPythonSerialize(), $artifacts));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Finalize a terminal analysis job after its artifacts have been uploaded
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryAnalysisJob, array{}>
	 *
	 * 200: Analysis job finalization checked
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequestHeader(name: 'x-structureddiary-job-token', description: 'Token assigned to the analysis job')]
	#[RequestHeader(name: 'x-structureddiary-job-uuid', description: 'UUID assigned by Nextcloud to the analysis job')]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/service/jobs/{uuid}/finalize', requirements: ['apiVersion' => '(v1)'])]
	public function finalize(string $uuid): DataResponse {
		try {
			$job = $this->jobMapper->getJobByUuid($uuid);
			$this->jobMapper->assertPythonAccess($job, $this->request->getHeader('x-structureddiary-job-token'), $this->request->getHeader('x-structureddiary-job-uuid'), false, time());

			return $this->respond($this->finalizationService->finalize($job));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Upload one analysis artifact file
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryAnalysisArtifact, array{}>
	 *
	 * 200: Analysis artifact uploaded
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[RequestHeader(name: 'x-structureddiary-job-token', description: 'Token assigned to the analysis job')]
	#[RequestHeader(name: 'x-structureddiary-job-uuid', description: 'UUID assigned by Nextcloud to the analysis job')]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/service/jobs/{uuid}/artifacts/upload/{artifactId}', requirements: ['apiVersion' => '(v1)'])]
	public function uploadArtifact(string $uuid, int $artifactId): DataResponse {
		try {
			$job = $this->jobMapper->getJobByUuid($uuid);
			$this->assertArtifactWriteAccess($job, $this->request->getHeader('x-structureddiary-job-token'), $this->request->getHeader('x-structureddiary-job-uuid'));
			$artifact = $this->artifactMapper->getArtifact($artifactId);
			if ($artifact->getJobId() !== $job->getId()) {
				throw new \InvalidArgumentException('Artifact does not belong to this job.');
			}

			return $this->respond($this->artifactStorage->storeArtifactContent($job, $artifact, $this->uploadedArtifactContent()));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	private function assertArtifactWriteAccess(AnalysisJob $job, string $token, ?string $jobUuid): void {
		$this->jobMapper->assertPythonAccess($job, $token, $jobUuid, false, time());
		if (!in_array($job->getStatus(), [AnalysisJob::STATUS_WORKER_UPLOAD, AnalysisJob::STATUS_JOB_COMPLETED, AnalysisJob::STATUS_JOB_FAILED], true)) {
			throw new \InvalidArgumentException('Artifacts can only be uploaded while the job is in WORKER_UPLOAD, JOB_COMPLETED, or JOB_FAILED.');
		}
	}

	private function assertNextcloudApiToken(string $token): void {
		$configuredToken = $this->configService->getNextcloudApiToken();
		if ($configuredToken === '' || !hash_equals($configuredToken, $token)) {
			throw new \InvalidArgumentException('Invalid Nextcloud API token.');
		}
	}

	private function parseChangedSince(null|int|string $changedSince): ?int {
		if ($changedSince === null || $changedSince === '') {
			return null;
		}
		if (is_int($changedSince) || ctype_digit($changedSince)) {
			return (int)$changedSince;
		}
		$timestamp = strtotime($changedSince);
		if ($timestamp === false) {
			throw new \InvalidArgumentException('changedSince must be an ISO timestamp or Unix timestamp.');
		}

		return $timestamp;
	}

	private function parseLongPollTimeout(null|int|string $longPollTimeout): int {
		if ($longPollTimeout === null || $longPollTimeout === '') {
			return 0;
		}
		if (!is_int($longPollTimeout) && !ctype_digit($longPollTimeout)) {
			throw new \InvalidArgumentException('longPollTimeout must be a non-negative integer.');
		}

		return min((int)$longPollTimeout, self::LONG_POLL_MAX_TIMEOUT);
	}

	protected function getCurrentTimestamp(): int {
		return time();
	}

	protected function waitBeforeNextLongPollCheck(): void {
		sleep(self::LONG_POLL_CHECK_INTERVAL);
	}

	/**
	 * @param list<AnalysisJob> $jobs
	 * @return list<StructuredDiaryPythonAnalysisJob>
	 */
	private function jsonPythonJobs(array $jobs): array {
		return array_map(fn (AnalysisJob $job): array => $this->jsonPythonJob($job), $jobs);
	}

	/**
	 * @return StructuredDiaryPythonAnalysisJob
	 */
	private function jsonPythonJob(AnalysisJob $job): array {
		$payload = $job->jsonSerialize();
		$payload['uuid'] = $job->getUuid();
		$payload['job_token'] = $job->getToken();

		return $payload;
	}

	/**
	 * @param list<array<string, mixed>>|null $artifacts
	 * @return list<array<string, mixed>>
	 */
	private function normalizeArtifactPayloads(?array $artifacts): array {
		$artifacts ??= [];
		$payloads = $artifacts['artifacts'] ?? $artifacts;
		if (!is_array($payloads)) {
			throw new \InvalidArgumentException('artifacts must be a list.');
		}

		$payloads = array_values(array_filter($payloads, static fn (mixed $payload): bool => is_array($payload)));
		if (count($payloads) > self::DEFAULT_MAX_JOB_ARTIFACTS) {
			throw new \InvalidArgumentException('Artifact list exceeds the maximum artifact count.');
		}
		$totalSize = array_sum(array_map(static fn (array $payload): int => max(0, (int)($payload['size'] ?? 0)), $payloads));
		if ($totalSize > self::DEFAULT_MAX_JOB_SIZE) {
			throw new \InvalidArgumentException('Artifact list exceeds the maximum total size.');
		}

		return $payloads;
	}

	/**
	 * @param list<array<string, mixed>> $payloads
	 * @return list<AnalysisArtifact>
	 */
	private function createArtifactsInParentOrder(AnalysisJob $job, array $payloads): array {
		$created = [];
		$createdByPythonId = [];
		$pending = array_values($payloads);
		while ($pending !== []) {
			$nextPending = [];
			$createdAny = false;
			foreach ($pending as $payload) {
				$pythonFileId = isset($payload['id']) ? (int)$payload['id'] : (isset($payload['python_file_id']) ? (int)$payload['python_file_id'] : null);
				$pythonParentId = isset($payload['parent_id']) ? (int)$payload['parent_id'] : (isset($payload['python_parent_id']) ? (int)$payload['python_parent_id'] : null);
				if ($pythonParentId !== null && !array_key_exists($pythonParentId, $createdByPythonId)) {
					$nextPending[] = $payload;
					continue;
				}
				$artifact = $this->artifactMapper->createFromPythonArtifact($job->getId(), $payload, $pythonParentId === null ? null : $createdByPythonId[$pythonParentId]);
				$created[] = $artifact;
				if ($pythonFileId !== null) {
					$createdByPythonId[$pythonFileId] = $artifact->getId();
				}
				$createdAny = true;
			}
			if (!$createdAny) {
				throw new \InvalidArgumentException('Artifact list contains a parent reference that was not included.');
			}
			$pending = $nextPending;
		}

		return $created;
	}

	private function uploadedArtifactContent(): string {
		$file = $this->request->getUploadedFile('file');
		if (is_array($file) && isset($file['tmp_name']) && (int)($file['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
			$content = file_get_contents((string)$file['tmp_name']);
			if ($content === false) {
				throw new \RuntimeException('Could not read uploaded artifact.');
			}
			return $content;
		}

		$content = file_get_contents('php://input');
		if ($content === false || $content === '') {
			throw new \InvalidArgumentException('Artifact upload body is required.');
		}

		return $content;
	}
}
