<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Service;

use OCA\StructuredDiary\Db\AnalysisArtifact;
use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use Throwable;

class AnalysisJobProcessingService {
	public const QUICK_QUEUE_TIMEOUT = 1;
	public const DEFAULT_TIMEOUT = 30;
	public const SMALL_RESULT_LIMIT = 52428800;
	public const MAX_QUEUE_FAILURES = 128;

	public function __construct(
		private AnalysisJobMapper $jobMapper,
		private AnalysisArtifactMapper $artifactMapper,
		private PythonAnalysisClient $pythonClient,
		private AnalysisArtifactStorageService $artifactStorage,
	) {
	}

	public function tryQueueJob(int $jobId, bool $ignoreErrors = false, int $timeout = self::DEFAULT_TIMEOUT, bool $recordError = true): ?AnalysisJob {
		return $this->tryQueueJobEntity($this->jobMapper->getJob($jobId), $ignoreErrors, $timeout, $recordError);
	}

	public function tryQueueJobEntity(AnalysisJob $job, bool $ignoreErrors = false, int $timeout = self::DEFAULT_TIMEOUT, bool $recordError = true): ?AnalysisJob {
		if ($job->getStatus() !== AnalysisJob::STATUS_READY_QUEUE) {
			return $job;
		}
		$previousPythonJobId = $job->getPythonJobId();
		$previousStatus = $job->getStatus();
		$previousStartedAt = $job->getStartedAt();
		try {
			$pythonJobId = $this->pythonClient->enqueueJob($job, $timeout);
			$job->setPythonDeletedFails(0);
			$job->setPythonDeletedLastFailAt(null);
			return $this->jobMapper->markQueued($job, $pythonJobId);
		} catch (\Throwable $e) {
			$job->setPythonJobId($previousPythonJobId);
			$job->setStatus($previousStatus);
			$job->setStartedAt($previousStartedAt);
			if ($recordError) {
				$job->setErrorMessage($e->getMessage());
			}
			$job->setPythonDeletedFails($job->getPythonDeletedFails() + 1);
			$job->setPythonDeletedLastFailAt(time());
			$job->setUpdatedAt(time());
			if ($job->getPythonDeletedFails() > self::MAX_QUEUE_FAILURES) {
				return $this->jobMapper->failJob($job, $e->getMessage());
			}
			$this->jobMapper->update($job);
			if (!$ignoreErrors) {
				throw $e;
			}
			return $job;
		}
	}

	public function requestCancel(AnalysisJob $job): AnalysisJob {
		$updated = $this->jobMapper->requestCancel($job);
		if ($updated->getStatus() === AnalysisJob::STATUS_CANCEL_REQUESTED && $updated->getPythonJobId() !== null && $updated->getPythonJobId() !== '') {
			$this->pythonClient->cancelJob($updated);
		}

		return $updated;
	}

	public function handleTerminalPythonStatusUpdate(AnalysisJob $job): void {
		if (!in_array($job->getStatus(), [AnalysisJob::STATUS_JOB_FAILED, AnalysisJob::STATUS_JOB_COMPLETED], true)) {
			return;
		}
		try {
			$this->downloadArtifactListForJob($job);
		} catch (Throwable) {
			// SD-2 allows ignoring errors for the opportunistic result handoff.
		}
	}

	public function processTerminalJob(AnalysisJob $job): ?AnalysisJob {
		if (!in_array($job->getStatus(), [AnalysisJob::STATUS_JOB_CANCELED, AnalysisJob::STATUS_JOB_FAILED, AnalysisJob::STATUS_JOB_COMPLETED], true)) {
			return $job;
		}
		if ($job->getStatus() !== AnalysisJob::STATUS_JOB_CANCELED) {
			$this->downloadArtifactsForJob($job, false);
		}
		try {
			$this->pythonClient->deleteJob($job);
		} catch (Throwable $e) {
			$job->setPythonDeletedFails($job->getPythonDeletedFails() + 1);
			$job->setPythonDeletedLastFailAt(time());
			$job->setUpdatedAt(time());
			$this->jobMapper->update($job);
			throw $e;
		}

		return $this->jobMapper->finishCleanup($job);
	}

	public function downloadArtifactListForJob(AnalysisJob $job): void {
		if ($job->getArtifactsDownloaded()) {
			return;
		}
		try {
			$this->createArtifactsInParentOrder($job, $this->pythonClient->listArtifacts($job));
		} catch (Throwable $e) {
			$this->markJobArtifactDownloadFailed($job);
			throw $e;
		}
		$job->setArtifactsDownloaded(true);
		$job->setArtifactsDownloadFails(0);
		$job->setArtifactsDownloadLastFailAt(null);
		$job->setUpdatedAt(time());
		$this->jobMapper->update($job);
	}

	/**
	 * @param list<array<string, mixed>> $payloads
	 */
	private function createArtifactsInParentOrder(AnalysisJob $job, array $payloads): void {
		$createdByPythonId = [];
		foreach ($this->artifactMapper->getArtifactsForJob($job->getId()) as $artifact) {
			if ($artifact->getPythonFileId() !== null) {
				$createdByPythonId[$artifact->getPythonFileId()] = $artifact->getId();
			}
		}
		$pending = array_values($payloads);
		while ($pending !== []) {
			$nextPending = [];
			$createdAny = false;
			foreach ($pending as $payload) {
				$filePath = (string)($payload['file_path'] ?? $payload['filePath'] ?? $payload['file_name'] ?? $payload['fileName'] ?? 'artifact');
				$pythonFileId = isset($payload['id']) ? (int)$payload['id'] : (isset($payload['python_file_id']) ? (int)$payload['python_file_id'] : null);
				$pythonParentId = isset($payload['parent_id']) ? (int)$payload['parent_id'] : (isset($payload['python_parent_id']) ? (int)$payload['python_parent_id'] : null);
				if ($pythonParentId !== null && !array_key_exists($pythonParentId, $createdByPythonId)) {
					$nextPending[] = $payload;
					continue;
				}
				if ($this->artifactMapper->artifactPathExists($job->getId(), $filePath)) {
					$createdAny = true;
					continue;
				}
				$created = $this->artifactMapper->createFromPythonArtifact($job->getId(), $payload, $pythonParentId === null ? null : $createdByPythonId[$pythonParentId]);
				if ($pythonFileId !== null) {
					$createdByPythonId[$pythonFileId] = $created->getId();
				}
				$createdAny = true;
			}
			if (!$createdAny) {
				throw new \RuntimeException('Artifact list contains a parent reference that was not included.');
			}
			$pending = $nextPending;
		}
	}

	public function downloadArtifactsForJob(AnalysisJob $job, bool $onlySmallResults): void {
		if (!$job->getArtifactsDownloaded()) {
			$this->downloadArtifactListForJob($job);
		}
		if ($onlySmallResults) {
			return;
		}

		$downloadError = null;
		foreach ($this->artifactMapper->getArtifactsForJob($job->getId()) as $artifact) {
			if ($artifact->getDownloaded()) {
				continue;
			}
			try {
				$this->downloadOneArtifact($job, $artifact);
			} catch (Throwable $e) {
				$downloadError ??= $e;
			}
		}
		if ($this->hasOpenArtifacts($job)) {
			$this->markJobArtifactDownloadFailed($job);
			throw new \RuntimeException('Not all artifacts have been downloaded.', 0, $downloadError);
		}
		$job->setArtifactsDownloaded(true);
		$job->setArtifactsDownloadFails(0);
		$job->setArtifactsDownloadLastFailAt(null);
		$job->setUpdatedAt(time());
		$this->jobMapper->update($job);
	}

	private function downloadOneArtifact(AnalysisJob $job, AnalysisArtifact $artifact): void {
		try {
			$pythonFileId = $artifact->getPythonFileId();
			if ($pythonFileId === null) {
				throw new \RuntimeException('Artifact has no Python file id.');
			}
			$content = $this->pythonClient->downloadArtifact($job, $pythonFileId);
			$this->artifactStorage->storeArtifactContent($job, $artifact, $content);
		} catch (\Throwable $e) {
			$this->artifactMapper->markDownloadFailed($artifact);
			throw $e;
		}
	}

	private function hasOpenArtifacts(AnalysisJob $job): bool {
		foreach ($this->artifactMapper->getArtifactsForJob($job->getId()) as $artifact) {
			if (!$artifact->getDownloaded()) {
				return true;
			}
		}

		return false;
	}

	private function markJobArtifactDownloadFailed(AnalysisJob $job): void {
		$job->setArtifactsDownloadFails($job->getArtifactsDownloadFails() + 1);
		$job->setArtifactsDownloadLastFailAt(time());
		$job->setUpdatedAt(time());
		$this->jobMapper->update($job);
	}
}
