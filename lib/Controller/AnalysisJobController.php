<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Service\AnalysisArtifactArchiveService;
use OCA\StructuredDiary\ResponseDefinitions;
use OCA\StructuredDiary\Service\AnalysisJobProcessingService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use OCP\IRequest;
use Throwable;

/**
 * @psalm-import-type StructuredDiaryAnalysisJob from ResponseDefinitions
 * @psalm-import-type StructuredDiaryAnalysisArtifact from ResponseDefinitions
 */
class AnalysisJobController extends ApiOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private AnalysisJobMapper $jobMapper,
		private AnalysisArtifactMapper $artifactMapper,
		private AnalysisJobProcessingService $processingService,
		private AnalysisArtifactArchiveService $archiveService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List analysis jobs
	 *
	 * @return DataResponse<Http::STATUS_OK, list<StructuredDiaryAnalysisJob>, array{}>
	 *
	 * 200: Analysis jobs returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/jobs', requirements: ['apiVersion' => '(v1)'])]
	public function index(null|int|string $changedSince = null): DataResponse {
		try {
			return $this->respond($this->jobMapper->getJobsForUser($this->requireUser($this->userId), $this->parseChangedSince($changedSince)));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
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

	/**
	 * Create an analysis job
	 *
	 * @param list<string>|null $outputFormats
	 * @param array<string, mixed>|null $parameters
	 * @return DataResponse<Http::STATUS_CREATED, StructuredDiaryAnalysisJob, array{}>
	 *
	 * 201: Analysis job created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/jobs', requirements: ['apiVersion' => '(v1)'])]
	public function create(
		int $diaryId,
		int $fromTimestamp,
		int $untilTimestamp,
		string $title,
		string $language,
		bool $start = false,
		?array $outputFormats = null,
		?array $parameters = null,
	): DataResponse {
		try {
			$job = $this->jobMapper->createJob(
				$diaryId,
				$this->requireUser($this->userId),
				$fromTimestamp,
				$untilTimestamp,
				$title,
				$language,
				$start,
				$outputFormats,
				$parameters,
			);
			if ($job->getStatus() === AnalysisJob::STATUS_READY_QUEUE) {
				$job = $this->processingService->tryQueueJobEntity($job, true, AnalysisJobProcessingService::QUICK_QUEUE_TIMEOUT, false) ?? $job;
			}

			return $this->respond($job, Http::STATUS_CREATED);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage());
		}
	}

	/**
	 * Update an analysis job
	 *
	 * @param list<string>|null $outputFormats
	 * @param array<string, mixed>|null $parameters
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryAnalysisJob, array{}>
	 *
	 * 200: Analysis job updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/api/{apiVersion}/jobs/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function update(
		int $id,
		?int $fromTimestamp = null,
		?int $untilTimestamp = null,
		?string $title = null,
		?string $language = null,
		?array $outputFormats = null,
		?array $parameters = null,
		?string $status = null,
	): DataResponse {
		try {
			$job = $this->jobMapper->getJobForUser($id, $this->requireUser($this->userId));
			if ($status === AnalysisJob::STATUS_CANCEL_REQUESTED) {
				return $this->respond($this->processingService->requestCancel($job));
			}

			$updated = $this->jobMapper->updateDraftJob($job, $fromTimestamp, $untilTimestamp, $title, $language, $outputFormats, $parameters, $status);
			if ($updated->getStatus() === AnalysisJob::STATUS_READY_QUEUE) {
				$updated = $this->processingService->tryQueueJobEntity($updated, true, AnalysisJobProcessingService::QUICK_QUEUE_TIMEOUT) ?? $updated;
			}

			return $this->respond($updated);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete an analysis job
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryAnalysisJob, array{}>
	 *
	 * 200: Analysis job deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/jobs/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function delete(int $id): DataResponse {
		try {
			$job = $this->jobMapper->getJobForUser($id, $this->requireUser($this->userId));
			return $this->respond($this->jobMapper->deleteFinishedJob($job));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * List analysis job artifacts
	 *
	 * @return DataResponse<Http::STATUS_OK, list<StructuredDiaryAnalysisArtifact>, array{}>
	 *
	 * 200: Analysis job artifacts returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/jobs/{id}/artifacts', requirements: ['apiVersion' => '(v1)'])]
	public function artifacts(int $id): DataResponse {
		try {
			return $this->respond($this->artifactMapper->getArtifactsForUser($id, $this->requireUser($this->userId)));
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Download all analysis job artifacts as ZIP
	 *
	 * @return Response<Http::STATUS_OK, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Analysis job artifacts returned as ZIP
	 * 404: Analysis job artifacts not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/jobs/{id}/artifacts/download', requirements: ['apiVersion' => '(v1)'])]
	public function downloadArtifacts(int $id): Response|DataResponse {
		return $this->downloadArtifactSelection($id, null, true);
	}

	/**
	 * Download analysis job artifacts by type
	 *
	 * @return Response<Http::STATUS_OK, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Analysis job artifacts returned
	 * 404: Analysis job artifacts not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/jobs/{id}/artifacts/download/{artifactType}', requirements: ['apiVersion' => '(v1)', 'artifactType' => '[A-Za-z_]+'])]
	public function downloadArtifactsByType(int $id, string $artifactType): Response|DataResponse {
		return $this->downloadArtifactSelection($id, $artifactType, false);
	}

	/**
	 * Open one analysis job artifact inline
	 *
	 * @return Response<Http::STATUS_OK, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Analysis job artifact returned
	 * 404: Analysis job artifact not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/jobs/{id}/artifacts/{artifactId}/content', requirements: ['apiVersion' => '(v1)'])]
	public function artifactContent(int $id, int $artifactId): Response|DataResponse {
		try {
			$artifact = $this->archiveService->buildInlineArtifact($id, $artifactId, $this->requireUser($this->userId));
			return new DataDisplayResponse($artifact['content'], Http::STATUS_OK, [
				'Content-Disposition' => 'inline; filename="' . str_replace(['"', '\\'], ['\\"', '\\\\'], $artifact['filename']) . '"',
				'Content-Type' => $artifact['contentType'],
			]);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	private function downloadArtifactSelection(int $id, ?string $artifactType, bool $forceZip): Response|DataResponse {
		try {
			$download = $this->archiveService->buildDownload($id, $this->requireUser($this->userId), $artifactType, $forceZip);
			if ($download['path'] !== null) {
				return new StreamResponse($download['path'], Http::STATUS_OK, [
					'Content-Disposition' => 'attachment; filename="' . str_replace(['"', '\\'], ['\\"', '\\\\'], $download['filename']) . '"',
					'Content-Type' => $download['contentType'],
				]);
			}

			return new DataDownloadResponse($download['content'] ?? '', $download['filename'], $download['contentType']);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}
}
