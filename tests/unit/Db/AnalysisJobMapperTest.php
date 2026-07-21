<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AnalysisJobMapperTest extends TestCase {
	private IDBConnection&MockObject $db;
	private DiaryMapper&MockObject $diaryMapper;
	private AnalysisConfigService&MockObject $configService;
	private IURLGenerator&MockObject $urlGenerator;

	protected function setUp(): void {
		parent::setUp();
		$this->db = $this->createMock(IDBConnection::class);
		$this->diaryMapper = $this->createMock(DiaryMapper::class);
		$this->configService = $this->createMock(AnalysisConfigService::class);
		$this->configService->method('getOutputBaseFolder')->willReturn('/StructuredDiary/Analyses');
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->urlGenerator->method('linkTo')->willReturnCallback(static function (string $appName, string $file, array $args = []): string {
			$url = '/apps/' . $appName . '/' . $file;
			return $args === [] ? $url : $url . '?' . http_build_query($args);
		});
		$this->urlGenerator->method('linkToRouteAbsolute')->willReturnCallback(static function (string $routeName, array $args = []): string {
			return 'https://cloud.example/index.php/apps/files/files/' . ($args['fileid'] ?? 0) . '?' . http_build_query(['dir' => $args['dir'] ?? '']);
		});
		$this->urlGenerator->method('getAbsoluteURL')->willReturnCallback(static fn (string $url): string => 'https://cloud.example' . $url);
	}

	public function testUpdateFromPythonAcceptsLifecycleTransitionAndAlwaysUpdatesTimestamp(): void {
		$job = $this->job(AnalysisJob::STATUS_QUEUED);
		$mapper = $this->mapper(['update', 'getCurrentTimestamp']);
		$mapper->method('getCurrentTimestamp')->willReturn(1234);
		$mapper->expects($this->once())
			->method('update')
			->willReturnCallback(static fn (AnalysisJob $updated): AnalysisJob => $updated);

		$result = $mapper->updateFromPython($job, AnalysisJob::STATUS_LOAD_DATA, 12.5, 'loading', null);

		$this->assertSame(AnalysisJob::STATUS_LOAD_DATA, $result->getStatus());
		$this->assertSame(12.5, $result->getProgress());
		$this->assertSame('loading', $result->getStatusMessage());
		$this->assertSame(1234, $result->getUpdatedAt());
		$this->assertSame('https://cloud.example/index.php/apps/files/files/0?dir=%2FStructuredDiary%2FAnalyses%2Freport-42', $result->getStorageUrl());
	}

	public function testUpdateFromPythonAcceptsSubmittedToReadyQueueTransition(): void {
		$job = $this->job(AnalysisJob::STATUS_SUBMITTED);
		$mapper = $this->mapper(['update', 'getCurrentTimestamp']);
		$mapper->method('getCurrentTimestamp')->willReturn(1234);
		$mapper->expects($this->once())
			->method('update')
			->willReturnCallback(static fn (AnalysisJob $updated): AnalysisJob => $updated);

		$result = $mapper->updateFromPython($job, AnalysisJob::STATUS_READY_QUEUE, null, 'ready for worker queue', null);

		$this->assertSame(AnalysisJob::STATUS_READY_QUEUE, $result->getStatus());
		$this->assertNull($result->getStartedAt());
		$this->assertSame(1234, $result->getUpdatedAt());
		$this->assertSame('ready for worker queue', $result->getStatusMessage());
	}

	public function testPythonUpdateTransitionsIncludeEveryReachableLifecycleState(): void {
		$reflection = new \ReflectionClass(AnalysisJobMapper::class);
		$actualTransitions = $reflection->getReflectionConstant('PYTHON_UPDATE_TRANSITIONS')?->getValue();
		$this->assertIsArray($actualTransitions);

		$expectedTransitions = [];
		foreach ($actualTransitions as $from => $nextStatuses) {
			$reachable = [];
			$pending = $nextStatuses;
			while ($pending !== []) {
				$status = array_pop($pending);
				if ($status === $from || isset($reachable[$status])) {
					continue;
				}
				$reachable[$status] = true;
				foreach ($actualTransitions[$status] ?? [] as $nextStatus) {
					$pending[] = $nextStatus;
				}
			}
			$expectedTransitions[$from] = array_keys($reachable);
			sort($expectedTransitions[$from]);
		}

		foreach ($actualTransitions as &$nextStatuses) {
			sort($nextStatuses);
		}
		unset($nextStatuses);
		ksort($expectedTransitions);
		ksort($actualTransitions);

		$this->assertSame($expectedTransitions, $actualTransitions);
	}

	public function testUpdateFromPythonClampsNegativeProgressFromService(): void {
		$job = $this->job(AnalysisJob::STATUS_QUEUED);
		$mapper = $this->mapper(['update', 'getCurrentTimestamp']);
		$mapper->method('getCurrentTimestamp')->willReturn(1234);
		$mapper->expects($this->once())
			->method('update')
			->willReturnCallback(static fn (AnalysisJob $updated): AnalysisJob => $updated);

		$result = $mapper->updateFromPython($job, AnalysisJob::STATUS_LOAD_DATA, -5.0, 'loading', null);

		$this->assertSame(0.0, $result->getProgress());
	}

	public function testUpdateFromPythonRejectsInvalidTransition(): void {
		$mapper = $this->mapper(['update']);
		$mapper->expects($this->never())->method('update');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid Python job status transition.');

		$mapper->updateFromPython($this->job(AnalysisJob::STATUS_DRAFT), AnalysisJob::STATUS_RUNNING, null, null, null);
	}

	public function testUpdateFromPythonTreatsTerminalResultAfterCancelRequestAsCanceled(): void {
		$job = $this->job(AnalysisJob::STATUS_CANCEL_REQUESTED);
		$mapper = $this->mapper(['update', 'getCurrentTimestamp']);
		$mapper->method('getCurrentTimestamp')->willReturn(1234);
		$mapper->expects($this->once())
			->method('update')
			->willReturnCallback(static fn (AnalysisJob $updated): AnalysisJob => $updated);

		$result = $mapper->updateFromPython($job, AnalysisJob::STATUS_JOB_COMPLETED, 100.0, 'completed', null);

		$this->assertSame(AnalysisJob::STATUS_JOB_CANCELED, $result->getStatus());
		$this->assertSame(1234, $result->getFinishedAt());
	}

	public function testUpdateFromPythonAcceptsWorkerUploadBeforeTerminalStatus(): void {
		$mapper = $this->mapper(['update', 'getCurrentTimestamp']);
		$mapper->method('getCurrentTimestamp')->willReturn(1234);
		$mapper->expects($this->exactly(2))
			->method('update')
			->willReturnCallback(static fn (AnalysisJob $updated): AnalysisJob => $updated);

		$workerUpload = $mapper->updateFromPython($this->job(AnalysisJob::STATUS_RUNNING), AnalysisJob::STATUS_WORKER_UPLOAD, 99.0, 'uploading artifacts', null);
		$this->assertSame(AnalysisJob::STATUS_WORKER_UPLOAD, $workerUpload->getStatus());
		$this->assertSame(99.0, $workerUpload->getProgress());
		$this->assertNull($workerUpload->getFinishedAt());

		$completed = $mapper->updateFromPython($workerUpload, AnalysisJob::STATUS_JOB_COMPLETED, null, 'completed', null);

		$this->assertSame(AnalysisJob::STATUS_JOB_COMPLETED, $completed->getStatus());
		$this->assertSame(1234, $completed->getFinishedAt());
		$this->assertSame(100.0, $completed->getProgress());
	}

	public function testRequestCancelWhileCollectingResultsMovesToPythonCleanupState(): void {
		$job = $this->job(AnalysisJob::STATUS_JOB_COMPLETED);
		$mapper = $this->mapper(['update', 'getCurrentTimestamp']);
		$mapper->method('getCurrentTimestamp')->willReturn(1234);
		$mapper->expects($this->once())
			->method('update')
			->willReturnCallback(static fn (AnalysisJob $updated): AnalysisJob => $updated);

		$result = $mapper->requestCancel($job);

		$this->assertSame(AnalysisJob::STATUS_JOB_CANCELED, $result->getStatus());
		$this->assertSame(1234, $result->getCancelRequestedAt());
		$this->assertSame(1234, $result->getFinishedAt());
	}

	public function testUpdateFromPythonClampsProgressAboveOneHundred(): void {
		$mapper = $this->mapper(['update']);
		$mapper->expects($this->once())
			->method('update')
			->willReturnCallback(static fn (AnalysisJob $updated): AnalysisJob => $updated);

		$result = $mapper->updateFromPython($this->job(AnalysisJob::STATUS_RUNNING), null, 100.1, null, null);

		$this->assertSame(100.0, $result->getProgress());
	}

	public function testUpdateFromPythonRejectsProgressDecreaseWithoutStateChange(): void {
		$job = $this->job(AnalysisJob::STATUS_RUNNING);
		$job->setProgress(50.0);
		$mapper = $this->mapper(['update']);
		$mapper->expects($this->never())->method('update');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Progress cannot decrease without a status change.');

		$mapper->updateFromPython($job, null, 49.0, null, null);
	}

	public function testUpdateFromPythonAllowsProgressDecreaseWhenStateChanges(): void {
		$job = $this->job(AnalysisJob::STATUS_RUNNING);
		$job->setProgress(50.0);
		$mapper = $this->mapper(['update']);
		$mapper->expects($this->once())->method('update')->willReturnCallback(static fn (AnalysisJob $updated): AnalysisJob => $updated);

		$result = $mapper->updateFromPython($job, AnalysisJob::STATUS_RESTART, 10.0, null, null);

		$this->assertSame(AnalysisJob::STATUS_RESTART, $result->getStatus());
		$this->assertSame(10.0, $result->getProgress());
	}

	/**
	 * @dataProvider finalizedStatuses
	 */
	public function testUpdateFromPythonRejectsMetadataUpdatesAfterFinalization(string $status): void {
		$mapper = $this->mapper(['update']);
		$mapper->expects($this->never())->method('update');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Finalized jobs cannot be updated by the analysis service.');

		$mapper->updateFromPython($this->job($status), null, 100.0, 'stale callback', null);
	}

	/**
	 * @return list<array{string}>
	 */
	public static function finalizedStatuses(): array {
		return [
			[AnalysisJob::STATUS_CANCELED],
			[AnalysisJob::STATUS_FAILED],
			[AnalysisJob::STATUS_COMPLETED],
		];
	}

	public function testAssertPythonAccessRequiresMatchingTokenLoadDataAndFreshUpdate(): void {
		$mapper = new AnalysisJobMapper($this->db, $this->diaryMapper, $this->configService, $this->urlGenerator);
		$job = $this->job(AnalysisJob::STATUS_LOAD_DATA);
		$job->setToken('secret');
		$job->setUpdatedAt(2000);

		$mapper->assertPythonAccess($job, 'secret', '11111111-2222-4333-8444-555555555555', true, 2100);

		$this->addToAssertionCount(1);
	}

	public function testAssertPythonAccessRejectsTimedOutDataRequest(): void {
		$mapper = new AnalysisJobMapper($this->db, $this->diaryMapper, $this->configService, $this->urlGenerator);
		$job = $this->job(AnalysisJob::STATUS_LOAD_DATA);
		$job->setToken('secret');
		$job->setUpdatedAt(1000);

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Job data access timed out.');

		$mapper->assertPythonAccess($job, 'secret', '11111111-2222-4333-8444-555555555555', true, 1000 + 86401);
	}

	public function testAssertPythonAccessRejectsMissingUuid(): void {
		$mapper = new AnalysisJobMapper($this->db, $this->diaryMapper, $this->configService, $this->urlGenerator);
		$job = $this->job(AnalysisJob::STATUS_LOAD_DATA);
		$job->setToken('secret');
		$job->setUpdatedAt(2000);

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Invalid analysis job uuid.');

		$mapper->assertPythonAccess($job, 'secret', null, true, 2100);
	}

	public function testAssertPythonAccessRejectsWrongTokenEvenWhenPythonJobIdMatches(): void {
		$mapper = new AnalysisJobMapper($this->db, $this->diaryMapper, $this->configService, $this->urlGenerator);
		$job = $this->job(AnalysisJob::STATUS_LOAD_DATA);
		$job->setToken('secret');
		$job->setUpdatedAt(2000);

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Invalid job token.');

		$mapper->assertPythonAccess($job, 'wrong', '11111111-2222-4333-8444-555555555555', true, 2100);
	}

	public function testAssertPythonAccessRejectsDataExportBeforeLoadData(): void {
		$mapper = new AnalysisJobMapper($this->db, $this->diaryMapper, $this->configService, $this->urlGenerator);
		$job = $this->job(AnalysisJob::STATUS_RUNNING);
		$job->setToken('secret');
		$job->setUpdatedAt(2000);

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Job is not loading data.');

		$mapper->assertPythonAccess($job, 'secret', '11111111-2222-4333-8444-555555555555', true, 2100);
	}

	/**
	 * @param list<string> $methods
	 */
	private function mapper(array $methods): AnalysisJobMapper&MockObject {
		return $this->getMockBuilder(AnalysisJobMapper::class)
			->setConstructorArgs([$this->db, $this->diaryMapper, $this->configService, $this->urlGenerator])
			->onlyMethods($methods)
			->getMock();
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
		$job->setStoragePath('/StructuredDiary/Analyses/report-42');
		$job->setStatusMessage('');
		$job->setArtifactsDownloaded(false);
		$job->setPythonDeleted(false);

		return $job;
	}
}
