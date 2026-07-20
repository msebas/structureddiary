<?php

declare(strict_types=1);

namespace Cron;

use OCA\StructuredDiary\Cron\AnalysisJobFinalizationCron;
use OCA\StructuredDiary\Service\AnalysisJobFinalizationService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

final class AnalysisJobFinalizationCronTest extends TestCase {
	public function testRunDelegatesToFinalizationService(): void {
		$finalizationService = $this->createMock(AnalysisJobFinalizationService::class);
		$finalizationService->expects($this->once())->method('finalizePending');
		$cron = new class($this->createMock(ITimeFactory::class), $finalizationService) extends AnalysisJobFinalizationCron {
			public function runNow(): void {
				$this->run(null);
			}
		};

		$cron->runNow();
	}
}
