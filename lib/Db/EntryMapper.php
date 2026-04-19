<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class EntryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, TableNames::ENTRIES, Entry::class);
	}

	/**
	 * @throws Exception
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function getEntry(int $id): Entry {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	/**
	 * @return list<Entry>
	 * @throws Exception
	 */
	public function getEntriesForDiary(int $diaryId, ?int $fromTimestamp = null, ?int $untilTimestamp = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('diary_id', $qb->createNamedParameter($diaryId, IQueryBuilder::PARAM_INT))
			);
		if ($fromTimestamp !== null) {
			$qb->andWhere(
				$qb->expr()->gte('timestamp', $qb->createNamedParameter($fromTimestamp, IQueryBuilder::PARAM_INT))
			);
		}
		if ($untilTimestamp !== null) {
			$qb->andWhere(
				$qb->expr()->lte('timestamp', $qb->createNamedParameter($untilTimestamp, IQueryBuilder::PARAM_INT))
			);
		}
		$qb
			->orderBy('timestamp', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * @throws Exception
	 */
	public function createEntry(int $diaryId, int $timestamp, ?string $title): Entry {
		$entry = new Entry();
		$entry->setDiaryId($diaryId);
		$entry->setTimestamp($timestamp);
		$entry->setTitle($title);

		return $this->insert($entry);
	}

	/**
	 * @throws Exception
	 */
	public function updateEntry(Entry $entry, ?int $timestamp, ?string $title): Entry {
		if ($timestamp !== null) {
			$entry->setTimestamp($timestamp);
		}
		if ($title !== null) {
			$entry->setTitle($title);
		}

		return $this->update($entry);
	}

	/**
	 * @throws Exception
	 */
	public function deleteEntry(Entry $entry): Entry {
		$this->delete($entry);

		return $entry;
	}
}
