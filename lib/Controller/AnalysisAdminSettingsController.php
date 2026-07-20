<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use OCA\StructuredDiary\ResponseDefinitions;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCA\StructuredDiary\Service\AnalysisServiceClient;
use OCA\StructuredDiary\Settings\AdminSettings;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Throwable;

/**
 * @psalm-import-type StructuredDiaryAdminSettings from ResponseDefinitions
 * @psalm-import-type StructuredDiaryAnalysisConnectionTest from ResponseDefinitions
 */
class AnalysisAdminSettingsController extends ApiOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private AnalysisConfigService $configService,
		private AnalysisServiceClient $analysisClient,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Save analysis settings
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryAdminSettings, array{}>
	 *
	 * 200: Analysis settings saved
	 */
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/analysis-settings', requirements: ['apiVersion' => '(v1)'])]
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function save(string $serviceUrl = '', string $serviceSecret = '', string $outputBaseFolder = AnalysisConfigService::DEFAULT_OUTPUT_BASE_FOLDER): DataResponse {
		try {
			$this->configService->setServiceUrl($serviceUrl);
			if (trim($serviceSecret) !== '') {
				$this->configService->setServiceSecret($serviceSecret);
			}
			$this->configService->setOutputBaseFolder($outputBaseFolder);
			$serviceUrl = $this->configService->getServiceUrl();
			$serviceSecret = $this->configService->getServiceSecret();
			if ($serviceUrl !== '' && $serviceSecret !== '') {
				$this->analysisClient->registerNextcloudInstance(
					$serviceUrl,
					$serviceSecret,
					$this->configService->getNextcloudBaseUrl(),
					$this->configService->getOrCreateNextcloudApiToken(),
				);
			}

			return $this->respond($this->configService->getSettings());
		} catch (Throwable $e) {
			return $this->respondError($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Test analysis service connection
	 *
	 * @return DataResponse<Http::STATUS_OK, StructuredDiaryAnalysisConnectionTest, array{}>
	 *
	 * 200: Analysis service connection tested
	 */
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/analysis-settings/test', requirements: ['apiVersion' => '(v1)'])]
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function testConnection(string $serviceUrl = '', string $nextcloudApiToken = ''): DataResponse {
		try {
			$serviceUrl = trim($serviceUrl) === '' ? $this->configService->getServiceUrl() : $serviceUrl;
			$nextcloudApiToken = trim($nextcloudApiToken) === '' ? $this->configService->getNextcloudApiToken() : $nextcloudApiToken;

			$health = $this->analysisClient->healthcheck($serviceUrl, $nextcloudApiToken);

			return $this->respond([
				'ok' => ($health['ok'] ?? false) === true,
				'health' => $health,
				'settings' => $this->configService->getSettings(),
			]);
		} catch (Throwable $e) {
			return $this->respond([
				'ok' => false,
				'error' => $e->getMessage(),
				'settings' => $this->configService->getSettings(),
			], Http::STATUS_BAD_REQUEST);
		}
	}
}
