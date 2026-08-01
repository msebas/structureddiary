<?php

declare(strict_types=1);

namespace OCA\Tests\StructuredDiary\Integration\Service;

use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Service\AnalysisJobFinalizationService;
use OCA\Tests\StructuredDiary\Integration\TestUtil\IntegrationTestParentClass;

final class AnalysisJobFinalizationServiceIntegrationTest extends IntegrationTestParentClass {
	private AnalysisJobMapper $jobMapper;
	private AnalysisArtifactMapper $artifactMapper;
	private DiaryMapper $diaryMapper;
	private AnalysisJobFinalizationService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->jobMapper = self::$container->get(AnalysisJobMapper::class);
		$this->artifactMapper = self::$container->get(AnalysisArtifactMapper::class);
		$this->diaryMapper = self::$container->get(DiaryMapper::class);
		$this->service = new AnalysisJobFinalizationService($this->jobMapper, $this->artifactMapper);
	}

	public function testCompletedJobWaitsForManifestAndEveryArtifact(): void {
		$job = $this->terminalJob(AnalysisJob::STATUS_JOB_COMPLETED);
		$artifact = $this->artifactMapper->createArtifact($job->getId(), 'HTML', 'text/html', 'report.html', 'report.html');

		$this->assertSame(AnalysisJob::STATUS_JOB_COMPLETED, $this->service->finalize($job)->getStatus());

		$job->setArtifactsDownloaded(true);
		$job = $this->jobMapper->update($job);
		$this->assertSame(AnalysisJob::STATUS_JOB_COMPLETED, $this->service->finalize($job)->getStatus());

		$this->artifactMapper->markDownloaded($artifact, 101, 12, 'sha256:report');
		$finalized = $this->service->finalize($this->jobMapper->getJob($job->getId()));

		$this->assertSame(AnalysisJob::STATUS_COMPLETED, $finalized->getStatus());
		$this->assertFalse($finalized->getPythonDeleted());
		$this->assertNotNull($finalized->getFinishedAt());
	}

	public function testFailedJobFinalizesWithoutArtifactsAfterManifestIsConfirmed(): void {
		$job = $this->terminalJob(AnalysisJob::STATUS_JOB_FAILED);
		$job->setArtifactsDownloaded(true);
		$job = $this->jobMapper->update($job);

		$finalized = $this->service->finalize($job);

		$this->assertSame(AnalysisJob::STATUS_FAILED, $finalized->getStatus());
		$this->assertFalse($finalized->getPythonDeleted());
	}

	public function testCanceledJobFinalizesWithoutWaitingForArtifacts(): void {
		$job = $this->terminalJob(AnalysisJob::STATUS_JOB_CANCELED);
		$this->artifactMapper->createArtifact($job->getId(), 'LOG', 'text/plain', 'worker.log', 'worker.log');

		$finalized = $this->service->finalize($job);

		$this->assertSame(AnalysisJob::STATUS_CANCELED, $finalized->getStatus());
		$this->assertFalse($finalized->getPythonDeleted());
	}

	public function testFinalizePendingHonorsLimitAndLeavesIneligibleJobsUntouched(): void {
		$canceled = $this->terminalJob(AnalysisJob::STATUS_JOB_CANCELED);
		$completed = $this->terminalJob(AnalysisJob::STATUS_JOB_COMPLETED);
		$completed->setArtifactsDownloaded(false);
		$this->jobMapper->update($completed);

		$this->service->finalizePending(1);

		$this->assertSame(AnalysisJob::STATUS_CANCELED, $this->jobMapper->getJob($canceled->getId())->getStatus());
		$this->assertSame(AnalysisJob::STATUS_JOB_COMPLETED, $this->jobMapper->getJob($completed->getId())->getStatus());
	}

	public function testStaleFinalizerCannotOverwriteConcurrentCancellation(): void {
		$job = $this->terminalJob(AnalysisJob::STATUS_JOB_COMPLETED);
		$job->setArtifactsDownloaded(true);
		$job = $this->jobMapper->update($job);
		$staleCompletedJob = $this->jobMapper->getJob($job->getId());
		$canceled = $this->jobMapper->requestCancel($this->jobMapper->getJob($job->getId()));

		$result = $this->service->finalize($staleCompletedJob);

		$this->assertSame(AnalysisJob::STATUS_JOB_CANCELED, $canceled->getStatus());
		$this->assertSame(AnalysisJob::STATUS_JOB_CANCELED, $result->getStatus());
		$this->assertSame(AnalysisJob::STATUS_JOB_CANCELED, $this->jobMapper->getJob($job->getId())->getStatus());
	}

	public function testEndpointAndCronFinalizationAttemptsAreIdempotent(): void {
		$job = $this->terminalJob(AnalysisJob::STATUS_JOB_COMPLETED);
		$job->setArtifactsDownloaded(true);
		$this->jobMapper->update($job);
		$endpointSnapshot = $this->jobMapper->getJob($job->getId());
		$cronSnapshot = $this->jobMapper->getJob($job->getId());

		$this->assertSame(AnalysisJob::STATUS_COMPLETED, $this->service->finalize($endpointSnapshot)->getStatus());
		$this->assertSame(AnalysisJob::STATUS_COMPLETED, $this->service->finalize($cronSnapshot)->getStatus());
		$this->assertSame(AnalysisJob::STATUS_COMPLETED, $this->jobMapper->getJob($job->getId())->getStatus());
	}

	private function terminalJob(string $status): AnalysisJob {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', '');
		$job = $this->jobMapper->createJob(
			$diary->getId(),
			'alice',
			100,
			200,
			'Report ' . $status,
			'en',
			true,
			null,
			['includeTextAnalysis' => false],
		);
		if ($status === AnalysisJob::STATUS_JOB_CANCELED) {
			return $this->jobMapper->updateFromPython($this->jobMapper->requestCancel($job), $status, null, 'Canceled', null);
		}

		return $this->jobMapper->updateFromPython($job, $status, null, 'Terminal state', $status === AnalysisJob::STATUS_JOB_FAILED ? 'Worker failed' : null);
	}
}
