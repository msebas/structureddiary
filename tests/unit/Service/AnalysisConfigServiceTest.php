<?php

declare(strict_types=1);

namespace Service;

use OCA\StructuredDiary\AppInfo\Application;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCP\IAppConfig;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

final class AnalysisConfigServiceTest extends TestCase {
	public function testGetSettingsUsesLazyAppConfigValuesAndWarnsForNonLocalHttp(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$config = $this->createMock(IConfig::class);
		$appConfig->method('getValueString')->willReturnMap([
			[Application::APP_ID, AnalysisConfigService::KEY_SERVICE_URL, '', true, 'http://example.com:8790'],
			[Application::APP_ID, AnalysisConfigService::KEY_SERVICE_SECRET, '', true, 'secret'],
			[Application::APP_ID, AnalysisConfigService::KEY_OUTPUT_BASE_FOLDER, AnalysisConfigService::DEFAULT_OUTPUT_BASE_FOLDER, true, '/StructuredDiary/Analyses'],
		]);
		$config->method('getSystemValueString')->with('overwrite.cli.url', '')->willReturn('https://cloud.example.test/');
		$service = new AnalysisConfigService($appConfig, $config);

		$this->assertSame([
			'service_url' => 'http://example.com:8790',
			'service_secret_configured' => true,
			'nextcloud_base_url' => 'https://cloud.example.test',
			'output_base_folder' => '/StructuredDiary/Analyses',
			'https_warning' => true,
		], $service->getSettings());
	}

	public function testSetServiceSecretStoresSensitiveLazyValue(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$config = $this->createMock(IConfig::class);
		$appConfig->expects($this->once())
			->method('setValueString')
			->with(Application::APP_ID, AnalysisConfigService::KEY_SERVICE_SECRET, 'secret', true, true);

		(new AnalysisConfigService($appConfig, $config))->setServiceSecret(' secret ');
	}

	public function testGetOrCreateNextcloudApiTokenReusesStoredToken(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$config = $this->createMock(IConfig::class);
		$appConfig->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, AnalysisConfigService::KEY_NEXTCLOUD_API_TOKEN, '', true)
			->willReturn('stored-token');
		$appConfig->expects($this->never())->method('setValueString');

		$this->assertSame('stored-token', (new AnalysisConfigService($appConfig, $config))->getOrCreateNextcloudApiToken());
	}

	public function testGetNextcloudApiTokenDoesNotCreateMissingToken(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$config = $this->createMock(IConfig::class);
		$appConfig->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, AnalysisConfigService::KEY_NEXTCLOUD_API_TOKEN, '', true)
			->willReturn('');
		$appConfig->expects($this->never())->method('setValueString');

		$this->assertSame('', (new AnalysisConfigService($appConfig, $config))->getNextcloudApiToken());
	}

	public function testGetOrCreateNextcloudApiTokenStoresSensitiveGeneratedToken(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$config = $this->createMock(IConfig::class);
		$appConfig->expects($this->once())->method('getValueString')->willReturn('');
		$appConfig->expects($this->once())
			->method('setValueString')
			->with(
				Application::APP_ID,
				AnalysisConfigService::KEY_NEXTCLOUD_API_TOKEN,
				$this->isType('string'),
				true,
				true,
			);

		$this->assertNotSame('', (new AnalysisConfigService($appConfig, $config))->getOrCreateNextcloudApiToken());
	}

	public function testSetOutputBaseFolderRejectsParentTraversal(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$config = $this->createMock(IConfig::class);
		$appConfig->expects($this->never())->method('setValueString');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Output base folder must not contain relative path segments.');

		(new AnalysisConfigService($appConfig, $config))->setOutputBaseFolder('/StructuredDiary/../Other');
	}

	public function testSetServiceUrlRejectsUnsupportedScheme(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$config = $this->createMock(IConfig::class);
		$appConfig->expects($this->never())->method('setValueString');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Analysis service URL must be an HTTP or HTTPS URL.');

		(new AnalysisConfigService($appConfig, $config))->setServiceUrl('file:///tmp/service');
	}
}
