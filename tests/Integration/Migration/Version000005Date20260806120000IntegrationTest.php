<?php

declare(strict_types=1);

namespace OCA\Tests\StructuredDiary\Integration\Migration;

use OCA\StructuredDiary\Db\TableNames;
use OCA\Tests\StructuredDiary\Integration\TestUtil\IntegrationTestParentClass;

final class Version000005Date20260806120000IntegrationTest extends IntegrationTestParentClass {
	public function testLatestSchemaHasUniqueClientMutationIds(): void {
		$schemaManager = self::$connection->createSchemaManager();
		foreach ([TableNames::DIARIES, TableNames::ENTRIES, TableNames::QUESTIONS, TableNames::ANSWERS] as $name) {
			$table = $schemaManager->introspectTable('oc_' . $name);
			$this->assertTrue($table->hasColumn('client_mutation_id'));
			$this->assertFalse($table->getColumn('client_mutation_id')->getNotnull());
		}
	}
}
