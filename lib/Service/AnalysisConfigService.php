<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Service;

use OCA\StructuredDiary\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IConfig;

class AnalysisConfigService {
	public const KEY_SERVICE_URL = 'analysis.service_url';
	public const KEY_SERVICE_SECRET = 'analysis.service_secret';
	public const KEY_NEXTCLOUD_API_TOKEN = 'analysis.nextcloud_api_token';
	public const KEY_OUTPUT_BASE_FOLDER = 'analysis.output_base_folder';
	public const DEFAULT_OUTPUT_BASE_FOLDER = '/StructuredDiary/Analyses';

	public function __construct(
		private IAppConfig $appConfig,
		private IConfig $config,
	) {
	}

	public function getServiceUrl(): string {
		return rtrim($this->appConfig->getValueString(Application::APP_ID, self::KEY_SERVICE_URL, '', true), '/');
	}

	public function getServiceSecret(): string {
		return $this->appConfig->getValueString(Application::APP_ID, self::KEY_SERVICE_SECRET, '', true);
	}

	public function getOrCreateNextcloudApiToken(): string {
		$token = $this->appConfig->getValueString(Application::APP_ID, self::KEY_NEXTCLOUD_API_TOKEN, '', true);
		if ($token !== '') {
			return $token;
		}

		$token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
		$this->appConfig->setValueString(Application::APP_ID, self::KEY_NEXTCLOUD_API_TOKEN, $token, true, true);

		return $token;
	}

	public function getNextcloudApiToken(): string {
		return $this->appConfig->getValueString(Application::APP_ID, self::KEY_NEXTCLOUD_API_TOKEN, '', true);
	}

	public function getOutputBaseFolder(): string {
		$value = trim($this->appConfig->getValueString(Application::APP_ID, self::KEY_OUTPUT_BASE_FOLDER, self::DEFAULT_OUTPUT_BASE_FOLDER, true));
		if ($value === '') {
			return self::DEFAULT_OUTPUT_BASE_FOLDER;
		}

		return '/' . trim($value, '/');
	}

	public function getNextcloudBaseUrl(): string {
		return rtrim($this->config->getSystemValueString('overwrite.cli.url', ''), '/');
	}

	public function setServiceUrl(string $serviceUrl): void {
		$serviceUrl = rtrim(trim($serviceUrl), '/');
		if ($serviceUrl !== '' && !preg_match('/^https?:\/\/[^\/\s]+/i', $serviceUrl)) {
			throw new \InvalidArgumentException('Analysis service URL must be an HTTP or HTTPS URL.');
		}
		$this->appConfig->setValueString(Application::APP_ID, self::KEY_SERVICE_URL, $serviceUrl, true);
	}

	public function setServiceSecret(string $serviceSecret): void {
		$this->appConfig->setValueString(Application::APP_ID, self::KEY_SERVICE_SECRET, trim($serviceSecret), true, true);
	}

	public function setOutputBaseFolder(string $outputBaseFolder): void {
		$outputBaseFolder = $this->normalizeOutputBaseFolder($outputBaseFolder);
		$this->appConfig->setValueString(Application::APP_ID, self::KEY_OUTPUT_BASE_FOLDER, $outputBaseFolder, true);
	}

	/**
	 * @return array<string, string|bool>
	 */
	public function getSettings(): array {
		$serviceUrl = $this->getServiceUrl();

		return [
			'service_url' => $serviceUrl,
			'service_secret_configured' => $this->getServiceSecret() !== '',
			'nextcloud_base_url' => $this->getNextcloudBaseUrl(),
			'output_base_folder' => $this->getOutputBaseFolder(),
			'https_warning' => $serviceUrl !== '' && !$this->isLocalServiceUrl($serviceUrl) && !str_starts_with(strtolower($serviceUrl), 'https://'),
		];
	}

	public function assertServiceConfigured(): void {
		if ($this->getServiceUrl() === '') {
			throw new \RuntimeException('Analysis service URL is not configured.');
		}
		if ($this->getServiceSecret() === '') {
			throw new \RuntimeException('Analysis service secret is not configured.');
		}
	}

	public function isLocalServiceUrl(string $url): bool {
		$host = parse_url($url, PHP_URL_HOST);
		if (!is_string($host)) {
			return false;
		}

		return in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', 'analysis_service', 'host.docker.internal'], true);
	}

	private function normalizeOutputBaseFolder(string $outputBaseFolder): string {
		$outputBaseFolder = trim(str_replace('\\', '/', $outputBaseFolder));
		if ($outputBaseFolder === '' || $outputBaseFolder === '/') {
			return self::DEFAULT_OUTPUT_BASE_FOLDER;
		}
		$parts = array_values(array_filter(explode('/', trim($outputBaseFolder, '/')), static fn (string $part): bool => $part !== ''));
		foreach ($parts as $part) {
			if ($part === '.' || $part === '..') {
				throw new \InvalidArgumentException('Output base folder must not contain relative path segments.');
			}
		}

		return '/' . implode('/', $parts);
	}
}
