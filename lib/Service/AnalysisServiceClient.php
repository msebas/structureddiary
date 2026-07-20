<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Service;

class AnalysisServiceClient {
	/**
	 * @return array<string, mixed>
	 */
	public function healthcheck(string $serviceUrl, string $nextcloudApiToken, int $timeout = 5): array {
		$serviceUrl = rtrim(trim($serviceUrl), '/');
		$nextcloudApiToken = trim($nextcloudApiToken);
		if ($serviceUrl === '') {
			throw new \RuntimeException('Analysis service URL is not configured.');
		}
		if ($nextcloudApiToken === '') {
			throw new \RuntimeException('Analysis service secret is not configured.');
		}

		$response = $this->request($serviceUrl . '/v1/health', [
			'Accept: application/json',
			'X-StructuredDiary-Nextcloud-API-Token: ' . $nextcloudApiToken,
		], $timeout);
		$decoded = json_decode($response, true);

		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function registerNextcloudInstance(string $serviceUrl, string $serviceSecret, string $baseUrl, string $apiToken, int $timeout = 10): array {
		$serviceUrl = rtrim(trim($serviceUrl), '/');
		$serviceSecret = trim($serviceSecret);
		$baseUrl = rtrim(trim($baseUrl), '/');
		$apiToken = trim($apiToken);
		if ($serviceUrl === '') {
			throw new \RuntimeException('Analysis service URL is not configured.');
		}
		if ($serviceSecret === '') {
			throw new \RuntimeException('Analysis service secret is not configured.');
		}
		if ($baseUrl === '') {
			throw new \RuntimeException('Nextcloud base URL is not configured.');
		}
		if ($apiToken === '') {
			throw new \RuntimeException('Nextcloud API token is not configured.');
		}

		$response = $this->request($serviceUrl . '/v1/nextcloud-instances', [
			'Accept: application/json',
			'Content-Type: application/json',
			'X-StructuredDiary-Service-Secret: ' . $serviceSecret,
		], $timeout, 'POST', json_encode([
			'base_url' => $baseUrl,
			'api_token' => $apiToken,
			'enabled' => true,
		], JSON_THROW_ON_ERROR));
		$decoded = json_decode($response, true);

		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * @param list<string> $headers
	 */
	protected function request(string $url, array $headers, int $timeout, string $method = 'GET', ?string $body = null): string {
		$context = stream_context_create([
			'http' => [
				'method' => $method,
				'header' => implode("\r\n", $headers),
				'timeout' => $timeout,
				'ignore_errors' => true,
				'content' => $body,
			],
		]);
		$response = @file_get_contents($url, false, $context);
		if ($response === false) {
			throw new \RuntimeException('Analysis service request failed.');
		}
		$statusCode = $this->statusCode($http_response_header ?? []);
		if ($statusCode < 200 || $statusCode >= 300) {
			throw new \RuntimeException('Analysis service request failed with HTTP ' . $statusCode . '.');
		}

		return $response;
	}

	/**
	 * @param list<string> $headers
	 */
	private function statusCode(array $headers): int {
		$statusLine = $headers[0] ?? '';
		if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $statusLine, $matches) !== 1) {
			return 0;
		}

		return (int)$matches[1];
	}
}
