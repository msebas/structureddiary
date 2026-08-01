<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisArtifact;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Service\AnalysisArtifactArchiveService;
use OCA\StructuredDiary\ResponseDefinitions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
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
	private const LONG_POLL_MAX_TIMEOUT = 30;
	private const LONG_POLL_CHECK_INTERVAL = 1;

	public function __construct(
		string $appName,
		IRequest $request,
		private AnalysisJobMapper $jobMapper,
		private AnalysisArtifactMapper $artifactMapper,
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
	public function index(null|int|string $changedSince = null, null|int|string $longPollTimeout = null, null|int|string $wait = null): DataResponse {
		try {
			$userId = $this->requireUser($this->userId);
			$changedSinceTimestamp = $this->parseChangedSince($changedSince);
			$timeout = $this->parseLongPollTimeout($longPollTimeout ?? $wait);
			$jobs = $this->jobMapper->getJobsForUser($userId, $changedSinceTimestamp);

			if ($changedSinceTimestamp === null || $jobs !== [] || $timeout === 0) {
				return $this->respond($jobs);
			}

			$deadline = $this->getCurrentTimestamp() + $timeout;
			while ($jobs === [] && $this->getCurrentTimestamp() < $deadline) {
				$this->waitBeforeNextLongPollCheck();
				$jobs = $this->jobMapper->getJobsForUser($userId, $changedSinceTimestamp);
			}

			return $this->respond($jobs);
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
		?string $analysisType = null,
		?string $llmUrl = null,
		?string $llmHeader = null,
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
				$analysisType,
				$llmUrl,
				$llmHeader,
			);
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
				return $this->respond($this->jobMapper->requestCancel($job));
			}

			$updated = $this->jobMapper->updateDraftJob($job, $fromTimestamp, $untilTimestamp, $title, $language, $outputFormats, $parameters, $status);

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
	 * Return the settings required to copy one analysis job
	 *
	 * The LLM header is intentionally not part of the normal job representation.
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/jobs/{id}/copy-settings', requirements: ['apiVersion' => '(v1)'])]
	public function copySettings(int $id): DataResponse {
		try {
			$job = $this->jobMapper->getJobForUser($id, $this->requireUser($this->userId));
			return $this->respond([
				'data_from' => $job->getDataFrom(),
				'title' => $job->getTitle(),
				'language' => $job->getLanguage(),
				'analysis_type' => $job->getAnalysisType(),
				'output_types' => $job->getOutputTypeList(),
				'parameters' => $job->getParameters(),
				'llm_url' => $job->getLlmUrl(),
				'llm_header' => $job->getLlmHeader(),
			]);
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
	#[NoCSRFRequired]
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
	#[NoCSRFRequired]
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
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/jobs/{id}/artifacts/{artifactId}/content', requirements: ['apiVersion' => '(v1)'])]
	public function artifactContent(int $id, int $artifactId, null|int|string $download = null): Response|DataResponse {
		try {
			$artifact = $this->archiveService->buildInlineArtifact($id, $artifactId, $this->requireUser($this->userId));
			if ((string)$download === '1') {
				return new DataDownloadResponse($artifact['content'], $artifact['filename'], $artifact['contentType']);
			}

			return new DataDisplayResponse($artifact['content'], Http::STATUS_OK, [
				'Content-Disposition' => 'inline; filename="' . str_replace(['"', '\\'], ['\\"', '\\\\'], $artifact['filename']) . '"',
				'Content-Type' => $artifact['contentType'],
			]);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Serve an analysis report inside Nextcloud
	 *
	 * @return Response<Http::STATUS_OK, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/jobs/{id}/artifacts/{artifactId}/integrated-view', requirements: ['apiVersion' => '(v1)'])]
	public function integratedArtifactView(int $id, int $artifactId): Response|DataResponse {
		try {
			$artifact = $this->archiveService->buildInlineArtifact($id, $artifactId, $this->requireUser($this->userId));
			if (!str_starts_with(strtolower($artifact['contentType']), 'text/html')) {
				throw new \RuntimeException('Only HTML artifacts can be shown as an integrated report.');
			}

			return new DataDisplayResponse(
				$this->integrateReport($artifact['content'], $artifactId, $this->artifactMapper->getArtifactsForJob($id)),
				Http::STATUS_OK,
				[
					'Content-Type' => 'text/html; charset=utf-8',
					// Scripts are deliberately disabled for now. A future interactive-report
					// policy can add hash-pinned scripts here without changing the route.
					'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; font-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'",
				],
			);
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param list<AnalysisArtifact> $artifacts
	 */
	private function integrateReport(string $html, int $currentArtifactId, array $artifacts): string {
		$current = null;
		$byPath = [];
		foreach ($artifacts as $artifact) {
			if ($artifact->getId() === $currentArtifactId) {
				$current = $artifact;
			}
			$byPath[$this->normalizeReportPath($artifact->getFilePath())] = $artifact;
		}
		if ($current === null) {
			throw new \RuntimeException('Artifact not found.');
		}

		$document = new \DOMDocument();
		$previous = libxml_use_internal_errors(true);
		try {
			$document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
			$xpath = new \DOMXPath($document);
			foreach ($xpath->query('//script|//base|//iframe|//object|//embed|//form|//meta[@http-equiv]') ?: [] as $element) {
				$element->parentNode?->removeChild($element);
			}
			foreach ($xpath->query('//*') ?: [] as $element) {
				foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
					if (str_starts_with(strtolower($attribute->name), 'on') || strtolower($attribute->name) === 'srcset') {
						$element->removeAttribute($attribute->name);
					}
				}
			}
			$this->rewriteReportUrls($xpath, $current, $byPath);
			return $document->saveHTML() ?: '';
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
		}
	}

	/**
	 * @param array<string, AnalysisArtifact> $byPath
	 */
	private function rewriteReportUrls(\DOMXPath $xpath, AnalysisArtifact $current, array $byPath): void {
		foreach ([['//a[@href]', 'href', true], ['//img[@src]', 'src', false], ['//link[@href]', 'href', false]] as [$query, $attribute, $isNavigation]) {
			foreach ($xpath->query($query) ?: [] as $element) {
				$value = $element->getAttribute($attribute);
				if (str_starts_with($value, '#')) {
					continue;
				}
				$target = $byPath[$this->resolveReportPath($current->getFilePath(), $value)] ?? null;
				if ($target === null) {
					$element->removeAttribute($attribute);
					continue;
				}
				$endpoint = $isNavigation && $target->getArtifactType() === AnalysisArtifact::TYPE_HTML
					? 'integrated-view'
					: 'content';
				$element->setAttribute($attribute, '../' . $target->getId() . '/' . $endpoint);
			}
		}
	}

	private function resolveReportPath(string $currentPath, string $reference): string {
		$path = parse_url($reference, PHP_URL_PATH);
		if (!is_string($path) || $path === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $reference) === 1 || str_starts_with($reference, '//')) {
			return '';
		}
		$directory = str_contains($currentPath, '/') ? substr($currentPath, 0, (int)strrpos($currentPath, '/') + 1) : '';
		return $this->normalizeReportPath($directory . $path);
	}

	private function normalizeReportPath(string $path): string {
		$parts = [];
		foreach (explode('/', ltrim($path, '/')) as $part) {
			if ($part === '' || $part === '.') continue;
			if ($part === '..') {
				array_pop($parts);
				continue;
			}
			$parts[] = $part;
		}
		return implode('/', $parts);
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
