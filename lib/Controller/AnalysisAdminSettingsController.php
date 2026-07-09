<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Controller;

use OCA\StructuredDiary\ResponseDefinitions;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCA\StructuredDiary\Service\PythonAnalysisClient;
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
		private PythonAnalysisClient $pythonClient,
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
	public function testConnection(): DataResponse {
		try {
			return $this->respond([
				'ok' => true,
				'health' => $this->pythonClient->healthcheck(),
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
