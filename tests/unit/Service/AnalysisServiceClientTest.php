<?php

declare(strict_types=1);

namespace Service;

use OCA\StructuredDiary\Service\AnalysisServiceClient;
use PHPUnit\Framework\TestCase;

final class AnalysisServiceClientTest extends TestCase {
	public function testHealthcheckUsesStreamRequestAndDecodesJson(): void {
		$client = new class extends AnalysisServiceClient {
			public string $url = '';
			public string $method = '';
			public ?string $body = null;
			/** @var list<string> */
			public array $headers = [];

			protected function request(string $url, array $headers, int $timeout, string $method = 'GET', ?string $body = null): string {
				$this->url = $url;
				$this->headers = $headers;
				$this->method = $method;
				$this->body = $body;

				return '{"ok":true}';
			}
		};

		$this->assertSame(['ok' => true], $client->healthcheck('http://analysis.test/', 'secret'));
		$this->assertSame('http://analysis.test/v1/health', $client->url);
		$this->assertContains('X-StructuredDiary-Nextcloud-API-Token: secret', $client->headers);
		$this->assertSame('GET', $client->method);
		$this->assertNull($client->body);
	}

	public function testRegisterNextcloudInstanceUsesDocumentedPayload(): void {
		$client = new class extends AnalysisServiceClient {
			public string $url = '';
			public string $method = '';
			public ?string $body = null;
			/** @var list<string> */
			public array $headers = [];

			protected function request(string $url, array $headers, int $timeout, string $method = 'GET', ?string $body = null): string {
				$this->url = $url;
				$this->headers = $headers;
				$this->method = $method;
				$this->body = $body;

				return '{"id":1,"base_url":"https://cloud.example","enabled":true,"last_seen_at":null,"last_poll_at":null,"last_error":null}';
			}
		};

		$result = $client->registerNextcloudInstance('http://analysis.test/', 'service-secret', 'https://cloud.example/', 'nextcloud-token');

		$this->assertSame(1, $result['id']);
		$this->assertSame('http://analysis.test/v1/nextcloud-instances', $client->url);
		$this->assertSame('POST', $client->method);
		$this->assertContains('Content-Type: application/json', $client->headers);
		$this->assertContains('X-StructuredDiary-Service-Secret: service-secret', $client->headers);
		$this->assertSame([
			'base_url' => 'https://cloud.example',
			'api_token' => 'nextcloud-token',
			'enabled' => true,
		], json_decode((string)$client->body, true));
	}

	public function testHealthcheckRequiresConfiguredUrlAndSecret(): void {
		$client = new AnalysisServiceClient();

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Analysis service URL is not configured.');

		$client->healthcheck('', 'secret');
	}
}
