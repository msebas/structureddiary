<?php

declare(strict_types=1);

namespace OCA\Tests\StructuredDiary\Integration\Controller;

use OCA\StructuredDiary\Controller\PythonAnalysisController;
use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Service\AnalysisArtifactStorageService;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCA\StructuredDiary\Service\AnalysisExportService;
use OCA\StructuredDiary\Service\AnalysisJobFinalizationService;
use OCA\Tests\StructuredDiary\Integration\TestUtil\IntegrationTestParentClass;
use OCP\IRequest;

final class PythonAnalysisControllerIntegrationTest extends IntegrationTestParentClass {
	private AnalysisJobMapper $jobMapper;
	private AnalysisArtifactMapper $artifactMapper;
	private DiaryMapper $diaryMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->jobMapper = self::$container->get(AnalysisJobMapper::class);
		$this->artifactMapper = self::$container->get(AnalysisArtifactMapper::class);
		$this->diaryMapper = self::$container->get(DiaryMapper::class);
	}

	public function testPollStatusArtifactAndFinalizeWorkflowPersistsThroughServiceController(): void {
		$job = $this->submittedJob();
		$pollRequest = $this->createMock(IRequest::class);
		$pollRequest->method('getHeader')->with('x-structureddiary-nextcloud-api-token')->willReturn('nextcloud-token');
		$config = $this->createMock(AnalysisConfigService::class);
		$config->method('getNextcloudApiToken')->willReturn('nextcloud-token');

		$poll = $this->controller($pollRequest, $config)->poll();
		$this->assertSame(200, $poll->getStatus());
		$this->assertSame($job->getUuid(), $poll->getData()[0]['uuid']);
		$this->assertSame($job->getToken(), $poll->getData()[0]['job_token']);

		$controller = $this->controller($this->jobRequest($job));
		$status = $controller->updateStatus($job->getUuid(), AnalysisJob::STATUS_WORKER_UPLOAD, 90.0, 'Uploading report');
		$this->assertSame(200, $status->getStatus());
		$this->assertSame(AnalysisJob::STATUS_WORKER_UPLOAD, $this->jobMapper->getJob($job->getId())->getStatus());

		$created = $controller->createArtifacts($job->getUuid(), [
			['id' => 10, 'output_type' => 'HTML', 'mime_type' => 'text/html', 'file_name' => 'report.html', 'file_path' => 'report.html', 'size' => 12, 'checksum' => 'sha256:report'],
		]);
		$this->assertSame(201, $created->getStatus());
		$this->assertSame(10, $created->getData()[0]['python_id']);
		$this->assertTrue($this->jobMapper->getJob($job->getId())->getArtifactsDownloaded());
		$duplicate = $controller->createArtifacts($job->getUuid(), []);
		$this->assertSame(200, $duplicate->getStatus());
		$this->assertSame($created->getData(), $duplicate->getData());
		$this->assertCount(1, $this->artifactMapper->getArtifactsForJob($job->getId()));

		$artifacts = $controller->getArtifacts($job->getUuid());
		$this->assertSame(200, $artifacts->getStatus());
		$this->assertSame($created->getData(), $artifacts->getData());

		$controller->updateStatus($job->getUuid(), AnalysisJob::STATUS_JOB_COMPLETED, null, 'Complete');
		$pending = $controller->finalize($job->getUuid());
		$this->assertSame(AnalysisJob::STATUS_JOB_COMPLETED, $pending->getData()->getStatus());

		$artifact = $this->artifactMapper->getArtifactsForJob($job->getId())[0];
		$this->artifactMapper->markDownloaded($artifact, 77, 12, 'sha256:report');
		$finalized = $controller->finalize($job->getUuid());
		$this->assertSame(200, $finalized->getStatus());
		$this->assertSame(AnalysisJob::STATUS_COMPLETED, $finalized->getData()->getStatus());
		$this->assertFalse($this->jobMapper->getJob($job->getId())->getPythonDeleted());
	}

	public function testServiceEndpointRejectsWrongJobTokenWithoutChangingPersistence(): void {
		$job = $this->submittedJob();
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturnMap([
			['x-structureddiary-job-token', 'wrong-token'],
			['x-structureddiary-job-uuid', $job->getUuid()],
		]);

		$response = $this->controller($request)->updateStatus($job->getUuid(), AnalysisJob::STATUS_READY_QUEUE);

		$this->assertSame(400, $response->getStatus());
		$this->assertSame(AnalysisJob::STATUS_SUBMITTED, $this->jobMapper->getJob($job->getId())->getStatus());
	}

	public function testFailedManifestRollsBackArtifactsAndClaim(): void {
		$job = $this->submittedJob();
		$this->jobMapper->updateFromPython($job, AnalysisJob::STATUS_WORKER_UPLOAD, null, null, null);
		$controller = $this->controller($this->jobRequest($job));

		$response = $controller->createArtifacts($job->getUuid(), [
			['id' => 1, 'output_type' => 'HTML', 'file_name' => 'report.html', 'file_path' => 'report.html'],
			['id' => 2, 'output_type' => 'JSON', 'file_name' => 'report.json', 'file_path' => 'report.html'],
		]);

		$this->assertSame(400, $response->getStatus());
		$this->assertSame([], $this->artifactMapper->getArtifactsForJob($job->getId()));
		$this->assertFalse($this->jobMapper->getJob($job->getId())->getArtifactsDownloaded());
	}

	public function testServiceEndpointRejectsLateFinalizedAndDecreasingProgressUpdates(): void {
		$job = $this->submittedJob();
		$this->jobMapper->updateFromPython($job, AnalysisJob::STATUS_WORKER_UPLOAD, 50.0, null, null);
		$controller = $this->controller($this->jobRequest($job));

		$decreasing = $controller->updateStatus($job->getUuid(), null, 49.0, 'stale progress');
		$this->assertSame(400, $decreasing->getStatus());
		$this->assertSame(50.0, $this->jobMapper->getJob($job->getId())->getProgress());

		$completed = $this->jobMapper->updateFromPython($this->jobMapper->getJob($job->getId()), AnalysisJob::STATUS_JOB_COMPLETED, null, null, null);
		$completed->setArtifactsDownloaded(true);
		$this->jobMapper->update($completed);
		(new AnalysisJobFinalizationService($this->jobMapper, $this->artifactMapper))->finalize($this->jobMapper->getJob($job->getId()));

		$late = $controller->updateStatus($job->getUuid(), null, 100.0, 'late callback');
		$this->assertSame(400, $late->getStatus());
		$stored = $this->jobMapper->getJob($job->getId());
		$this->assertSame(AnalysisJob::STATUS_COMPLETED, $stored->getStatus());
		$this->assertSame(100.0, $stored->getProgress());
	}

	public function testDuplicatePollersAndCallbacksAreIdempotentWhileReorderedCallbackCannotRegressState(): void {
		$job = $this->submittedJob();
		$pollRequest = $this->createMock(IRequest::class);
		$pollRequest->method('getHeader')->with('x-structureddiary-nextcloud-api-token')->willReturn('nextcloud-token');
		$config = $this->createMock(AnalysisConfigService::class);
		$config->method('getNextcloudApiToken')->willReturn('nextcloud-token');
		$pollController = $this->controller($pollRequest, $config);

		$this->assertSame($pollController->poll()->getData(), $pollController->poll()->getData());

		$controller = $this->controller($this->jobRequest($job));
		$this->assertSame(200, $controller->updateStatus($job->getUuid(), AnalysisJob::STATUS_READY_QUEUE, 5.0)->getStatus());
		$this->assertSame(200, $controller->updateStatus($job->getUuid(), AnalysisJob::STATUS_READY_QUEUE, 5.0)->getStatus());
		$this->assertSame(200, $controller->updateStatus($job->getUuid(), AnalysisJob::STATUS_QUEUED, 10.0)->getStatus());

		$reordered = $controller->updateStatus($job->getUuid(), AnalysisJob::STATUS_READY_QUEUE, 5.0);
		$this->assertSame(400, $reordered->getStatus());
		$stored = $this->jobMapper->getJob($job->getId());
		$this->assertSame(AnalysisJob::STATUS_QUEUED, $stored->getStatus());
		$this->assertSame(10.0, $stored->getProgress());
	}

	private function submittedJob(): AnalysisJob {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', '');
		return $this->jobMapper->createJob(
			$diary->getId(),
			'alice',
			100,
			200,
			'Report',
			'en',
			true,
			null,
			['includeTextAnalysis' => false],
		);
	}

	private function jobRequest(AnalysisJob $job): IRequest {
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturnMap([
			['x-structureddiary-job-token', $job->getToken()],
			['x-structureddiary-job-uuid', $job->getUuid()],
		]);
		return $request;
	}

	private function controller(IRequest $request, ?AnalysisConfigService $config = null): PythonAnalysisController {
		return new PythonAnalysisController(
			'structureddiary',
			$request,
			$this->jobMapper,
			$this->artifactMapper,
			$this->createMock(AnalysisExportService::class),
			$this->createMock(AnalysisArtifactStorageService::class),
			$config ?? $this->createMock(AnalysisConfigService::class),
			new AnalysisJobFinalizationService($this->jobMapper, $this->artifactMapper),
			self::$db,
		);
	}
}
