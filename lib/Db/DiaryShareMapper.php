<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class DiaryShareMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, TableNames::DIARY_SHARES, DiaryShare::class);
	}

	/**
	 * @return list<DiaryShare>
	 * @throws Exception
	 */
	public function getSharesForDiary(int $diaryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('diary_id', $qb->createNamedParameter($diaryId, IQueryBuilder::PARAM_INT))
			)
			->orderBy('shared_with', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @throws Exception
	 */
	public function upsertShare(int $diaryId, string $sharedWith, int $permission): DiaryShare {
		try {
			$share = $this->findShareForDiaryAndUser($diaryId, $sharedWith);
			$share->setPermission($permission);

			return $this->update($share);
		} catch (DoesNotExistException | MultipleObjectsReturnedException) {
			$share = new DiaryShare();
			$share->setDiaryId($diaryId);
			$share->setSharedWith($sharedWith);
			$share->setPermission($permission);

			return $this->insert($share);
		}
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getShare(int $id): DiaryShare {
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
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function deleteShare(int $id): DiaryShare {
		$share = $this->getShare($id);
		$this->delete($share);

		return $share;
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	private function findShareForDiaryAndUser(int $diaryId, string $sharedWith): DiaryShare {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('diary_id', $qb->createNamedParameter($diaryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('shared_with', $qb->createNamedParameter($sharedWith, IQueryBuilder::PARAM_STR))
			)
			->setMaxResults(1);

		return $this->findEntity($qb);
	}
}
