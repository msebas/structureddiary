<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Cron;

use OCA\StructuredDiary\Service\AnalysisJobFinalizationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class AnalysisJobFinalizationCron extends TimedJob {
	public function __construct(ITimeFactory $time, private AnalysisJobFinalizationService $finalizationService) {
		parent::__construct($time);
		$this->setInterval(300);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
		$this->setAllowParallelRuns(false);
	}

	protected function run($argument): void {
		$this->finalizationService->finalizePending();
	}
}
