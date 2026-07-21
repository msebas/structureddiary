<?php

declare(strict_types=1);

namespace Service;

use OCA\StructuredDiary\Db\AnalysisArtifact;
use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Service\AnalysisJobFinalizationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AnalysisJobFinalizationServiceTest extends TestCase {
	private AnalysisJobMapper&MockObject $jobMapper;
	private AnalysisArtifactMapper&MockObject $artifactMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->jobMapper = $this->createMock(AnalysisJobMapper::class);
		$this->artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
	}

	/**
	 * @dataProvider finalizableStatuses
	 */
	public function testFinalizeTransitionsReadyTerminalJob(string $status, bool $hasArtifacts): void {
		$job = $this->job($status);
		$job->setArtifactsDownloaded($hasArtifacts);
		$artifact = $this->artifact(true);
		$this->artifactMapper->expects($hasArtifacts ? $this->once() : $this->never())
			->method('getArtifactsForJob')
			->with(42)
			->willReturn([$artifact]);
		$this->jobMapper->expects($this->once())->method('finishCleanup')->with($job)->willReturn($job);

		$result = $this->service()->finalize($job);

		$this->assertSame($job, $result);
	}

	/**
	 * @return list<array{string, bool}>
	 */
	public static function finalizableStatuses(): array {
		return [
			[AnalysisJob::STATUS_JOB_CANCELED, false],
			[AnalysisJob::STATUS_JOB_FAILED, true],
			[AnalysisJob::STATUS_JOB_COMPLETED, true],
		];
	}

	public function testFinalizeKeepsCompletedJobRetryableUntilArtifactsAreUploaded(): void {
		$job = $this->job(AnalysisJob::STATUS_JOB_COMPLETED);
		$job->setArtifactsDownloaded(true);
		$artifact = $this->artifact(false);
		$this->artifactMapper->expects($this->once())->method('getArtifactsForJob')->with(42)->willReturn([$artifact]);
		$this->jobMapper->expects($this->never())->method('finishCleanup');

		$this->assertSame($job, $this->service()->finalize($job));
	}

	/**
	 * @dataProvider nonFinalizableStatuses
	 */
	public function testFinalizeDoesNotInspectOrChangeNonTerminalPythonState(string $status): void {
		$job = $this->job($status);
		$this->jobMapper->expects($this->never())->method('finishCleanup');
		$this->artifactMapper->expects($this->never())->method('getArtifactsForJob');

		$this->assertSame($job, $this->service()->finalize($job));
	}

	/**
	 * @return list<array{string}>
	 */
	public static function nonFinalizableStatuses(): array {
		return array_map(static fn (string $status): array => [$status], [
			AnalysisJob::STATUS_DRAFT,
			AnalysisJob::STATUS_SUBMITTED,
			AnalysisJob::STATUS_READY_QUEUE,
			AnalysisJob::STATUS_QUEUED,
			AnalysisJob::STATUS_LOAD_DATA,
			AnalysisJob::STATUS_RUNNING,
			AnalysisJob::STATUS_RESTART,
			AnalysisJob::STATUS_WORKER_UPLOAD,
			AnalysisJob::STATUS_CANCEL_REQUESTED,
			AnalysisJob::STATUS_CANCELED,
			AnalysisJob::STATUS_FAILED,
			AnalysisJob::STATUS_COMPLETED,
		]);
	}

	public function testFinalizeKeepsFailedJobRetryableUntilManifestIsConfirmed(): void {
		$job = $this->job(AnalysisJob::STATUS_JOB_FAILED);
		$job->setArtifactsDownloaded(false);
		$this->jobMapper->expects($this->never())->method('finishCleanup');
		$this->artifactMapper->expects($this->never())->method('getArtifactsForJob');

		$this->assertSame($job, $this->service()->finalize($job));
	}

	public function testFinalizeAllowsTerminalJobWithConfirmedEmptyArtifactList(): void {
		$job = $this->job(AnalysisJob::STATUS_JOB_COMPLETED);
		$job->setArtifactsDownloaded(true);
		$this->artifactMapper->expects($this->once())->method('getArtifactsForJob')->with(42)->willReturn([]);
		$this->jobMapper->expects($this->once())->method('finishCleanup')->with($job)->willReturn($job);

		$this->assertSame($job, $this->service()->finalize($job));
	}

	public function testFinalizePendingChecksAllTerminalJobs(): void {
		$canceled = $this->job(AnalysisJob::STATUS_JOB_CANCELED);
		$completed = $this->job(AnalysisJob::STATUS_JOB_COMPLETED);
		$completed->setArtifactsDownloaded(false);
		$this->jobMapper->expects($this->once())
			->method('getJobsByStatuses')
			->with([
				AnalysisJob::STATUS_JOB_CANCELED,
				AnalysisJob::STATUS_JOB_FAILED,
				AnalysisJob::STATUS_JOB_COMPLETED,
			], 50)
			->willReturn([$canceled, $completed]);
		$this->jobMapper->expects($this->once())->method('finishCleanup')->with($canceled)->willReturn($canceled);
		$this->artifactMapper->expects($this->never())->method('getArtifactsForJob');

		$this->service()->finalizePending();
	}

	public function testFinalizePendingPassesExplicitLimitToMapper(): void {
		$this->jobMapper->expects($this->once())
			->method('getJobsByStatuses')
			->with([
				AnalysisJob::STATUS_JOB_CANCELED,
				AnalysisJob::STATUS_JOB_FAILED,
				AnalysisJob::STATUS_JOB_COMPLETED,
			], 3)
			->willReturn([]);

		$this->service()->finalizePending(3);
	}

	private function service(): AnalysisJobFinalizationService {
		return new AnalysisJobFinalizationService($this->jobMapper, $this->artifactMapper);
	}

	private function job(string $status): AnalysisJob {
		$job = new AnalysisJob();
		$job->setId(42);
		$job->setStatus($status);
		$job->setArtifactsDownloaded(false);

		return $job;
	}

	private function artifact(bool $downloaded): AnalysisArtifact {
		$artifact = new AnalysisArtifact();
		$artifact->setDownloaded($downloaded);

		return $artifact;
	}
}
