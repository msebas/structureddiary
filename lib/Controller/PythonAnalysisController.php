<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\ResponseDefinitions;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCA\StructuredDiary\Service\AnalysisExportService;
use OCA\StructuredDiary\Service\AnalysisJobProcessingService;
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
use Throwable;

/**
 * @psalm-import-type StructuredDiaryAnalysisJob from ResponseDefinitions
 * @psalm-import-type StructuredDiaryHealthcheck from ResponseDefinitions
 * @psalm-import-type StructuredDiaryAnalysisExportDiary from ResponseDefinitions
 * @psalm-import-type StructuredDiaryAnalysisExportEntries from ResponseDefinitions
 */
#[OpenAPI(scope: 'python_analysis')]
class PythonAnalysisController extends ApiOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private AnalysisJobMapper $jobMapper,
		private AnalysisExportService $exportService,
		private AnalysisConfigService $configService,
		private AnalysisJobProcessingService $processingService,
	) {
		parent::__construct($appName, $request);
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
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/jobs/healthcheck', requirements: ['apiVersion' => '(v1)'])]
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
	#[ApiRoute(verb: 'PATCH', url: '/api/{apiVersion}/jobs/{id}/status', requirements: ['apiVersion' => '(v1)'])]
	public function updateStatus(
		string $id,
		?string $status = null,
		?float $progress = null,
		?string $statusMessage = null,
		?string $errorMessage = null,
	): DataResponse {
		try {
			$job = $this->jobMapper->getJobByUuid($id);
			$this->jobMapper->assertPythonAccess($job, $this->request->getHeader('x-structureddiary-job-token'), $this->request->getHeader('x-structureddiary-job-uuid'), false, time());
			$updated = $this->jobMapper->updateFromPython($job, $status, $progress, $statusMessage, $errorMessage);
			$this->processingService->handleTerminalPythonStatusUpdate($updated);

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
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/jobs/{id}/diary', requirements: ['apiVersion' => '(v1)'])]
	public function diary(string $id): JSONResponse {
		try {
			$job = $this->jobMapper->getJobByUuid($id);
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
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/jobs/{id}/entries', requirements: ['apiVersion' => '(v1)'])]
	public function entries(string $id, int $offset = 0): JSONResponse {
		try {
			$job = $this->jobMapper->getJobByUuid($id);
			$this->jobMapper->assertPythonAccess($job, $this->request->getHeader('x-structureddiary-job-token'), $this->request->getHeader('x-structureddiary-job-uuid'), true, time());

			return new JSONResponse($this->exportService->exportEntries($job, $offset));
		} catch (Throwable $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
}
