<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Service;

use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;

class AnalysisJobFinalizationService {
	/** @var list<string> */
	private const FINALIZABLE_STATUSES = [
		AnalysisJob::STATUS_JOB_CANCELED,
		AnalysisJob::STATUS_JOB_FAILED,
		AnalysisJob::STATUS_JOB_COMPLETED,
	];

	public function __construct(
		private AnalysisJobMapper $jobMapper,
		private AnalysisArtifactMapper $artifactMapper,
	) {
	}

	public function finalize(AnalysisJob $job): AnalysisJob {
		if (!in_array($job->getStatus(), self::FINALIZABLE_STATUSES, true) || !$this->isReady($job)) {
			return $job;
		}

		return $this->jobMapper->finishCleanup($job);
	}

	public function finalizePending(int $limit = 50): void {
		foreach ($this->jobMapper->getJobsByStatuses(self::FINALIZABLE_STATUSES, $limit) as $job) {
			$this->finalize($job);
		}
	}

	private function isReady(AnalysisJob $job): bool {
		if ($job->getStatus() === AnalysisJob::STATUS_JOB_CANCELED) {
			return true;
		}
		if (!$job->getArtifactsDownloaded()) {
			return false;
		}
		foreach ($this->artifactMapper->getArtifactsForJob($job->getId()) as $artifact) {
			if (!$artifact->getDownloaded()) {
				return false;
			}
		}

		return true;
	}
}
