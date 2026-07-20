<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\AppInfo;

use OCA\StructuredDiary\Cron\AnalysisJobFinalizationCron;
use OCA\StructuredDiary\Settings\AdminSection;
use OCA\StructuredDiary\Settings\AdminSettings;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BackgroundJob\IJobList;
use OCP\Settings\IManager;

class Application extends App implements IBootstrap {
	public const APP_ID = 'structureddiary';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (IJobList $jobList): void {
			$jobList->add(AnalysisJobFinalizationCron::class);
		});
		$context->injectFn(function (IManager $settingsManager): void {
			$settingsManager->registerSection(IManager::SETTINGS_ADMIN, AdminSection::class);
			$settingsManager->registerSetting(IManager::SETTINGS_ADMIN, AdminSettings::class);
		});
	}
}
