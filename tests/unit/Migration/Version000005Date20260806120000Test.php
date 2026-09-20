<?php

declare(strict_types=1);

namespace Migration;

use Doctrine\DBAL\Schema\Table;
use OCA\StructuredDiary\Db\TableNames;
use OCA\StructuredDiary\Migration\Version000005Date20260806120000;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

final class Version000005Date20260806120000Test extends TestCase {
	public function testMigrationAddsNullableUniqueClientMutationIdToSyncEntities(): void {
		$tables = [];
		foreach ([TableNames::DIARIES, TableNames::ENTRIES, TableNames::QUESTIONS, TableNames::ANSWERS] as $name) {
			$tables[$name] = new Table($name);
		}
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturnCallback(static fn (string $name): bool => isset($tables[$name]));
		$schema->method('getTable')->willReturnCallback(static fn (string $name): Table => $tables[$name]);

		(new Version000005Date20260806120000($this->createMock(IDBConnection::class)))->changeSchema($this->createMock(IOutput::class), static fn (): ISchemaWrapper => $schema, []);

		foreach ($tables as $table) {
			$this->assertTrue($table->hasColumn('client_mutation_id'));
			$this->assertFalse($table->getColumn('client_mutation_id')->getNotnull());
			$this->assertCount(1, array_filter($table->getIndexes(), static fn ($index): bool => $index->isUnique()));
		}
	}
}
