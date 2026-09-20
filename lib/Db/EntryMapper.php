<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use OCP\AppFramework\Db\DoesNotExistException;
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
	public function getEntriesForDiary(int $diaryId, ?int $fromTimestamp = null, ?int $untilTimestamp = null, ?int $limit = null, ?int $offset = null): array {
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
		if ($limit !== null) {
			$qb->setMaxResults(max(1, $limit));
		}
		if ($offset !== null) {
			$qb->setFirstResult(max(0, $offset));
		}

		return $this->findEntities($qb);
	}

	/**
	 * @throws Exception
	 */
	/** @throws Exception */
	public function findByClientMutationId(string $clientMutationId): ?Entry {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('client_mutation_id', $qb->createNamedParameter($clientMutationId)))
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @throws Exception */
	public function createEntry(int $diaryId, int $timestamp, ?string $title, ?string $clientMutationId = null): Entry {
		$entry = new Entry();
		$entry->setDiaryId($diaryId);
		$entry->setTimestamp($timestamp);
		$entry->setTitle($title);
		$entry->setClientMutationId($clientMutationId);
		$entry->setRevisionCount(1);

		try {
			return $this->insert($entry);
		} catch (Exception $e) {
			if ($clientMutationId === null || ($existing = $this->findByClientMutationId($clientMutationId)) === null) {
				throw $e;
			}
			return $existing;
		}
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
		$entry->setRevisionCount($entry->getRevisionCount() + 1);

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
