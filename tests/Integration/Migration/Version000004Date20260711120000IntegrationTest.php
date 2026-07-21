<?php

declare(strict_types=1);

namespace OCA\Tests\StructuredDiary\Integration\Migration;

use OCA\StructuredDiary\Db\TableNames;
use OCA\Tests\StructuredDiary\Integration\TestUtil\IntegrationTestParentClass;

final class Version000004Date20260711120000IntegrationTest extends IntegrationTestParentClass {
	public function testLatestSchemaContainsFinalizerIndexAndNoRemovedRetryColumns(): void {
		$schemaManager = self::$connection->createSchemaManager();
		$jobs = $schemaManager->introspectTable('oc_' . TableNames::ANALYSIS_JOBS);
		$artifacts = $schemaManager->introspectTable('oc_' . TableNames::ANALYSIS_ARTIFACTS);

		$this->assertTrue($jobs->hasIndex('sd_analysis_job_updated_idx'));
		foreach (['artifacts_download_fails', 'artifacts_download_last_fail_at', 'python_deleted_fails', 'python_deleted_last_fail_at'] as $column) {
			$this->assertFalse($jobs->hasColumn($column), $column . ' must be removed by migration 000004.');
		}
		foreach (['download_fails', 'download_last_fail_at'] as $column) {
			$this->assertFalse($artifacts->hasColumn($column), $column . ' must be removed by migration 000004.');
		}
	}
}
