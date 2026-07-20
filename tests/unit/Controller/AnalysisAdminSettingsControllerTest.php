<?php

declare(strict_types=1);

namespace Controller;

use OCA\StructuredDiary\AppInfo\Application;
use OCA\StructuredDiary\Controller\AnalysisAdminSettingsController;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCA\StructuredDiary\Service\AnalysisServiceClient;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class AnalysisAdminSettingsControllerTest extends TestCase {
	public function testSaveRegistersNextcloudInstanceWhenServiceIsConfigured(): void {
		$config = $this->createMock(AnalysisConfigService::class);
		$config->expects($this->once())->method('setServiceUrl')->with('http://analysis.test');
		$config->expects($this->once())->method('setServiceSecret')->with('service-secret');
		$config->expects($this->once())->method('setOutputBaseFolder')->with('/StructuredDiary/Analyses');
		$config->expects($this->once())->method('getServiceUrl')->willReturn('http://analysis.test');
		$config->expects($this->once())->method('getServiceSecret')->willReturn('service-secret');
		$config->expects($this->once())->method('getNextcloudBaseUrl')->willReturn('https://cloud.example');
		$config->expects($this->once())->method('getOrCreateNextcloudApiToken')->willReturn('nextcloud-token');
		$config->method('getSettings')->willReturn([
			'service_url' => 'http://analysis.test',
			'service_secret_configured' => true,
			'nextcloud_base_url' => 'https://cloud.example',
			'output_base_folder' => '/StructuredDiary/Analyses',
			'https_warning' => false,
		]);
		$client = $this->createMock(AnalysisServiceClient::class);
		$client->expects($this->once())
			->method('registerNextcloudInstance')
			->with('http://analysis.test', 'service-secret', 'https://cloud.example', 'nextcloud-token')
			->willReturn(['id' => 1]);

		$response = (new AnalysisAdminSettingsController(Application::APP_ID, $this->createMock(IRequest::class), $config, $client))
			->save('http://analysis.test', 'service-secret', '/StructuredDiary/Analyses');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testConnectionUsesSubmittedServiceValues(): void {
		$config = $this->createMock(AnalysisConfigService::class);
		$config->expects($this->never())->method('getServiceUrl');
		$config->expects($this->never())->method('getServiceSecret');
		$config->method('getSettings')->willReturn([
			'service_url' => 'http://analysis.test',
			'service_secret_configured' => true,
			'nextcloud_base_url' => 'https://cloud.example',
			'output_base_folder' => '/StructuredDiary/Analyses',
			'https_warning' => false,
		]);
		$client = $this->createMock(AnalysisServiceClient::class);
		$client->expects($this->once())
			->method('healthcheck')
			->with('http://analysis.test', 'secret')
			->willReturn(['ok' => true]);

		$response = (new AnalysisAdminSettingsController(Application::APP_ID, $this->createMock(IRequest::class), $config, $client))
			->testConnection('http://analysis.test', 'secret');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['ok' => true], $response->getData()['health']);
	}

	public function testConnectionFallsBackToStoredNextcloudApiTokenWhenSubmittedTokenIsEmpty(): void {
		$config = $this->createMock(AnalysisConfigService::class);
		$config->expects($this->never())->method('getServiceUrl');
		$config->expects($this->once())->method('getNextcloudApiToken')->willReturn('stored-nextcloud-token');
		$config->method('getSettings')->willReturn([
			'service_url' => 'http://analysis.test',
			'service_secret_configured' => true,
			'nextcloud_base_url' => 'https://cloud.example',
			'output_base_folder' => '/StructuredDiary/Analyses',
			'https_warning' => false,
		]);
		$client = $this->createMock(AnalysisServiceClient::class);
		$client->expects($this->once())
			->method('healthcheck')
			->with('http://analysis.test', 'stored-nextcloud-token')
			->willReturn(['ok' => true]);

		$response = (new AnalysisAdminSettingsController(Application::APP_ID, $this->createMock(IRequest::class), $config, $client))
			->testConnection('http://analysis.test', '');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testConnectionReportsAnUnhealthyAnalysisService(): void {
		$config = $this->createMock(AnalysisConfigService::class);
		$config->method('getSettings')->willReturn([
			'service_url' => 'http://analysis.test',
			'service_secret_configured' => true,
			'nextcloud_base_url' => 'https://cloud.example',
			'output_base_folder' => '/StructuredDiary/Analyses',
			'https_warning' => false,
		]);
		$client = $this->createMock(AnalysisServiceClient::class);
		$client->expects($this->once())
			->method('healthcheck')
			->with('http://analysis.test', 'nextcloud-token')
			->willReturn(['ok' => false, 'coordinator' => false]);

		$response = (new AnalysisAdminSettingsController(Application::APP_ID, $this->createMock(IRequest::class), $config, $client))
			->testConnection('http://analysis.test', 'nextcloud-token');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertFalse($response->getData()['ok']);
		$this->assertSame(['ok' => false, 'coordinator' => false], $response->getData()['health']);
	}
}
