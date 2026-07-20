<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Migration;

use Closure;
use OCA\StructuredDiary\Db\TableNames;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000004Date20260711120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable(TableNames::ANALYSIS_JOBS)) {
			$table = $schema->getTable(TableNames::ANALYSIS_JOBS);
			if (!$table->hasIndex('sd_analysis_job_updated_idx')) {
				$table->addIndex(['updated_at'], 'sd_analysis_job_updated_idx');
			}
			foreach (['artifacts_download_fails', 'artifacts_download_last_fail_at', 'python_deleted_fails', 'python_deleted_last_fail_at'] as $column) {
				if ($table->hasColumn($column)) {
					$table->dropColumn($column);
				}
			}
		}

		if ($schema->hasTable(TableNames::ANALYSIS_ARTIFACTS)) {
			$table = $schema->getTable(TableNames::ANALYSIS_ARTIFACTS);
			foreach (['download_fails', 'download_last_fail_at'] as $column) {
				if ($table->hasColumn($column)) {
					$table->dropColumn($column);
				}
			}
		}

		return $schema;
	}
}
