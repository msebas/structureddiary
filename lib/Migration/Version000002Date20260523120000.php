<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Migration;

use Closure;
use OCA\StructuredDiary\Db\TableNames;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000002Date20260523120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable(TableNames::ALARM_SOUNDS)) {
			return null;
		}

		$table = $schema->createTable(TableNames::ALARM_SOUNDS);
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('path', Types::STRING, ['notnull' => false, 'length' => 1024]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('last_seen_at', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('is_default', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
		$table->addColumn('os_affinity', Types::TEXT, ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['name'], 'sd_alarm_name_idx');
		$table->addIndex(['is_default'], 'sd_alarm_default_idx');

		return $schema;
	}
}
