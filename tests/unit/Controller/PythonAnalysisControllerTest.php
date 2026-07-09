<?php

declare(strict_types=1);

namespace Controller;

use OCA\StructuredDiary\Controller\PythonAnalysisController;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCA\StructuredDiary\Service\AnalysisExportService;
use OCA\StructuredDiary\Service\AnalysisJobProcessingService;
use OCP\IRequest;
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
			['updateStatus'],
			['diary'],
			['entries'],
		];
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
		$processingService = $this->createMock(AnalysisJobProcessingService::class);
		$processingService->expects($this->once())->method('handleTerminalPythonStatusUpdate')->with($updated);

		$controller = new PythonAnalysisController(
			'structureddiary',
			$request,
			$jobMapper,
			$this->createMock(AnalysisExportService::class),
			$this->createMock(AnalysisConfigService::class),
			$processingService,
		);

		$response = $controller->updateStatus($uuid, AnalysisJob::STATUS_LOAD_DATA, 10.0, 'loading', null);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame($updated, $response->getData());
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
}
