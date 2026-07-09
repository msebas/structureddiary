<?php

declare(strict_types=1);

namespace OCA\Tests\StructuredDiary\Integration\Db;

use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCA\StructuredDiary\Db\DiaryShareMapper;
use OCA\Tests\StructuredDiary\Integration\TestUtil\IntegrationTestParentClass;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * @runTestsInSeparateProcesses
 */
final class AnalysisJobMapperIntegrationTest extends IntegrationTestParentClass {
	private DiaryMapper $diaryMapper;
	private DiaryShareMapper $shareMapper;
	private AnalysisJobMapper $jobMapper;
	private AnalysisArtifactMapper $artifactMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->diaryMapper = self::$container->get(DiaryMapper::class);
		$this->shareMapper = self::$container->get(DiaryShareMapper::class);
		$this->jobMapper = self::$container->get(AnalysisJobMapper::class);
		$this->artifactMapper = self::$container->get(AnalysisArtifactMapper::class);
	}

	public function testCreateUpdateCancelAndDeleteJobPersistValues(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$job = $this->jobMapper->createJob($diary->getId(), 'alice', 1000, 2000, 'April Report', 'de-DE', false, ['json', 'html'], [
			'includeTextAnalysis' => false,
			'shifting_median_width' => 9,
			'plot_std_error' => true,
		]);

		$this->assertGreaterThan(0, $job->getId());
		$this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $job->getUuid());
		$this->assertSame(AnalysisJob::STATUS_DRAFT, $job->getStatus());
		$this->assertSame('/StructuredDiary/Analyses/Diary-' . $diary->getId() . '/April-Report-' . $job->getId(), $job->getStoragePath());
		$this->assertNotNull($job->getStorageUrl());
		$this->assertStringContainsString('/apps/files/', $job->getStorageUrl());
		$this->assertStringContainsString('dir=', $job->getStorageUrl());
		$this->assertSame(['JSON', 'HTML'], $job->getOutputTypeList());
		$this->assertSame([
			'includeTextAnalysis' => false,
			'shifting_median_width' => 9,
			'plot_std_error' => true,
		], $job->getParameters());
		$this->assertNotNull($job->getToken());
		$this->assertSame($job->getId(), $this->jobMapper->getJobByUuid($job->getUuid())->getId());

		$started = $this->jobMapper->updateDraftJob($job, null, null, null, null, null, null, AnalysisJob::STATUS_READY_QUEUE);
		$this->assertSame(AnalysisJob::STATUS_READY_QUEUE, $started->getStatus());

		$queued = $this->jobMapper->markQueued($started, '123');
		$this->assertSame(AnalysisJob::STATUS_QUEUED, $queued->getStatus());
		$this->assertSame('123', $queued->getPythonJobId());

		$canceled = $this->jobMapper->requestCancel($queued);
		$this->assertSame(AnalysisJob::STATUS_CANCEL_REQUESTED, $canceled->getStatus());

		$jobCanceled = $this->jobMapper->updateFromPython($canceled, AnalysisJob::STATUS_JOB_CANCELED, null, 'canceled', null);
		$jobCanceled->setStatus(AnalysisJob::STATUS_CANCELED);
		$terminal = $this->jobMapper->update($jobCanceled);
		$deleted = $this->jobMapper->deleteFinishedJob($terminal);
		$this->assertSame($terminal->getId(), $deleted->getId());

		$this->expectException(DoesNotExistException::class);
		$this->jobMapper->getJob($terminal->getId());
	}

	public function testDiaryDeleteCascadesAnalysisJobsAndArtifacts(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$job = $this->jobMapper->createJob($diary->getId(), 'alice', 1000, 2000, 'Report', 'en', false);
		$artifact = $this->artifactMapper->createArtifact($job->getId(), 'json', 'application/json', 'analysis.json', 'analysis.json');

		$this->diaryMapper->deleteDiary($diary->getId(), 'alice');

		try {
			$this->jobMapper->getJob($job->getId());
			$this->fail('Job should have been deleted by cascade.');
		} catch (DoesNotExistException) {
			$this->addToAssertionCount(1);
		}
		try {
			$this->artifactMapper->getArtifact($artifact->getId());
			$this->fail('Artifact should have been deleted by cascade.');
		} catch (DoesNotExistException) {
			$this->addToAssertionCount(1);
		}
	}

	public function testSameArtifactPathCanExistForDifferentJobs(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$firstJob = $this->jobMapper->createJob($diary->getId(), 'alice', 1000, 2000, 'First Report', 'en', false);
		$secondJob = $this->jobMapper->createJob($diary->getId(), 'alice', 1000, 2000, 'Second Report', 'en', false);

		$firstArtifact = $this->artifactMapper->createArtifact($firstJob->getId(), 'json', 'application/json', 'analysis.json', 'analysis.json');
		$secondArtifact = $this->artifactMapper->createArtifact($secondJob->getId(), 'json', 'application/json', 'analysis.json', 'analysis.json');

		$this->assertNotSame($firstArtifact->getId(), $secondArtifact->getId());
		$this->assertSame('analysis.json', $secondArtifact->getFilePath());
	}

	public function testCreateJobRequiresAnalyzePermission(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');

		$this->expectException(DoesNotExistException::class);
		$this->jobMapper->createJob($diary->getId(), 'bob', 1000, 2000, 'Report', 'en', false);
	}

	public function testReadOnlyShareCannotSeeAnalysisJobOrArtifacts(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$this->shareMapper->upsertShare($diary->getId(), 'bob', DiaryPermissions::READ);
		$job = $this->jobMapper->createJob($diary->getId(), 'alice', 1000, 2000, 'Report', 'en', false);
		$this->artifactMapper->createArtifact($job->getId(), 'json', 'application/json', 'analysis.json', 'analysis.json');

		$this->assertSame([], $this->jobMapper->getJobsForUser('bob'));

		try {
			$this->jobMapper->getJobForUser($job->getId(), 'bob');
			$this->fail('Read-only share should not access analysis job.');
		} catch (DoesNotExistException) {
			$this->addToAssertionCount(1);
		}
		try {
			$this->artifactMapper->getArtifactsForUser($job->getId(), 'bob');
			$this->fail('Read-only share should not access analysis artifacts.');
		} catch (DoesNotExistException) {
			$this->addToAssertionCount(1);
		}
	}

	public function testAnalyzeShareCanSeeAnalysisJobAndArtifacts(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$this->shareMapper->upsertShare($diary->getId(), 'bob', DiaryPermissions::READ | DiaryPermissions::ANALYZE);
		$job = $this->jobMapper->createJob($diary->getId(), 'alice', 1000, 2000, 'Report', 'en', false);
		$artifact = $this->artifactMapper->createArtifact($job->getId(), 'json', 'application/json', 'analysis.json', 'analysis.json');

		$this->assertSame([$job->getId()], array_map(static fn (AnalysisJob $visibleJob): int => $visibleJob->getId(), $this->jobMapper->getJobsForUser('bob')));
		$this->assertSame($job->getId(), $this->jobMapper->getJobForUser($job->getId(), 'bob')->getId());
		$this->assertSame([$artifact->getId()], array_map(static fn ($visibleArtifact): int => $visibleArtifact->getId(), $this->artifactMapper->getArtifactsForUser($job->getId(), 'bob')));
	}
}
