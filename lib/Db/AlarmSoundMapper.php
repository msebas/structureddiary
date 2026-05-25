<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class AlarmSoundMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, TableNames::ALARM_SOUNDS, AlarmSound::class);
	}

	/**
	 * @return list<AlarmSound>
	 * @throws Exception
	 */
	public function getAlarmSounds(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('is_default', 'DESC')
			->addOrderBy('name', 'ASC')
			->addOrderBy('path', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getAlarmSound(int $id): AlarmSound {
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
	 * @param list<string> $osAffinity
	 * @throws Exception
	 */
	public function upsertAlarmSound(string $name, ?string $path, array $osAffinity, bool $isDefault = false): AlarmSound {
		$name = $this->normalizeName($name);
		$path = $this->normalizePath($path);
		$now = $this->getCurrentTimestamp();
		$match = $this->findCompatibleAlarmSound($name, $path);

		if ($match !== null) {
			$mergedAffinity = $this->mergeOsAffinity($match->getOsAffinityList(), $osAffinity);
			$match->setLastSeenAt($now);
			if ($path !== null && $match->getPath() === null) {
				$match->setPath($path);
			}
			$match->setOsAffinityList($mergedAffinity);
			if ($match->getIsDefault() || $isDefault) {
				$this->clearCompatibleDefaultAlarmSounds($mergedAffinity, $match->getId());
			}
			if ($isDefault) {
				$match->setIsDefault(true);
			}

			return $this->update($match);
		}

		$sound = new AlarmSound();
		$sound->setName($name);
		$sound->setPath($path);
		$sound->setCreatedAt($now);
		$sound->setLastSeenAt($now);
		$sound->setIsDefault($isDefault);
		$osAffinity = AlarmSound::normalizeOsAffinity($osAffinity);
		$sound->setOsAffinityList($osAffinity);

		if ($isDefault) {
			$this->clearCompatibleDefaultAlarmSounds($osAffinity);
		}

		return $this->insert($sound);
	}

	/**
	 * @param list<string>|null $osAffinity
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function updateAlarmSound(int $id, ?string $name, ?string $path, ?array $osAffinity, ?bool $isDefault): AlarmSound {
		$current = $this->getAlarmSound($id);
		$targetName = $name === null ? $current->getName() : $this->normalizeName($name);
		$targetPath = $path === null ? $current->getPath() : $this->normalizePath($path);
		$targetAffinity = $osAffinity === null
			? $current->getOsAffinityList()
			: $this->mergeOsAffinity($current->getOsAffinityList(), $osAffinity);

		$match = $this->findCompatibleAlarmSound($targetName, $targetPath, $id);
		if ($match !== null) {
			$mergedAffinity = $this->mergeOsAffinity($match->getOsAffinityList(), $targetAffinity);
			$match->setLastSeenAt($this->getCurrentTimestamp());
			if ($targetPath !== null && $match->getPath() === null) {
				$match->setPath($targetPath);
			}
			$match->setOsAffinityList($mergedAffinity);
			$matchWillBeDefault = $isDefault === true || ($isDefault !== false && $match->getIsDefault());
			if ($matchWillBeDefault) {
				$this->clearCompatibleDefaultAlarmSounds($mergedAffinity, $match->getId());
			}
			if ($isDefault === true) {
				$match->setIsDefault(true);
			} elseif ($isDefault === false) {
				$match->setIsDefault(false);
			}
			$this->delete($current);

			return $this->update($match);
		}

		$current->setName($targetName);
		$current->setPath($targetPath);
		$current->setLastSeenAt($this->getCurrentTimestamp());
		if ($osAffinity !== null) {
			$current->setOsAffinityList($targetAffinity);
		}
		$currentWillBeDefault = $isDefault === true || ($isDefault !== false && $current->getIsDefault());
		if ($currentWillBeDefault) {
			$this->clearCompatibleDefaultAlarmSounds($targetAffinity, $current->getId());
		}
		if ($isDefault === true) {
			$current->setIsDefault(true);
		} elseif ($isDefault === false) {
			$current->setIsDefault(false);
		}

		return $this->update($current);
	}

	/**
	 * @param list<string> $previous
	 * @param list<string> $next
	 * @return list<string>
	 */
	private function mergeOsAffinity(array $previous, array $next): array {
		return AlarmSound::normalizeOsAffinity([...$previous, ...$next]);
	}

	private function normalizeName(string $name): string {
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('Alarm sound name is required.');
		}

		return $name;
	}

	private function normalizePath(?string $path): ?string {
		$path = $path === null ? null : trim($path);
		return $path === '' ? null : $path;
	}

	private function getCurrentTimestamp(): int {
		return time();
	}

	/**
	 * Match by name and exact path, or by name and a NULL path on either side.
	 *
	 * @throws Exception
	 */
	private function findCompatibleAlarmSound(string $name, ?string $path, ?int $excludeId = null): ?AlarmSound {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR))
			)
			->orderBy('path', 'DESC')
			->addOrderBy('id', 'ASC');

		if ($excludeId !== null) {
			$qb->andWhere(
				$qb->expr()->neq('id', $qb->createNamedParameter($excludeId, IQueryBuilder::PARAM_INT))
			);
		}

		/** @var list<AlarmSound> $candidates */
		$candidates = $this->findEntities($qb);
		if ($path !== null) {
			foreach ($candidates as $candidate) {
				if ($candidate->getPath() === $path) {
					return $candidate;
				}
			}
		}

		foreach ($candidates as $candidate) {
			if ($candidate->getPath() === null || $path === null) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * @throws Exception
	 */
	private function clearCompatibleDefaultAlarmSounds(array $osAffinity, ?int $exceptId = null): void {
		foreach ($this->getDefaultAlarmSounds() as $sound) {
			if ($exceptId !== null && $sound->getId() === $exceptId) {
				continue;
			}
			if (!AlarmSound::osAffinitiesOverlap($osAffinity, $sound->getOsAffinityList())) {
				continue;
			}

			$sound->setIsDefault(false);
			$this->update($sound);
		}
	}

	/**
	 * @return list<AlarmSound>
	 * @throws Exception
	 */
	private function getDefaultAlarmSounds(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('is_default', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			)
			->orderBy('id', 'ASC');

		return $this->findEntities($qb);
	}
}
