<?php

declare(strict_types=1);

namespace Settings;

use OCA\StructuredDiary\AppInfo\Application;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCA\StructuredDiary\Settings\AdminSettings;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

final class AdminSettingsTest extends TestCase {
	public function testGetFormPassesGeneratedAdminSaveUrlToTemplate(): void {
		$config = $this->createMock(AnalysisConfigService::class);
		$config->method('getSettings')->willReturn([
			'service_url' => 'http://127.0.0.1:8790',
			'service_secret_configured' => true,
			'nextcloud_base_url' => 'https://cloud.example.test',
			'output_base_folder' => '/StructuredDiary/Analyses',
			'https_warning' => false,
		]);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('getAbsoluteURL')->willReturnCallback(static fn (string $path): string => 'https://cloud.example.test' . $path);

		$response = (new AdminSettings($config, $urlGenerator))->getForm();
		$params = $response->getParams();

		$this->assertSame(Application::APP_ID, $response->getApp());
		$this->assertSame('admin-settings', $response->getTemplateName());
		$this->assertSame('https://cloud.example.test/ocs/v2.php/apps/structureddiary/api/v1/admin/analysis-settings', $params['save_url']);
		$this->assertSame('https://cloud.example.test/ocs/v2.php/apps/structureddiary/api/v1/admin/analysis-settings/test', $params['test_url']);
	}
}
