<?php

declare(strict_types=1);

namespace Service;

use OCA\StructuredDiary\Db\AnalysisArtifact;
use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Service\AnalysisArtifactStorageService;
use OCA\StructuredDiary\Service\AnalysisJobProcessingService;
use OCA\StructuredDiary\Service\PythonAnalysisClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AnalysisJobProcessingServiceTest extends TestCase {
	public function testTryQueueJobMarksReadyJobQueuedWhenPythonAcceptsIt(): void {
		$job = $this->job(AnalysisJob::STATUS_READY_QUEUE);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$pythonClient = $this->createMock(PythonAnalysisClient::class);
		$storage = $this->createMock(AnalysisArtifactStorageService::class);
		$queued = $this->job(AnalysisJob::STATUS_QUEUED);
		$queued->setPythonJobId('123');

		$jobMapper->method('getJob')->with(42)->willReturn($job);
		$pythonClient->expects($this->once())->method('enqueueJob')->with($job, 1)->willReturn('123');
		$jobMapper->expects($this->once())->method('markQueued')->with($job, '123')->willReturn($queued);

		$service = new AnalysisJobProcessingService($jobMapper, $artifactMapper, $pythonClient, $storage);

		$this->assertSame($queued, $service->tryQueueJob(42, true, 1));
	}

	public function testTryQueueJobCanSuppressVisibleErrorForCreateTimeQuickQueueFailure(): void {
		$job = $this->job(AnalysisJob::STATUS_READY_QUEUE);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$pythonClient = $this->createMock(PythonAnalysisClient::class);
		$storage = $this->createMock(AnalysisArtifactStorageService::class);

		$jobMapper->method('getJob')->with(42)->willReturn($job);
		$pythonClient->expects($this->once())->method('enqueueJob')->with($job, 1)->willThrowException(new \RuntimeException('timeout'));
		$jobMapper->expects($this->once())->method('update')->with($this->callback(static function (AnalysisJob $updated): bool {
			return $updated->getStatus() === AnalysisJob::STATUS_READY_QUEUE
				&& $updated->getErrorMessage() === null
				&& $updated->getPythonDeletedFails() === 1;
		}))->willReturnCallback(static fn (AnalysisJob $updated): AnalysisJob => $updated);

		$service = new AnalysisJobProcessingService($jobMapper, $artifactMapper, $pythonClient, $storage);
		$result = $service->tryQueueJob(42, true, 1, false);

		$this->assertSame($job, $result);
		$this->assertNull($result->getErrorMessage());
	}

	public function testTryQueueJobRestoresReadyStateWhenMarkQueuedFails(): void {
		$job = $this->job(AnalysisJob::STATUS_READY_QUEUE);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$pythonClient = $this->createMock(PythonAnalysisClient::class);
		$storage = $this->createMock(AnalysisArtifactStorageService::class);

		$jobMapper->method('getJob')->with(42)->willReturn($job);
		$pythonClient->expects($this->once())->method('enqueueJob')->with($job, 30)->willReturn('1');
		$jobMapper->expects($this->once())->method('markQueued')->with($job, '1')->willThrowException(new \RuntimeException('duplicate python id'));
		$jobMapper->expects($this->once())->method('update')->with($this->callback(static function (AnalysisJob $updated): bool {
			return $updated->getStatus() === AnalysisJob::STATUS_READY_QUEUE
				&& $updated->getPythonJobId() === null
				&& $updated->getStartedAt() === null
				&& $updated->getPythonDeletedFails() === 1
				&& $updated->getErrorMessage() === 'duplicate python id';
		}))->willReturnCallback(static fn (AnalysisJob $updated): AnalysisJob => $updated);

		$service = new AnalysisJobProcessingService($jobMapper, $artifactMapper, $pythonClient, $storage);

		$this->assertSame($job, $service->tryQueueJob(42, true, 30));
	}

	public function testRequestCancelPersistsCancelAndNotifiesPythonWhenPythonJobExists(): void {
		$job = $this->job(AnalysisJob::STATUS_RUNNING);
		$job->setPythonJobId('py-1');
		$canceled = $this->job(AnalysisJob::STATUS_CANCEL_REQUESTED);
		$canceled->setPythonJobId('py-1');
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$pythonClient = $this->createMock(PythonAnalysisClient::class);
		$storage = $this->createMock(AnalysisArtifactStorageService::class);

		$jobMapper->expects($this->once())->method('requestCancel')->with($job)->willReturn($canceled);
		$pythonClient->expects($this->once())->method('cancelJob')->with($canceled);

		$service = new AnalysisJobProcessingService($jobMapper, $artifactMapper, $pythonClient, $storage);

		$this->assertSame($canceled, $service->requestCancel($job));
	}

	public function testRequestCancelWhileCollectingResultsDoesNotSendPythonCancelRequest(): void {
		$job = $this->job(AnalysisJob::STATUS_JOB_COMPLETED);
		$job->setPythonJobId('py-1');
		$canceled = $this->job(AnalysisJob::STATUS_JOB_CANCELED);
		$canceled->setPythonJobId('py-1');
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$pythonClient = $this->createMock(PythonAnalysisClient::class);
		$storage = $this->createMock(AnalysisArtifactStorageService::class);

		$jobMapper->expects($this->once())->method('requestCancel')->with($job)->willReturn($canceled);
		$pythonClient->expects($this->never())->method('cancelJob');

		$service = new AnalysisJobProcessingService($jobMapper, $artifactMapper, $pythonClient, $storage);

		$this->assertSame($canceled, $service->requestCancel($job));
	}

	public function testPreviouslyListedOpenArtifactsAreRetriedBeforeCleanup(): void {
		$job = $this->job(AnalysisJob::STATUS_JOB_COMPLETED);
		$job->setArtifactsDownloaded(false);
		$artifact = $this->artifact(301, false);
		$downloaded = $this->artifact(301, true);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$pythonClient = $this->createMock(PythonAnalysisClient::class);
		$storage = $this->createMock(AnalysisArtifactStorageService::class);

		$pythonClient->expects($this->once())->method('listArtifacts')->with($job)->willReturn([
			[
				'id' => 301,
				'output_type' => 'JSON',
				'file_name' => 'analysis.json',
				'file_path' => 'analysis.json',
				'mime_type' => 'application/json',
				'size' => 12,
			],
		]);
		$artifactMapper->expects($this->once())->method('artifactPathExists')->with(42, 'analysis.json')->willReturn(true);
		$artifactMapper->expects($this->never())->method('createFromPythonArtifact');
		$artifactMapper->expects($this->exactly(3))->method('getArtifactsForJob')->with(42)->willReturnOnConsecutiveCalls([$artifact], [$artifact], [$downloaded]);
		$pythonClient->expects($this->once())->method('downloadArtifact')->with($job, 301)->willReturn('{"ok":true}');
		$storage->expects($this->once())->method('storeArtifactContent')->with($job, $artifact, '{"ok":true}');
		$jobMapper->expects($this->exactly(2))->method('update')->with($this->callback(static fn (AnalysisJob $updated): bool => $updated->getArtifactsDownloaded()));

		$service = new AnalysisJobProcessingService($jobMapper, $artifactMapper, $pythonClient, $storage);
		$service->processTerminalJob($job);
	}

	public function testTerminalPythonStatusDownloadsOnlyArtifactListAndMarksListDownloaded(): void {
		$job = $this->job(AnalysisJob::STATUS_JOB_COMPLETED);
		$job->setArtifactsDownloaded(false);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$pythonClient = $this->createMock(PythonAnalysisClient::class);
		$storage = $this->createMock(AnalysisArtifactStorageService::class);

		$pythonClient->expects($this->once())->method('listArtifacts')->with($job)->willReturn([
			[
				'id' => 301,
				'output_type' => 'JSON',
				'file_name' => 'analysis.json',
				'file_path' => 'analysis.json',
				'mime_type' => 'application/json',
				'size' => 12,
			],
		]);
		$artifactMapper->expects($this->once())->method('artifactPathExists')->with(42, 'analysis.json')->willReturn(false);
		$artifactMapper->expects($this->once())->method('createFromPythonArtifact')->with(42, $this->isType('array'), null)->willReturn($this->artifact(301, false));
		$artifactMapper->expects($this->once())->method('getArtifactsForJob')->with(42)->willReturn([]);
		$pythonClient->expects($this->never())->method('downloadArtifact');
		$jobMapper->expects($this->once())->method('update')->with($this->callback(static fn (AnalysisJob $updated): bool => $updated->getArtifactsDownloaded()));

		$service = new AnalysisJobProcessingService($jobMapper, $artifactMapper, $pythonClient, $storage);
		$service->handleTerminalPythonStatusUpdate($job);
	}

	public function testArtifactListCreationOrdersParentsBeforeChildren(): void {
		$job = $this->job(AnalysisJob::STATUS_JOB_COMPLETED);
		$job->setArtifactsDownloaded(false);
		$parent = $this->artifactWithIds(11, 301, null);
		$child = $this->artifactWithIds(12, 302, 11);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$pythonClient = $this->createMock(PythonAnalysisClient::class);
		$storage = $this->createMock(AnalysisArtifactStorageService::class);
		$parentPayload = [
			'id' => 301,
			'output_type' => 'HTML',
			'file_name' => 'report.html',
			'file_path' => 'report.html',
			'mime_type' => 'text/html',
			'size' => 12,
		];
		$childPayload = [
			'id' => 302,
			'parent_id' => 301,
			'output_type' => 'PLOT',
			'file_name' => 'plot.png',
			'file_path' => 'plots/plot.png',
			'mime_type' => 'image/png',
			'size' => 20,
		];

		$pythonClient->expects($this->once())->method('listArtifacts')->with($job)->willReturn([$childPayload, $parentPayload]);
		$artifactMapper->expects($this->once())->method('getArtifactsForJob')->with(42)->willReturn([]);
		$artifactMapper->expects($this->exactly(2))->method('artifactPathExists')->willReturn(false);
		$createCalls = 0;
		$artifactMapper->expects($this->exactly(2))
			->method('createFromPythonArtifact')
			->willReturnCallback(static function (int $jobId, array $payload, ?int $localParentId) use (&$createCalls, $parentPayload, $childPayload, $parent, $child): AnalysisArtifact {
				$createCalls++;
				if ($createCalls === 1) {
					TestCase::assertSame(42, $jobId);
					TestCase::assertSame($parentPayload, $payload);
					TestCase::assertNull($localParentId);
					return $parent;
				}
				TestCase::assertSame(42, $jobId);
				TestCase::assertSame($childPayload, $payload);
				TestCase::assertSame(11, $localParentId);
				return $child;
			});
		$jobMapper->expects($this->once())->method('update')->with($this->callback(static fn (AnalysisJob $updated): bool => $updated->getArtifactsDownloaded()));

		$service = new AnalysisJobProcessingService($jobMapper, $artifactMapper, $pythonClient, $storage);
		$service->downloadArtifactListForJob($job);
	}

	public function testArtifactDownloadFailureLeavesJobRetryableButAttemptsRemainingArtifacts(): void {
		$job = $this->job(AnalysisJob::STATUS_JOB_COMPLETED);
		$job->setArtifactsDownloaded(true);
		$failed = $this->artifact(301, false);
		$successful = $this->artifact(302, false);
		$stillOpen = $this->artifact(301, false);
		$downloaded = $this->artifact(302, true);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$pythonClient = $this->createMock(PythonAnalysisClient::class);
		$storage = $this->createMock(AnalysisArtifactStorageService::class);

		$artifactMapper->expects($this->exactly(2))->method('getArtifactsForJob')->with(42)->willReturnOnConsecutiveCalls([$failed, $successful], [$stillOpen, $downloaded]);
		$pythonClient->expects($this->exactly(2))->method('downloadArtifact')->willReturnCallback(static fn (AnalysisJob $jobArg, int $pythonFileId): string => $pythonFileId === 301 ? 'broken' : 'ok');
		$storage->expects($this->exactly(2))->method('storeArtifactContent')->willReturnCallback(static function (AnalysisJob $jobArg, AnalysisArtifact $artifact, string $content) use ($failed, $successful): AnalysisArtifact {
			if ($artifact === $failed) {
				throw new \RuntimeException('disk full');
			}
			return $successful;
		});
		$artifactMapper->expects($this->once())->method('markDownloadFailed')->with($failed);
		$jobMapper->expects($this->once())->method('update')->with($this->callback(static fn (AnalysisJob $updated): bool => $updated->getArtifactsDownloaded() && $updated->getArtifactsDownloadFails() === 1));

		$service = new AnalysisJobProcessingService($jobMapper, $artifactMapper, $pythonClient, $storage);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Not all artifacts have been downloaded.');
		$service->downloadArtifactsForJob($job, false);
	}

	private function job(string $status): AnalysisJob {
		$job = new AnalysisJob();
		$job->setId(42);
		$job->setUuid('11111111-2222-4333-8444-555555555555');
		$job->setDiaryId(7);
		$job->setCreatedBy('alice');
		$job->setCreatedAt(1000);
		$job->setUpdatedAt(1000);
		$job->setDataFrom(10);
		$job->setDataUntil(20);
		$job->setTitle('Report');
		$job->setLanguage('en');
		$job->setAnalysisType(AnalysisJob::TYPE_STANDARD);
		$job->setStatus($status);
		$job->setProgress(0.0);
		$job->setOutputTypes('["JSON"]');
		$job->setParametersJson('{}');
		$job->setToken('token');
		$job->setStoragePath('/StructuredDiary/Analyses/Diary-7/Report-42');
		$job->setStatusMessage('');
		$job->setArtifactsDownloaded(false);
		$job->setArtifactsDownloadFails(0);
		$job->setPythonDeleted(false);
		$job->setPythonDeletedFails(0);

		return $job;
	}

	private function artifact(int $pythonFileId, bool $downloaded): AnalysisArtifact {
		$artifact = new AnalysisArtifact();
		$artifact->setId($pythonFileId);
		$artifact->setJobId(42);
		$artifact->setArtifactType('JSON');
		$artifact->setMimeType('application/json');
		$artifact->setFileName('analysis-' . $pythonFileId . '.json');
		$artifact->setFilePath('analysis-' . $pythonFileId . '.json');
		$artifact->setPythonFileId($pythonFileId);
		$artifact->setDownloaded($downloaded);
		$artifact->setDownloadFails(0);
		return $artifact;
	}

	private function artifactWithIds(int $id, int $pythonFileId, ?int $parentId): AnalysisArtifact {
		$artifact = $this->artifact($pythonFileId, false);
		$artifact->setId($id);
		$artifact->setParentId($parentId);
		return $artifact;
	}
}
