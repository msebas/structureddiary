<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Settings;

use OCA\StructuredDiary\AppInfo\Application;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IURLGenerator;
use OCP\Settings\IDelegatedSettings;
use OCP\Util;

class AdminSettings implements IDelegatedSettings {
	public function __construct(
		private AnalysisConfigService $configService,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, Application::APP_ID . '-settings');
		Util::addStyle(Application::APP_ID, Application::APP_ID . '-settings');

		return new TemplateResponse(Application::APP_ID, 'admin-settings', [
			...$this->configService->getSettings(),
			'save_url' => $this->urlGenerator->getAbsoluteURL('/ocs/v2.php/apps/structureddiary/api/v1/admin/analysis-settings'),
			'test_url' => $this->urlGenerator->getAbsoluteURL('/ocs/v2.php/apps/structureddiary/api/v1/admin/analysis-settings/test'),
		]);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 10;
	}

	public function getName(): ?string {
		return 'Structured Diary analysis';
	}

	public function getAuthorizedAppConfig(): array {
		return [
			Application::APP_ID => [
				'/' . preg_quote(AnalysisConfigService::KEY_SERVICE_URL, '/') . '/',
				'/' . preg_quote(AnalysisConfigService::KEY_SERVICE_SECRET, '/') . '/',
				'/' . preg_quote(AnalysisConfigService::KEY_OUTPUT_BASE_FOLDER, '/') . '/',
			],
		];
	}
}
