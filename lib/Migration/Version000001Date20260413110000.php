<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Migration;

use Closure;
use OCA\StructuredDiary\Db\TableNames;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000001Date20260413110000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable(TableNames::DIARIES)) {
			$table = $schema->createTable(TableNames::DIARIES);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('description', Types::TEXT, ['notnull' => true]);
			$table->addColumn('reminder_active', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$table->addColumn('reminder_time', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('reminder_count', Types::INTEGER, ['notnull' => true, 'default' => 3]);
			$table->addColumn('reminder_delay', Types::INTEGER, ['notnull' => true, 'default' => 2700]);
			$table->addColumn('reminder_signal_first', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$table->addColumn('reminder_signal_repeat', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$table->addColumn('entry_schedule', Types::INTEGER, ['notnull' => true, 'default' => 86400]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'sd_diary_user_idx');
		}

		if (!$schema->hasTable(TableNames::DIARY_SHARES)) {
			$table = $schema->createTable(TableNames::DIARY_SHARES);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('diary_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('shared_with', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('permission', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['diary_id', 'shared_with'], 'sd_diary_share_unique');
			$table->addIndex(['shared_with'], 'sd_diary_share_user_idx');
		}

		if (!$schema->hasTable(TableNames::ENTRIES)) {
			$table = $schema->createTable(TableNames::ENTRIES);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('diary_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('timestamp', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('title', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['diary_id', 'timestamp'], 'sd_entry_diary_date_idx');
		}

		if (!$schema->hasTable(TableNames::QUESTIONS)) {
			$table = $schema->createTable(TableNames::QUESTIONS);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('chain_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('diary_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('diary_question_order', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('display_text', Types::TEXT, ['notnull' => true]);
			$table->addColumn('type', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('minimum', Types::FLOAT, ['notnull' => false]);
			$table->addColumn('maximum', Types::FLOAT, ['notnull' => false]);
			$table->addColumn('json_choices', Types::TEXT, ['notnull' => false]);
			$table->addColumn('active', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$table->addColumn('template_text', Types::TEXT, ['notnull' => true, 'default' => '']);
			$table->addColumn('previous_version_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('next_version_id', Types::BIGINT, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['diary_id'], 'sd_question_diary_idx');
            $table->addIndex(['diary_question_order'], 'sd_question_diary_question_idx');
			$table->addIndex(['next_version_id'], 'sd_question_next_idx');
		}

		if (!$schema->hasTable(TableNames::ANSWERS)) {
			$table = $schema->createTable(TableNames::ANSWERS);
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('diary_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('entry_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('question_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('text_content', Types::TEXT, ['notnull' => false]);
			$table->addColumn('numeric_content', Types::FLOAT, ['notnull' => false]);
			$table->addColumn('previous_version_id', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('next_version_id', Types::BIGINT, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['entry_id'], 'sd_answer_entry_idx');
			$table->addIndex(['question_id'], 'sd_answer_question_idx');
			$table->addIndex(['next_version_id'], 'sd_answer_next_idx');
		}

		$diariesTable = $schema->getTable(TableNames::DIARIES);
		$sharesTable = $schema->getTable(TableNames::DIARY_SHARES);
		$entriesTable = $schema->getTable(TableNames::ENTRIES);
		$questionsTable = $schema->getTable(TableNames::QUESTIONS);
		$answersTable = $schema->getTable(TableNames::ANSWERS);

		if (!$sharesTable->hasForeignKey('sd_share_diary_fk')) {
			$sharesTable->addForeignKeyConstraint($diariesTable, ['diary_id'], ['id'], [
				'onDelete' => 'CASCADE',
			], 'sd_share_diary_fk');
		}

		if (!$entriesTable->hasForeignKey('sd_entry_diary_fk')) {
			$entriesTable->addForeignKeyConstraint($diariesTable, ['diary_id'], ['id'], [
				'onDelete' => 'CASCADE',
			], 'sd_entry_diary_fk');
		}

		if (!$questionsTable->hasForeignKey('sd_question_diary_fk')) {
			$questionsTable->addForeignKeyConstraint($diariesTable, ['diary_id'], ['id'], [
				'onDelete' => 'CASCADE',
			], 'sd_question_diary_fk');
		}

		if (!$questionsTable->hasForeignKey('sd_question_prev_fk')) {
			$questionsTable->addForeignKeyConstraint($questionsTable, ['previous_version_id'], ['id'], [
				'onDelete' => 'RESTRICT',
			], 'sd_question_prev_fk');
		}

		if (!$questionsTable->hasForeignKey('sd_question_next_fk')) {
			$questionsTable->addForeignKeyConstraint($questionsTable, ['next_version_id'], ['id'], [
				'onDelete' => 'RESTRICT',
			], 'sd_question_next_fk');
		}

		if (!$answersTable->hasForeignKey('sd_answer_diary_fk')) {
			$answersTable->addForeignKeyConstraint($diariesTable, ['diary_id'], ['id'], [
				'onDelete' => 'CASCADE',
			], 'sd_answer_diary_fk');
		}

		if (!$answersTable->hasForeignKey('sd_answer_entry_fk')) {
			$answersTable->addForeignKeyConstraint($entriesTable, ['entry_id'], ['id'], [
				'onDelete' => 'CASCADE',
			], 'sd_answer_entry_fk');
		}

		if (!$answersTable->hasForeignKey('sd_answer_question_fk')) {
			$answersTable->addForeignKeyConstraint($questionsTable, ['question_id'], ['id'], [
				'onDelete' => 'RESTRICT',
			], 'sd_answer_question_fk');
		}

		if (!$answersTable->hasForeignKey('sd_answer_prev_fk')) {
			$answersTable->addForeignKeyConstraint($answersTable, ['previous_version_id'], ['id'], [
				'onDelete' => 'RESTRICT',
			], 'sd_answer_prev_fk');
		}

		if (!$answersTable->hasForeignKey('sd_answer_next_fk')) {
			$answersTable->addForeignKeyConstraint($answersTable, ['next_version_id'], ['id'], [
				'onDelete' => 'RESTRICT',
			], 'sd_answer_next_fk');
		}

		return $schema;
	}
}
