<?php

declare(strict_types=1);

namespace Controller;

use OCA\StructuredDiary\Controller\PythonAnalysisController;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCA\StructuredDiary\Db\AnalysisArtifact;
use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCA\StructuredDiary\Service\AnalysisArtifactStorageService;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCA\StructuredDiary\Service\AnalysisExportService;
use OCA\StructuredDiary\Service\AnalysisJobFinalizationService;
use OCP\IRequest;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class PythonAnalysisControllerTest extends TestCase {
	/**
	 * @dataProvider pythonCallbackMethods
	 */
	public function testPythonCallbackEndpointsArePublicAndCsrfExempt(string $method): void {
		$reflection = new ReflectionMethod(PythonAnalysisController::class, $method);

		$this->assertNotEmpty($reflection->getAttributes(PublicPage::class), $method . ' must not require a logged-in browser user.');
		$this->assertNotEmpty($reflection->getAttributes(NoCSRFRequired::class), $method . ' must accept service-to-service requests without a CSRF token.');
	}

	/**
	 * @return list<array{string}>
	 */
	public static function pythonCallbackMethods(): array {
		return [
			['healthcheck'],
			['poll'],
			['updateStatus'],
			['diary'],
			['entries'],
			['createArtifacts'],
			['getArtifacts'],
			['finalize'],
			['uploadArtifact'],
		];
	}

	/**
	 * @dataProvider pythonServiceRoutes
	 */
	public function testPythonEndpointsUseDedicatedServiceNamespace(string $method, string $url): void {
		$reflection = new ReflectionMethod(PythonAnalysisController::class, $method);
		$attributes = $reflection->getAttributes(ApiRoute::class);

		$this->assertCount(1, $attributes);
		$this->assertSame($url, $attributes[0]->getArguments()['url']);
	}

	/**
	 * @return list<array{string, string}>
	 */
	public static function pythonServiceRoutes(): array {
		return [
			['poll', '/api/{apiVersion}/service/jobs/poll'],
			['healthcheck', '/api/{apiVersion}/service/jobs/healthcheck'],
			['updateStatus', '/api/{apiVersion}/service/jobs/{uuid}/status'],
			['diary', '/api/{apiVersion}/service/jobs/{uuid}/diary'],
			['entries', '/api/{apiVersion}/service/jobs/{uuid}/entries'],
			['createArtifacts', '/api/{apiVersion}/service/jobs/{uuid}/artifacts/create'],
			['getArtifacts', '/api/{apiVersion}/service/jobs/{uuid}/artifacts'],
			['finalize', '/api/{apiVersion}/service/jobs/{uuid}/finalize'],
			['uploadArtifact', '/api/{apiVersion}/service/jobs/{uuid}/artifacts/upload/{artifactId}'],
		];
	}

	public function testPollReturnsPythonJobPayloadForRegisteredNextcloudToken(): void {
		$uuid = '11111111-2222-4333-8444-555555555555';
		$job = $this->job($uuid, AnalysisJob::STATUS_SUBMITTED);
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->with('x-structureddiary-nextcloud-api-token')->willReturn('nextcloud-token');
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->once())
			->method('getJobsForPython')
			->with(1713254400)
			->willReturn([$job]);
		$configService = $this->createMock(AnalysisConfigService::class);
		$configService->method('getNextcloudApiToken')->willReturn('nextcloud-token');

		$response = $this->controller($request, $jobMapper, $this->createMock(AnalysisArtifactMapper::class), null, $configService)
			->poll('2024-04-16T08:00:00Z', 30);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame($uuid, $response->getData()[0]['uuid']);
		$this->assertSame('job-token', $response->getData()[0]['job_token']);
		$this->assertSame(42, $response->getData()[0]['id']);
		$this->assertSame(AnalysisJob::STATUS_SUBMITTED, $response->getData()[0]['status']);
	}

	public function testPollRejectsInvalidNextcloudToken(): void {
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->with('x-structureddiary-nextcloud-api-token')->willReturn('wrong');
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->never())->method('getJobsForPython');
		$configService = $this->createMock(AnalysisConfigService::class);
		$configService->method('getNextcloudApiToken')->willReturn('nextcloud-token');

		$response = $this->controller($request, $jobMapper, $this->createMock(AnalysisArtifactMapper::class), null, $configService)
			->poll(null, 30);

		$this->assertSame(400, $response->getStatus());
		$this->assertSame(['error' => 'Invalid Nextcloud API token.'], $response->getData());
	}

	public function testPollLongPollsUntilChangedJobsAppear(): void {
		$job = $this->job('11111111-2222-4333-8444-555555555555', AnalysisJob::STATUS_SUBMITTED);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->exactly(2))->method('getJobsForPython')->with(1000)->willReturnOnConsecutiveCalls([], [$job]);
		$controller = $this->pollingController($jobMapper, ['getCurrentTimestamp', 'waitBeforeNextLongPollCheck']);
		$controller->expects($this->exactly(2))->method('getCurrentTimestamp')->willReturn(1000);
		$controller->expects($this->once())->method('waitBeforeNextLongPollCheck');

		$response = $controller->poll(1000, 30);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame([$job->getUuid()], array_column($response->getData(), 'uuid'));
	}

	public function testPollReturnsEmptyListWhenLongPollTimeoutExpires(): void {
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->exactly(2))->method('getJobsForPython')->with(1000)->willReturn([]);
		$controller = $this->pollingController($jobMapper, ['getCurrentTimestamp', 'waitBeforeNextLongPollCheck']);
		$controller->expects($this->exactly(3))->method('getCurrentTimestamp')->willReturnOnConsecutiveCalls(1000, 1000, 1001);
		$controller->expects($this->once())->method('waitBeforeNextLongPollCheck');

		$response = $controller->poll(1000, 1);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	public function testPollAcceptsWaitAliasAndRejectsInvalidCursorOrTimeout(): void {
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->exactly(2))->method('getJobsForPython')->with(1000)->willReturn([]);
		$controller = $this->pollingController($jobMapper, ['getCurrentTimestamp', 'waitBeforeNextLongPollCheck']);
		$controller->expects($this->exactly(3))->method('getCurrentTimestamp')->willReturnOnConsecutiveCalls(1000, 1000, 1001);
		$controller->expects($this->once())->method('waitBeforeNextLongPollCheck');
		$this->assertSame([], $controller->poll(1000, null, 1)->getData());

		$invalidMapper = $this->createMock(AnalysisJobMapper::class);
		$invalidMapper->expects($this->never())->method('getJobsForPython');
		$invalid = $this->pollingController($invalidMapper);
		$this->assertSame(400, $invalid->poll('not-a-timestamp', 1)->getStatus());
		$this->assertSame(400, $invalid->poll(1000, 'soon')->getStatus());
	}

	public function testRepeatedPollCursorRedeliversStableJobWithoutMutatingIt(): void {
		$job = $this->job('11111111-2222-4333-8444-555555555555', AnalysisJob::STATUS_SUBMITTED);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->exactly(2))->method('getJobsForPython')->with(1000)->willReturn([$job]);
		$controller = $this->pollingController($jobMapper);

		$first = $controller->poll(1000, 30);
		$second = $controller->poll(1000, 30);

		$this->assertSame($first->getData(), $second->getData());
		$this->assertSame(AnalysisJob::STATUS_SUBMITTED, $job->getStatus());
	}

	public function testUpdateStatusWorkflowUsesJobUuidAndToken(): void {
		$uuid = '11111111-2222-4333-8444-555555555555';
		$job = $this->job($uuid, AnalysisJob::STATUS_QUEUED);
		$updated = $this->job($uuid, AnalysisJob::STATUS_LOAD_DATA);
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturnMap([
			['x-structureddiary-job-token', 'job-token'],
			['x-structureddiary-job-uuid', $uuid],
		]);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->once())->method('getJobByUuid')->with($uuid)->willReturn($job);
		$jobMapper->expects($this->once())->method('assertPythonAccess')->with($job, 'job-token', $uuid, false, $this->isType('int'));
		$jobMapper->expects($this->once())
			->method('updateFromPython')
			->with($job, AnalysisJob::STATUS_LOAD_DATA, 10.0, 'loading', null)
			->willReturn($updated);

		$controller = new PythonAnalysisController(
			'structureddiary',
			$request,
			$jobMapper,
			$this->createMock(AnalysisArtifactMapper::class),
			$this->createMock(AnalysisExportService::class),
			$this->createMock(AnalysisArtifactStorageService::class),
			$this->createMock(AnalysisConfigService::class),
			$this->createMock(AnalysisJobFinalizationService::class),
			$this->createMock(IDBConnection::class),
		);

		$response = $controller->updateStatus($uuid, AnalysisJob::STATUS_LOAD_DATA, 10.0, 'loading', null);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame($updated, $response->getData());
	}

	public function testCreateArtifactsCreatesWholeListInParentOrder(): void {
		$uuid = '11111111-2222-4333-8444-555555555555';
		$job = $this->job($uuid, AnalysisJob::STATUS_WORKER_UPLOAD);
		$parent = $this->artifact(11, 301, null);
		$child = $this->artifact(12, 302, 11);
		$child->setPythonParentId(301);
		$request = $this->request($uuid);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->once())->method('getJobByUuid')->with($uuid)->willReturn($job);
		$jobMapper->expects($this->once())->method('assertPythonAccess')->with($job, 'job-token', $uuid, false, $this->isType('int'));
		$jobMapper->expects($this->once())->method('claimArtifactManifest')->with($job)->willReturn(true);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$createCalls = 0;
		$artifactMapper->expects($this->exactly(2))
			->method('createFromPythonArtifact')
			->willReturnCallback(static function (int $jobId, array $payload, ?int $parentId) use (&$createCalls, $parent, $child): AnalysisArtifact {
				$createCalls++;
				TestCase::assertSame(42, $jobId);
				if ($createCalls === 1) {
					TestCase::assertSame(301, $payload['id']);
					TestCase::assertNull($parentId);
					return $parent;
				}
				TestCase::assertSame(302, $payload['id']);
				TestCase::assertSame(11, $parentId);
				return $child;
			});

		$response = $this->controller($request, $jobMapper, $artifactMapper)->createArtifacts($uuid, [
			['id' => 302, 'parent_id' => 301, 'output_type' => 'PLOT', 'file_name' => 'plot.png', 'file_path' => 'plots/plot.png'],
			['id' => 301, 'output_type' => 'HTML', 'file_name' => 'report.html', 'file_path' => 'report.html'],
		]);

		$this->assertSame(201, $response->getStatus());
		$this->assertSame([
			['id' => 11, 'parent_id' => null, 'python_id' => 301, 'python_parent_id' => null, 'checksum' => null],
			['id' => 12, 'parent_id' => 11, 'python_id' => 302, 'python_parent_id' => 301, 'checksum' => null],
		], $response->getData());
	}

	public function testCreateArtifactsReturnsExistingManifestForDuplicateDelivery(): void {
		$uuid = '11111111-2222-4333-8444-555555555555';
		$job = $this->job($uuid, AnalysisJob::STATUS_JOB_COMPLETED);
		$artifact = $this->artifact(11, 301, null);
		$request = $this->request($uuid);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->once())->method('getJobByUuid')->with($uuid)->willReturn($job);
		$jobMapper->expects($this->once())->method('assertPythonAccess')->with($job, 'job-token', $uuid, false, $this->isType('int'));
		$jobMapper->expects($this->once())->method('claimArtifactManifest')->with($job)->willReturn(false);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$artifactMapper->expects($this->once())->method('getArtifactsForJob')->with(42)->willReturn([$artifact]);

		$response = $this->controller($request, $jobMapper, $artifactMapper)->createArtifacts($uuid, []);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame([['id' => 11, 'parent_id' => null, 'python_id' => 301, 'python_parent_id' => null, 'checksum' => null]], $response->getData());
	}

	public function testCreateArtifactsRollsBackManifestClaimWhenArtifactCreationFails(): void {
		$uuid = '11111111-2222-4333-8444-555555555555';
		$job = $this->job($uuid, AnalysisJob::STATUS_WORKER_UPLOAD);
		$request = $this->request($uuid);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->method('getJobByUuid')->with($uuid)->willReturn($job);
		$jobMapper->method('claimArtifactManifest')->with($job)->willReturn(true);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$artifactMapper->method('createFromPythonArtifact')->willThrowException(new \InvalidArgumentException('invalid artifact'));
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->never())->method('commit');
		$db->expects($this->once())->method('rollBack');

		$response = $this->controller($request, $jobMapper, $artifactMapper, null, null, null, $db)->createArtifacts($uuid, [
			['id' => 1, 'output_type' => 'HTML', 'file_name' => 'report.html', 'file_path' => 'report.html'],
		]);

		$this->assertSame(400, $response->getStatus());
		$this->assertSame(['error' => 'invalid artifact'], $response->getData());
	}

	public function testGetArtifactsReturnsPythonSerializedArtifacts(): void {
		$uuid = '11111111-2222-4333-8444-555555555555';
		$job = $this->job($uuid, AnalysisJob::STATUS_WORKER_UPLOAD);
		$artifact = $this->artifact(11, 301, null);
		$request = $this->request($uuid);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->once())->method('getJobByUuid')->with($uuid)->willReturn($job);
		$jobMapper->expects($this->once())->method('assertPythonAccess')->with($job, 'job-token', $uuid, false, $this->isType('int'));
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$artifactMapper->expects($this->once())->method('getArtifactsForJob')->with(42)->willReturn([$artifact]);

		$response = $this->controller($request, $jobMapper, $artifactMapper)->getArtifacts($uuid);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame([
			['id' => 11, 'parent_id' => null, 'python_id' => 301, 'python_parent_id' => null, 'checksum' => null],
		], $response->getData());
	}

	public function testCreateArtifactsAllowsFailureDetailsAfterJobFailure(): void {
		$uuid = '11111111-2222-4333-8444-555555555555';
		$job = $this->job($uuid, AnalysisJob::STATUS_JOB_FAILED);
		$request = $this->request($uuid);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->once())->method('getJobByUuid')->with($uuid)->willReturn($job);
		$jobMapper->expects($this->once())->method('assertPythonAccess')->with($job, 'job-token', $uuid, false, $this->isType('int'));
		$jobMapper->expects($this->once())->method('claimArtifactManifest')->with($job)->willReturn(true);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);

		$response = $this->controller($request, $jobMapper, $artifactMapper)->createArtifacts($uuid, []);

		$this->assertSame(201, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	public function testFinalizeDelegatesToFinalizationServiceForAuthorizedJob(): void {
		$uuid = '11111111-2222-4333-8444-555555555555';
		$job = $this->job($uuid, AnalysisJob::STATUS_JOB_COMPLETED);
		$finalized = $this->job($uuid, AnalysisJob::STATUS_COMPLETED);
		$request = $this->request($uuid);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->once())->method('getJobByUuid')->with($uuid)->willReturn($job);
		$jobMapper->expects($this->once())->method('assertPythonAccess')->with($job, 'job-token', $uuid, false, $this->isType('int'));
		$finalizationService = $this->createMock(AnalysisJobFinalizationService::class);
		$finalizationService->expects($this->once())->method('finalize')->with($job)->willReturn($finalized);

		$response = $this->controller($request, $jobMapper, $this->createMock(AnalysisArtifactMapper::class), null, null, $finalizationService)->finalize($uuid);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame($finalized, $response->getData());
	}

	public function testUploadArtifactStoresMultipartUploadForMatchingArtifact(): void {
		$uuid = '11111111-2222-4333-8444-555555555555';
		$job = $this->job($uuid, AnalysisJob::STATUS_WORKER_UPLOAD);
		$artifact = $this->artifact(11, 301, null);
		$uploaded = $this->artifact(11, 301, null);
		$uploaded->setDownloaded(true);
		$tmp = tempnam(sys_get_temp_dir(), 'sd-artifact-');
		file_put_contents($tmp, 'artifact-content');
		$request = $this->request($uuid, [
			'tmp_name' => $tmp,
			'error' => UPLOAD_ERR_OK,
		]);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->once())->method('getJobByUuid')->with($uuid)->willReturn($job);
		$jobMapper->expects($this->once())->method('assertPythonAccess')->with($job, 'job-token', $uuid, false, $this->isType('int'));
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$artifactMapper->expects($this->once())->method('getArtifact')->with(11)->willReturn($artifact);
		$storage = $this->createMock(AnalysisArtifactStorageService::class);
		$storage->expects($this->once())->method('storeArtifactContent')->with($job, $artifact, 'artifact-content')->willReturn($uploaded);

		try {
			$response = $this->controller($request, $jobMapper, $artifactMapper, $storage)->uploadArtifact($uuid, 11);
		} finally {
			@unlink($tmp);
		}

		$this->assertSame(200, $response->getStatus());
		$this->assertSame($uploaded, $response->getData());
	}

	private function request(string $uuid, ?array $uploadedFile = null): IRequest {
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturnMap([
			['x-structureddiary-job-token', 'job-token'],
			['x-structureddiary-job-uuid', $uuid],
		]);
		$request->method('getUploadedFile')->with('file')->willReturn($uploadedFile);

		return $request;
	}

	private function controller(
		IRequest $request,
		AnalysisJobMapper $jobMapper,
		AnalysisArtifactMapper $artifactMapper,
		?AnalysisArtifactStorageService $storage = null,
		?AnalysisConfigService $configService = null,
		?AnalysisJobFinalizationService $finalizationService = null,
		?IDBConnection $db = null,
	): PythonAnalysisController {
		return new PythonAnalysisController(
			'structureddiary',
			$request,
			$jobMapper,
			$artifactMapper,
			$this->createMock(AnalysisExportService::class),
			$storage ?? $this->createMock(AnalysisArtifactStorageService::class),
			$configService ?? $this->createMock(AnalysisConfigService::class),
			$finalizationService ?? $this->createMock(AnalysisJobFinalizationService::class),
			$db ?? $this->createMock(IDBConnection::class),
		);
	}

	/**
	 * @param list<string> $methods
	 */
	private function pollingController(AnalysisJobMapper $jobMapper, array $methods = []): PythonAnalysisController {
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->with('x-structureddiary-nextcloud-api-token')->willReturn('nextcloud-token');
		$config = $this->createMock(AnalysisConfigService::class);
		$config->method('getNextcloudApiToken')->willReturn('nextcloud-token');
		$constructorArgs = [
			'structureddiary',
			$request,
			$jobMapper,
			$this->createMock(AnalysisArtifactMapper::class),
			$this->createMock(AnalysisExportService::class),
			$this->createMock(AnalysisArtifactStorageService::class),
			$config,
			$this->createMock(AnalysisJobFinalizationService::class),
			$this->createMock(IDBConnection::class),
		];
		if ($methods === []) {
			return new PythonAnalysisController(...$constructorArgs);
		}

		return $this->getMockBuilder(PythonAnalysisController::class)
			->setConstructorArgs($constructorArgs)
			->onlyMethods($methods)
			->getMock();
	}

	private function job(string $uuid, string $status): AnalysisJob {
		$job = new AnalysisJob();
		$job->setId(42);
		$job->setUuid($uuid);
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
		$job->setToken('job-token');
		$job->setStoragePath('/StructuredDiary/Analyses/Diary-7/Report-42');
		$job->setStatusMessage('');
		$job->setArtifactsDownloaded(false);
		$job->setPythonDeleted(false);

		return $job;
	}

	private function artifact(int $id, int $pythonFileId, ?int $parentId): AnalysisArtifact {
		$artifact = new AnalysisArtifact();
		$artifact->setId($id);
		$artifact->setParentId($parentId);
		$artifact->setJobId(42);
		$artifact->setArtifactType('HTML');
		$artifact->setMimeType('text/html');
		$artifact->setFileName('report.html');
		$artifact->setFilePath('report.html');
		$artifact->setPythonFileId($pythonFileId);
		$artifact->setSize(16);
		$artifact->setCreatedAt(1234);
		$artifact->setDownloaded(false);

		return $artifact;
	}
}
