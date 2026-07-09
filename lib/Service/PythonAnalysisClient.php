<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Service;

use OCA\StructuredDiary\Db\AnalysisJob;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class PythonAnalysisClient {
	public function __construct(
		private AnalysisConfigService $config,
		private IClientService $clientService,
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function healthcheck(int $timeout = 5): array {
		$response = $this->client()->get($this->url('/v1/health'), [
			'timeout' => $timeout,
			'headers' => $this->serviceHeaders(),
		]);

		return $this->decodeJsonResponse($response->getBody());
	}

	public function enqueueJob(AnalysisJob $job, int $timeout = 5): string {
		$response = $this->client()->post($this->url('/v1/jobs'), [
			'timeout' => $timeout,
			'headers' => $this->serviceHeaders() + ['Content-Type' => 'application/json'],
			'body' => json_encode($this->jobPayload($job), JSON_THROW_ON_ERROR),
		]);
		if (!in_array($response->getStatusCode(), [200, 201], true)) {
			throw new \RuntimeException('Python service rejected job with HTTP ' . $response->getStatusCode());
		}
		$data = $this->decodeJsonResponse($response->getBody());
		$jobUuid = $this->jobUuid($job);
		if (array_key_exists('nextcloud_job_id', $data) && (string)$data['nextcloud_job_id'] !== (string)$job->getId()) {
			throw new \RuntimeException('Python service returned a different Nextcloud job id.');
		}
		$returnedJobUuid = (string)($data['uuid'] ?? $data['job_uuid'] ?? $data['external_job_id'] ?? $jobUuid);
		if ($returnedJobUuid !== $jobUuid) {
			throw new \RuntimeException('Python service returned a different job uuid.');
		}
		$pythonJobId = (string)($data['id'] ?? $data['pythonJobId'] ?? $data['python_job_id'] ?? '');
		if ($pythonJobId === '') {
			throw new \RuntimeException('Python service did not return a job id.');
		}

		return $pythonJobId;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function listArtifacts(AnalysisJob $job, int $timeout = 30): array {
		$response = $this->client()->get($this->url('/v1/jobs/' . $this->pythonJobId($job) . '/artifacts'), [
			'timeout' => $timeout,
			'headers' => $this->jobHeaders($job),
		]);
		if ($response->getStatusCode() !== 200) {
			throw new \RuntimeException('Python service artifact list failed with HTTP ' . $response->getStatusCode());
		}
		$data = $this->decodeJsonResponse($response->getBody());
		$artifacts = $data['artifacts'] ?? $data;
		if (!is_array($artifacts)) {
			throw new \RuntimeException('Python service returned an invalid artifact list.');
		}

		return array_values(array_filter($artifacts, static fn (mixed $artifact): bool => is_array($artifact)));
	}

	public function downloadArtifact(AnalysisJob $job, int $pythonArtifactId, int $timeout = 120): string {
		$response = $this->client()->get($this->url('/v1/jobs/' . $this->pythonJobId($job) . '/artifacts/' . $pythonArtifactId), [
			'timeout' => $timeout,
			'headers' => $this->jobHeaders($job),
		]);
		if ($response->getStatusCode() !== 200) {
			throw new \RuntimeException('Python service artifact download failed with HTTP ' . $response->getStatusCode());
		}

		return (string)$response->getBody();
	}

	public function cancelJob(AnalysisJob $job, int $timeout = 10): void {
		$this->client()->post($this->url('/v1/jobs/' . $this->pythonJobId($job) . '/cancel'), [
			'timeout' => $timeout,
			'headers' => $this->jobHeaders($job),
		]);
	}

	public function deleteJob(AnalysisJob $job, int $timeout = 30): void {
		$response = $this->client()->delete($this->url('/v1/jobs/' . $this->pythonJobId($job)), [
			'timeout' => $timeout,
			'headers' => $this->jobHeaders($job),
		]);
		if (!in_array($response->getStatusCode(), [200, 202, 204, 404], true)) {
			throw new \RuntimeException('Python service delete failed with HTTP ' . $response->getStatusCode());
		}
	}

	private function client(): IClient {
		$this->config->assertServiceConfigured();
		return $this->clientService->newClient();
	}

	private function url(string $path): string {
		return $this->config->getServiceUrl() . $path;
	}

	/**
	 * @return array<string, string>
	 */
	private function serviceHeaders(): array {
		return [
			'X-StructuredDiary-Service-Secret' => $this->config->getServiceSecret(),
			'Accept' => 'application/json',
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function jobHeaders(AnalysisJob $job): array {
		return [
			'X-StructuredDiary-Job-Token' => (string)$job->getToken(),
			'X-StructuredDiary-Python-Job-Id' => (string)$this->pythonJobId($job),
			'Accept' => 'application/json',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function jobPayload(AnalysisJob $job): array {
		$nextcloudBaseUrl = $this->config->getNextcloudBaseUrl();

		return [
			'id' => $job->getId(),
			'uuid' => $this->jobUuid($job),
			'diary_id' => $job->getDiaryId(),
			'created_by' => $job->getCreatedBy(),
			'created_at' => $job->getCreatedAt(),
			'data_from' => $job->getDataFrom(),
			'data_until' => $job->getDataUntil(),
			'title' => $job->getTitle(),
			'language' => $job->getLanguage(),
			'analysis_type' => $job->getAnalysisType(),
			'output_types' => $job->getOutputTypeList(),
			'parameters' => $job->getParameters(),
			'llm_url' => $job->getLlmUrl(),
			'llm_header' => $this->llmHeaderPayload($job),
			'nextcloud_base_url' => $nextcloudBaseUrl === '' ? null : $nextcloudBaseUrl,
			'token' => $job->getToken(),
		];
	}

	private function jobUuid(AnalysisJob $job): string {
		$uuid = trim($job->getUuid());
		if ($uuid === '') {
			throw new \RuntimeException('Analysis job uuid is required.');
		}

		return $uuid;
	}

	private function pythonJobId(AnalysisJob $job): int {
		$pythonJobId = (string)$job->getPythonJobId();
		if ($pythonJobId === '' || !ctype_digit($pythonJobId)) {
			throw new \RuntimeException('Python job id must be numeric.');
		}

		return (int)$pythonJobId;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function llmHeaderPayload(AnalysisJob $job): ?array {
		$header = $job->getLlmHeader();
		if ($header === null || trim($header) === '') {
			return null;
		}
		$decoded = json_decode($header, true);
		if (!is_array($decoded)) {
			throw new \RuntimeException('LLM header must be a JSON object.');
		}

		return $decoded;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decodeJsonResponse(mixed $body): array {
		$decoded = json_decode((string)$body, true);
		if (!is_array($decoded)) {
			return [];
		}

		return $decoded;
	}
}
