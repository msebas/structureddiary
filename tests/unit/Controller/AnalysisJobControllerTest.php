<?php

declare(strict_types=1);

namespace Controller;

use OCA\StructuredDiary\Controller\AnalysisJobController;
use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Service\AnalysisArtifactArchiveService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AnalysisJobControllerTest extends TestCase {
	public function testIndexReturnsChangedJobsImmediately(): void {
		$job = $this->job(42);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->once())
			->method('getJobsForUser')
			->with('alice', 1713254400)
			->willReturn([$job]);

		$controller = $this->controller($jobMapper, ['waitBeforeNextLongPollCheck']);
		$controller->expects($this->never())->method('waitBeforeNextLongPollCheck');

		$response = $controller->index('2024-04-16T08:00:00Z', 30);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([$job], $response->getData());
	}

	public function testIndexLongPollsUntilChangedJobsAppear(): void {
		$job = $this->job(42);
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->exactly(2))
			->method('getJobsForUser')
			->with('alice', 1713254400)
			->willReturnOnConsecutiveCalls([], [$job]);

		$controller = $this->controller($jobMapper, ['getCurrentTimestamp', 'waitBeforeNextLongPollCheck']);
		$controller->expects($this->exactly(2))->method('getCurrentTimestamp')->willReturn(1000);
		$controller->expects($this->once())->method('waitBeforeNextLongPollCheck');

		$response = $controller->index('2024-04-16T08:00:00Z', 30);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([$job], $response->getData());
	}

	public function testIndexReturnsEmptyListWhenLongPollTimeoutExpires(): void {
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->exactly(2))
			->method('getJobsForUser')
			->with('alice', 1713254400)
			->willReturn([]);

		$controller = $this->controller($jobMapper, ['getCurrentTimestamp', 'waitBeforeNextLongPollCheck']);
		$controller->expects($this->exactly(3))->method('getCurrentTimestamp')->willReturnOnConsecutiveCalls(1000, 1000, 1001);
		$controller->expects($this->once())->method('waitBeforeNextLongPollCheck');

		$response = $controller->index('2024-04-16T08:00:00Z', 1);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	public function testIndexAcceptsWaitAliasForLongPollTimeout(): void {
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->exactly(2))
			->method('getJobsForUser')
			->with('alice', 1713254400)
			->willReturn([]);

		$controller = $this->controller($jobMapper, ['getCurrentTimestamp', 'waitBeforeNextLongPollCheck']);
		$controller->expects($this->exactly(3))->method('getCurrentTimestamp')->willReturnOnConsecutiveCalls(1000, 1000, 1001);
		$controller->expects($this->once())->method('waitBeforeNextLongPollCheck');

		$response = $controller->index('2024-04-16T08:00:00Z', null, 1);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	public function testIndexRejectsInvalidLongPollTimeout(): void {
		$jobMapper = $this->createMock(AnalysisJobMapper::class);
		$jobMapper->expects($this->never())->method('getJobsForUser');

		$controller = $this->controller($jobMapper);

		$response = $controller->index('2024-04-16T08:00:00Z', 'soon');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'longPollTimeout must be a non-negative integer.'], $response->getData());
	}

	/**
	 * @dataProvider directDownloadMethods
	 */
	public function testDirectDownloadEndpointsAreCsrfExempt(string $method): void {
		$reflection = new ReflectionMethod(AnalysisJobController::class, $method);

		$this->assertNotEmpty($reflection->getAttributes(NoCSRFRequired::class), $method . ' must be usable as a direct browser download link.');
	}

	/**
	 * @return list<array{string}>
	 */
	public static function directDownloadMethods(): array {
		return [
			['downloadArtifacts'],
			['downloadArtifactsByType'],
			['artifactContent'],
		];
	}

	/**
	 * @param list<string> $methods
	 */
	private function controller(AnalysisJobMapper $jobMapper, array $methods = []): AnalysisJobController {
		$constructorArgs = [
			'structureddiary',
			$this->createMock(IRequest::class),
			$jobMapper,
			$this->createMock(AnalysisArtifactMapper::class),
			$this->createMock(AnalysisArtifactArchiveService::class),
			'alice',
		];

		if ($methods !== []) {
			return $this->getMockBuilder(AnalysisJobController::class)
				->setConstructorArgs($constructorArgs)
				->onlyMethods($methods)
				->getMock();
		}

		return new AnalysisJobController(...$constructorArgs);
	}

	private function job(int $id): AnalysisJob {
		$job = new AnalysisJob();
		$job->setId($id);

		return $job;
	}
}
