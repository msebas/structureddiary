<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Migration;

use Closure;
use OCA\StructuredDiary\Db\TableNames;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\IDBConnection;

class Version000005Date20260806120000 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		foreach ([
			TableNames::DIARIES => 'sd_diary_client_mutation_unique',
			TableNames::ENTRIES => 'sd_entry_client_mutation_unique',
			TableNames::QUESTIONS => 'sd_question_client_mutation_unique',
			TableNames::ANSWERS => 'sd_answer_client_mutation_unique',
		] as $tableName => $indexName) {
			if (!$schema->hasTable($tableName)) {
				continue;
			}
			$table = $schema->getTable($tableName);
            if (!$table->hasColumn('revision_count')) {
                $table->addColumn('revision_count', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            }
			if (!$table->hasColumn('client_mutation_id')) {
				$table->addColumn('client_mutation_id', Types::STRING, ['notnull' => false, 'length' => 64]);
			}
			if (!$table->hasIndex($indexName)) {
				$table->addUniqueIndex(['client_mutation_id'], $indexName);
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->backfillRevisionCounts(TableNames::QUESTIONS);
		$this->backfillRevisionCounts(TableNames::ANSWERS);
	}

	/**
	 * Legacy questions and answers are version chains. Number every remaining
	 * version in chain order so clients can use the current version's revision
	 * as an optimistic-concurrency token even after historic versions were removed.
	 */
	private function backfillRevisionCounts(string $tableName): void {
		$qb = $this->db->getQueryBuilder();
		$rows = $qb->select('id', 'previous_version_id', 'next_version_id')
			->from($tableName)
			->executeQuery()
			->fetchAll();

		$byPreviousId = [];
		foreach ($rows as $row) {
			$previousId = $row['previous_version_id'] === null ? null : (int)$row['previous_version_id'];
			$byPreviousId[$previousId ?? 'root'][] = (int)$row['id'];
		}

		$visited = [];
		foreach ($byPreviousId['root'] ?? [] as $rootId) {
			$this->backfillChain($tableName, $rootId, $byPreviousId, $visited);
		}
		// Broken historic chains are still made usable rather than left at 1.
		foreach ($rows as $row) {
			$id = (int)$row['id'];
			if (!isset($visited[$id])) {
				$this->backfillChain($tableName, $id, $byPreviousId, $visited);
			}
		}
	}

	/** @param array<int|string, list<int>> $byPreviousId @param array<int, true> $visited */
	private function backfillChain(string $tableName, int $firstId, array $byPreviousId, array &$visited): void {
		$currentId = $firstId;
		$revisionCount = 1;
		while (!isset($visited[$currentId])) {
			$visited[$currentId] = true;
			$qb = $this->db->getQueryBuilder();
			$qb->update($tableName)
				->set('revision_count', $qb->createNamedParameter($revisionCount, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($currentId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();

			$nextIds = $byPreviousId[$currentId] ?? [];
			if ($nextIds === []) {
				return;
			}
			$currentId = $nextIds[0];
			$revisionCount++;
		}
	}
}
