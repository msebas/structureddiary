<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Cron;

use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Service\AnalysisJobProcessingService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Throwable;

class AnalysisJobCron extends TimedJob {
	private const MAX_RUNTIME = 420;

	public function __construct(
		ITimeFactory $time,
		private AnalysisJobMapper $jobMapper,
		private AnalysisJobProcessingService $processingService,
	) {
		parent::__construct($time);
		$this->setInterval(300);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
		$this->setAllowParallelRuns(false);
	}

	protected function run($argument): void {
		$startedAt = time();
		foreach ($this->jobMapper->getJobsByStatuses([AnalysisJob::STATUS_READY_QUEUE], 50) as $job) {
			try {
				$this->processingService->tryQueueJobEntity($job, true, AnalysisJobProcessingService::DEFAULT_TIMEOUT);
			} catch (Throwable) {
				// Keep the job retryable and continue with later jobs.
			}
			if ($this->shouldStop($startedAt)) {
				return;
			}
		}

		foreach ($this->jobMapper->getJobsByStatuses([
			AnalysisJob::STATUS_JOB_CANCELED,
			AnalysisJob::STATUS_JOB_FAILED,
			AnalysisJob::STATUS_JOB_COMPLETED,
		], 50) as $job) {
			try {
				$this->processingService->processTerminalJob($job);
			} catch (Throwable) {
				// Keep the job in its retryable terminal-Python status for the next cron run.
			}
			if ($this->shouldStop($startedAt)) {
				return;
			}
		}
	}

	private function shouldStop(int $startedAt): bool {
		return time() - $startedAt > self::MAX_RUNTIME;
	}
}
