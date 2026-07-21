<?php

declare(strict_types=1);

namespace Migration;

use Doctrine\DBAL\Schema\Table;
use OCA\StructuredDiary\Db\TableNames;
use OCA\StructuredDiary\Migration\Version000004Date20260711120000;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

final class Version000004Date20260711120000Test extends TestCase {
	public function testMigrationAddsIndexAndDropsOnlyObsoleteRetryColumns(): void {
		$jobs = new Table(TableNames::ANALYSIS_JOBS);
		$jobs->addColumn('updated_at', Types::BIGINT);
		$jobs->addColumn('artifacts_download_fails', Types::INTEGER);
		$jobs->addColumn('artifacts_download_last_fail_at', Types::BIGINT);
		$jobs->addColumn('python_deleted_fails', Types::INTEGER);
		$jobs->addColumn('python_deleted_last_fail_at', Types::BIGINT);
		$jobs->addColumn('preserved_column', Types::STRING);
		$artifacts = new Table(TableNames::ANALYSIS_ARTIFACTS);
		$artifacts->addColumn('download_fails', Types::INTEGER);
		$artifacts->addColumn('download_last_fail_at', Types::BIGINT);
		$artifacts->addColumn('preserved_column', Types::STRING);
		$schema = $this->schema([$jobs, $artifacts]);

		$result = (new Version000004Date20260711120000())->changeSchema(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[],
		);

		$this->assertSame($schema, $result);
		$this->assertTrue($jobs->hasIndex('sd_analysis_job_updated_idx'));
		$this->assertTrue($jobs->hasColumn('preserved_column'));
		$this->assertFalse($jobs->hasColumn('artifacts_download_fails'));
		$this->assertFalse($jobs->hasColumn('artifacts_download_last_fail_at'));
		$this->assertFalse($jobs->hasColumn('python_deleted_fails'));
		$this->assertFalse($jobs->hasColumn('python_deleted_last_fail_at'));
		$this->assertTrue($artifacts->hasColumn('preserved_column'));
		$this->assertFalse($artifacts->hasColumn('download_fails'));
		$this->assertFalse($artifacts->hasColumn('download_last_fail_at'));
	}

	public function testMigrationIsSafeWhenAnalysisTablesOrIndexAreAlreadyAbsentOrPresent(): void {
		$jobs = new Table(TableNames::ANALYSIS_JOBS);
		$jobs->addColumn('updated_at', Types::BIGINT);
		$jobs->addIndex(['updated_at'], 'sd_analysis_job_updated_idx');
		$schema = $this->schema([$jobs]);

		(new Version000004Date20260711120000())->changeSchema(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[],
		);

		$this->assertTrue($jobs->hasIndex('sd_analysis_job_updated_idx'));
	}

	/**
	 * @param list<Table> $tables
	 */
	private function schema(array $tables): ISchemaWrapper {
		$byName = [];
		foreach ($tables as $table) {
			$byName[$table->getName()] = $table;
		}
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturnCallback(static fn (string $name): bool => isset($byName[$name]));
		$schema->method('getTable')->willReturnCallback(static fn (string $name): Table => $byName[$name]);

		return $schema;
	}
}
