<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Migration;

use Closure;
use OCA\StructuredDiary\Db\TableNames;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000003Date20260702120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable(TableNames::ANALYSIS_JOBS)) {
			$table = $schema->createTable(TableNames::ANALYSIS_JOBS);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('diary_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('uuid', Types::STRING, ['notnull' => true, 'length' => 36]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('data_from', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('data_until', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('started_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('finished_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('language', Types::STRING, ['notnull' => true, 'length' => 35]);
			$table->addColumn('analysis_type', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'standard']);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'DRAFT']);
			$table->addColumn('progress', Types::FLOAT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('output_types', Types::TEXT, ['notnull' => true]);
			$table->addColumn('parameters_json', Types::TEXT, ['notnull' => true]);
			$table->addColumn('llm_url', Types::STRING, ['notnull' => false, 'length' => 1024]);
			$table->addColumn('llm_header', Types::TEXT, ['notnull' => false]);
			$table->addColumn('python_job_id', Types::STRING, ['notnull' => false, 'length' => 128]);
			$table->addColumn('token', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('storage_path', Types::STRING, ['notnull' => true, 'length' => 1024]);
			$table->addColumn('manifest_path', Types::STRING, ['notnull' => false, 'length' => 1024]);
			$table->addColumn('status_message', Types::TEXT, ['notnull' => true, 'default' => '']);
			$table->addColumn('error_message', Types::TEXT, ['notnull' => false]);
			$table->addColumn('cancel_requested_at', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('artifacts_downloaded', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('python_deleted', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['diary_id'], 'sd_analysis_job_diary_idx');
			$table->addIndex(['created_by'], 'sd_analysis_job_user_idx');
			$table->addIndex(['created_at'], 'sd_analysis_job_created_idx');
			$table->addIndex(['status'], 'sd_analysis_job_status_idx');
            $table->addIndex(['updated_at'], 'sd_analysis_job_updated_idx');
			$table->addUniqueIndex(['token'], 'sd_analysis_job_token_unique');
			$table->addUniqueIndex(['storage_path'], 'sd_analysis_job_path_unique');
			$table->addUniqueIndex(['uuid'], 'sd_analysis_job_uuid_unique');
		}

		if (!$schema->hasTable(TableNames::ANALYSIS_ARTIFACTS)) {
			$table = $schema->createTable(TableNames::ANALYSIS_ARTIFACTS);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('parent_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('job_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('artifact_type', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('mime_type', Types::STRING, ['notnull' => true, 'length' => 128]);
			$table->addColumn('file_name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('file_path', Types::STRING, ['notnull' => true, 'length' => 4000]);
			$table->addColumn('file_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('python_parent_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('python_file_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('size', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('checksum', Types::STRING, ['notnull' => false, 'length' => 128]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('downloaded', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['parent_id'], 'sd_analysis_art_parent_idx');
			$table->addIndex(['job_id'], 'sd_analysis_art_job_idx');
			$table->addIndex(['artifact_type'], 'sd_analysis_art_type_idx');
			$table->addUniqueIndex(['job_id', 'file_path'], 'sd_analysis_art_path_unique');
		}

		$diariesTable = $schema->getTable(TableNames::DIARIES);
		$jobsTable = $schema->getTable(TableNames::ANALYSIS_JOBS);
		$artifactsTable = $schema->getTable(TableNames::ANALYSIS_ARTIFACTS);

		if (!$jobsTable->hasForeignKey('sd_analysis_job_diary_fk')) {
			$jobsTable->addForeignKeyConstraint($diariesTable, ['diary_id'], ['id'], [
				'onDelete' => 'CASCADE',
			], 'sd_analysis_job_diary_fk');
		}
		if (!$artifactsTable->hasForeignKey('sd_analysis_art_job_fk')) {
			$artifactsTable->addForeignKeyConstraint($jobsTable, ['job_id'], ['id'], [
				'onDelete' => 'CASCADE',
			], 'sd_analysis_art_job_fk');
		}
		if (!$artifactsTable->hasForeignKey('sd_analysis_art_parent_fk')) {
			$artifactsTable->addForeignKeyConstraint($artifactsTable, ['parent_id'], ['id'], [
				'onDelete' => 'CASCADE',
			], 'sd_analysis_art_parent_fk');
		}
		return $schema;
	}
}
