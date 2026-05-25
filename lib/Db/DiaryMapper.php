<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use InvalidArgumentException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class DiaryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, TableNames::DIARIES, Diary::class);
	}

	/**
	 * @return list<Diary>
	 * @throws Exception
	 */
	public function getAccessibleDiaries(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$expr = $qb->expr();

		$qb->select('d.*')
			->from($this->getTableName(), 'd')
			->leftJoin(
				'd',
				TableNames::DIARY_SHARES,
				's',
				$expr->andX(
					$expr->eq('d.id', 's.diary_id'),
					$expr->eq('s.shared_with', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
				)
			)
			->where(
				$expr->orX(
					$expr->eq('d.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
					$expr->isNotNull('s.id')
				)
			)
			->orderBy('d.title', 'ASC');

		/** @var list<Diary> $diaries */
		$diaries = $this->findEntities($qb);
		foreach ($diaries as $diary) {
			$this->decorateDiaryAccess(
				$diary,
				$userId,
				$this->getAccessLevel($diary->getId(), $userId, $diary->getUserId())
			);
		}

		return $diaries;
	}

	/**
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getDiary(int $id): Diary {
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
	public function getDiaryForUser(int $id, string $userId, int $requiredPermission = DiaryPermissions::READ): Diary {
		$diary = $this->getDiary($id);
		$accessLevel = $this->getAccessLevel($id, $userId, $diary->getUserId());
		if ($requiredPermission === DiaryPermissions::READ && !DiaryPermissions::canRead($accessLevel)) {
			throw new DoesNotExistException('Diary not accessible');
		}
		if ($requiredPermission === DiaryPermissions::WRITE && !DiaryPermissions::canWrite($accessLevel)) {
			throw new DoesNotExistException('Diary not writable');
		}
		if ($requiredPermission === DiaryPermissions::ANALYZE && !DiaryPermissions::canAnalyze($accessLevel)) {
			throw new DoesNotExistException('Diary not analyzable');
		}
		if ($requiredPermission === DiaryPermissions::MANAGE && !DiaryPermissions::canManage($accessLevel)) {
			throw new DoesNotExistException('Diary not manageable');
		}

		$this->decorateDiaryAccess($diary, $userId, $accessLevel);

		return $diary;
	}

	/**
	 * @throws Exception
	 */
	public function createDiary(
		string $userId,
		string $title,
		string $description,
		bool $reminderActive = false,
		int $reminderTime = 0,
		int $reminderCount = 3,
		int $reminderDelay = 2700,
		string $reminderSignalFirst = '',
		string $reminderSignalRepeat = '',
		int $entrySchedule = 86400,
		?string $ownerUserId = null,
	): Diary {
		$this->validateReminderSettings($reminderTime, $reminderCount, $reminderDelay, $entrySchedule);
		$ownerUserId = $this->normalizeOwnerUserId($ownerUserId) ?? $userId;
		$diary = new Diary();
		$diary->setUserId($ownerUserId);
		$diary->setTitle($title);
		$diary->setDescription($description);
		$diary->setReminderActive($reminderActive);
		$diary->setReminderTime($reminderTime);
		$diary->setReminderCount($reminderCount);
		$diary->setReminderDelay($reminderDelay);
		$diary->setReminderSignalFirst($reminderSignalFirst);
		$diary->setReminderSignalRepeat($reminderSignalRepeat);
		$diary->setEntrySchedule($entrySchedule);
		$inserted = $this->insert($diary);
		if ($ownerUserId !== $userId) {
			$this->ensureShare($inserted->getId(), $userId, DiaryPermissions::READ | DiaryPermissions::MANAGE);
		}
		$this->decorateDiaryAccess(
			$inserted,
			$userId,
			$ownerUserId === $userId ? DiaryPermissions::OWNER : DiaryPermissions::READ | DiaryPermissions::MANAGE
		);

		return $inserted;
	}

	/**
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function updateDiary(
		int $id,
		string $userId,
		?string $title,
		?string $description,
		?string $ownerUserId = null,
		?bool $reminderActive = null,
		?int $reminderTime = null,
		?int $reminderCount = null,
		?int $reminderDelay = null,
		?string $reminderSignalFirst = null,
		?string $reminderSignalRepeat = null,
		?int $entrySchedule = null,
	): Diary {
		$diary = $this->getDiaryForUser($id, $userId, DiaryPermissions::MANAGE);
		$previousOwnerUserId = $diary->getUserId();
		$ownerChanged = false;
		$this->validateReminderSettings(
			$reminderTime ?? $diary->getReminderTime(),
			$reminderCount ?? $diary->getReminderCount(),
			$reminderDelay ?? $diary->getReminderDelay(),
			$entrySchedule ?? $diary->getEntrySchedule(),
		);

		if ($title !== null) {
			$diary->setTitle($title);
		}
		if ($description !== null) {
			$diary->setDescription($description);
		}
		$ownerUserId = $this->normalizeOwnerUserId($ownerUserId);
		if ($ownerUserId !== null && $ownerUserId !== $diary->getUserId()) {
			if ($this->hasEntriesForDiary($diary->getId())) {
				throw new InvalidArgumentException('The diary owner can only change if the diary has no entries.');
			}
			$diary->setUserId($ownerUserId);
			$ownerChanged = true;
		}
		if ($reminderActive !== null) {
			$diary->setReminderActive($reminderActive);
		}
		if ($reminderTime !== null) {
			$diary->setReminderTime($reminderTime);
		}
		if ($reminderCount !== null) {
			$diary->setReminderCount($reminderCount);
		}
		if ($reminderDelay !== null) {
			$diary->setReminderDelay($reminderDelay);
		}
		if ($reminderSignalFirst !== null) {
			$diary->setReminderSignalFirst($reminderSignalFirst);
		}
		if ($reminderSignalRepeat !== null) {
			$diary->setReminderSignalRepeat($reminderSignalRepeat);
		}
		if ($entrySchedule !== null) {
			$diary->setEntrySchedule($entrySchedule);
		}

		$updated = $this->update($diary);
		if ($ownerChanged && $userId === $previousOwnerUserId) {
			$this->ensureShare($updated->getId(), $previousOwnerUserId, DiaryPermissions::READ | DiaryPermissions::MANAGE);
		}
		$this->decorateDiaryAccess($updated, $userId, $this->getAccessLevel($updated->getId(), $userId, $updated->getUserId()));

		return $updated;
	}

	/**
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function deleteDiary(int $id, string $userId): Diary {
		$diary = $this->getDiaryForUser($id, $userId, DiaryPermissions::MANAGE);
		$this->decorateDiaryAccess($diary, $userId, $this->getAccessLevel($diary->getId(), $userId, $diary->getUserId()));
		$this->delete($diary);

		return $diary;
	}

	/**
	 * @return array<string, mixed>
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getDiaryStats(int $id, string $userId): array {
		$diary = $this->getDiaryForUser($id, $userId, DiaryPermissions::ANALYZE);
		$entries = $this->getEntriesForStats($diary->getId());
		$entryCount = count($entries);
		$questionCount = $this->countQuestions($diary->getId());
		$answerCount = $this->countAnswers($diary->getId());
		$durations = $this->getEntryDurations($diary->getId());
		$lastMonthStart = time() - (30 * 86400);
		$entryTimestamps = array_map(static fn (array $entry): int => (int)$entry['timestamp'], $entries);
		$intervals = $this->calculateIntervals($entryTimestamps);
		$recentIntervals = $this->calculateIntervals(array_values(array_filter(
			$entryTimestamps,
			static fn (int $timestamp): bool => $timestamp >= $lastMonthStart
		)));
		$target = $diary->getEntrySchedule();
		$largeGaps = array_values(array_filter(
			$intervals,
			static fn (array $gap): bool => $gap['duration'] > (10 * $target)
		));
		$latestAnswerAt = $entryCount === 0 ? null : (int)$entries[$entryCount - 1]['timestamp'];

		return [
			'question_count' => $questionCount,
			'entry_count' => $entryCount,
			'answer_count' => $answerCount,
			'average_answer_count' => $entryCount === 0 ? 0.0 : $answerCount / $entryCount,
			'first_entry_at' => $entryCount === 0 ? null : (int)$entries[0]['timestamp'],
			'latest_entry_at' => $entryCount === 0 ? null : (int)$entries[$entryCount - 1]['timestamp'],
			'entry_frequency' => $this->frequencyStats($intervals),
			'entry_frequency_last_month' => $this->frequencyStats($recentIntervals),
			'gap_count_above_ten_target_intervals' => count($largeGaps),
			'last_large_gap' => $largeGaps === [] ? null : $largeGaps[array_key_last($largeGaps)],
			'longest_gap' => $largeGaps === [] ? null : $this->maxGap($largeGaps),
			'average_entry_duration' => $this->averageDuration($durations),
			'average_entry_duration_last_month' => $this->averageDuration(array_filter(
				$durations,
				static fn (array $duration): bool => $duration['entry_timestamp'] >= $lastMonthStart
			)),
			'latest_answer_at' => $latestAnswerAt,
		];
	}

	/**
	 * @throws Exception
	 */
	public function getAccessLevel(int $diaryId, string $userId, ?string $ownerId = null): int {
		if ($ownerId !== null && $ownerId === $userId) {
			return DiaryPermissions::OWNER;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('permission')
			->from(TableNames::DIARY_SHARES)
			->where(
				$qb->expr()->eq('diary_id', $qb->createNamedParameter($diaryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('shared_with', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row === false ? 0 : (int)$row['permission'];
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function assertOwner(Diary $diary, string $userId): void {
		if ($diary->getUserId() !== $userId) {
			throw new DoesNotExistException('Diary not owned by current user');
		}
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function assertManageAccess(Diary $diary, string $userId): void {
		$accessLevel = $this->getAccessLevel($diary->getId(), $userId, $diary->getUserId());
		if (!DiaryPermissions::canManage($accessLevel)) {
			throw new DoesNotExistException('Diary not manageable by current user');
		}
	}

	private function decorateDiaryAccess(Diary $diary, string $userId, ?int $accessLevel = null): void {
		$isOwner = $diary->getUserId() === $userId;
		$diary->applyAccessMetadata($isOwner ? DiaryPermissions::OWNER : ($accessLevel ?? 0), $isOwner);
	}

	private function validateReminderSettings(int $reminderTime, int $reminderCount, int $reminderDelay, int $entrySchedule): void {
		if ($reminderTime < 0 || $reminderTime >= 86400) {
			throw new InvalidArgumentException('reminderTime must be between 0 and 86399.');
		}
		if ($reminderCount < 0) {
			throw new InvalidArgumentException('reminderCount must be zero or positive.');
		}
		if ($reminderDelay < 0) {
			throw new InvalidArgumentException('reminderDelay must be zero or positive.');
		}
		if ($entrySchedule < 43200) {
			throw new InvalidArgumentException('entrySchedule must be at least 43200 seconds (half a day).');
		}
	}

	private function normalizeOwnerUserId(?string $ownerUserId): ?string {
		if ($ownerUserId === null) {
			return null;
		}

		$ownerUserId = trim($ownerUserId);
		if ($ownerUserId === '') {
			throw new InvalidArgumentException('ownerUserId cannot be empty.');
		}

		return $ownerUserId;
	}

	/**
	 * @throws Exception
	 */
	protected function ensureShare(int $diaryId, string $sharedWith, int $permission): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(TableNames::DIARY_SHARES)
			->set('permission', $qb->createNamedParameter($permission, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->eq('diary_id', $qb->createNamedParameter($diaryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('shared_with', $qb->createNamedParameter($sharedWith, IQueryBuilder::PARAM_STR))
			);

		if ($qb->executeStatement() > 0) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert(TableNames::DIARY_SHARES)
			->values([
				'diary_id' => $qb->createNamedParameter($diaryId, IQueryBuilder::PARAM_INT),
				'shared_with' => $qb->createNamedParameter($sharedWith, IQueryBuilder::PARAM_STR),
				'permission' => $qb->createNamedParameter($permission, IQueryBuilder::PARAM_INT),
			]);
		$qb->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	protected function hasEntriesForDiary(int $diaryId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from(TableNames::ENTRIES)
			->where(
				$qb->expr()->eq('diary_id', $qb->createNamedParameter($diaryId, IQueryBuilder::PARAM_INT))
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row !== false;
	}

	/**
	 * @return list<array{id:int,timestamp:int}>
	 * @throws Exception
	 */
	protected function getEntriesForStats(int $diaryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'timestamp')
			->from(TableNames::ENTRIES)
			->where(
				$qb->expr()->eq('diary_id', $qb->createNamedParameter($diaryId, IQueryBuilder::PARAM_INT))
			)
			->orderBy('timestamp', 'ASC');

		$result = $qb->executeQuery();
		try {
			$entries = [];
			while ($row = $result->fetch()) {
				$entries[] = [
					'id' => (int)$row['id'],
					'timestamp' => (int)$row['timestamp'],
				];
			}

			return $entries;
		} finally {
			$result->closeCursor();
		}
	}

	/**
	 * @throws Exception
	 */
	protected function countQuestions(int $diaryId): int {
		return $this->countByDiaryTable(TableNames::QUESTIONS, $diaryId);
	}

	/**
	 * @throws Exception
	 */
	protected function countAnswers(int $diaryId): int {
		return $this->countByDiaryTable(TableNames::ANSWERS, $diaryId);
	}

	/**
	 * @throws Exception
	 */
	private function countByDiaryTable(string $tableName, int $diaryId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'cnt')
			->from($tableName)
			->where(
				$qb->expr()->eq('diary_id', $qb->createNamedParameter($diaryId, IQueryBuilder::PARAM_INT))
			);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return (int)($row['cnt'] ?? 0);
	}

	/**
	 * @return list<array{entry_id:int,entry_timestamp:int,duration:int}>
	 * @throws Exception
	 */
	protected function getEntryDurations(int $diaryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('e.id AS entry_id', 'e.timestamp AS entry_timestamp')
			->selectAlias($qb->createFunction('MAX(a.created_at)'), 'latest_answer_at')
			->from(TableNames::ENTRIES, 'e')
			->leftJoin('e', TableNames::ANSWERS, 'a', 'e.id = a.entry_id')
			->where(
				$qb->expr()->eq('e.diary_id', $qb->createNamedParameter($diaryId, IQueryBuilder::PARAM_INT))
			)
			->groupBy('e.id', 'e.timestamp')
			->orderBy('e.timestamp', 'ASC');

		$result = $qb->executeQuery();
		try {
			$durations = [];
			while ($row = $result->fetch()) {
				if ($row['latest_answer_at'] === null) {
					continue;
				}
				$durations[] = [
					'entry_id' => (int)$row['entry_id'],
					'entry_timestamp' => (int)$row['entry_timestamp'],
					'duration' => max(0, (int)$row['latest_answer_at'] - (int)$row['entry_timestamp']),
				];
			}

			return $durations;
		} finally {
			$result->closeCursor();
		}
	}

	/**
	 * @param list<int> $timestamps
	 * @return list<array{start:int,end:int,duration:int}>
	 */
	private function calculateIntervals(array $timestamps): array {
		$intervals = [];
		for ($i = 1, $count = count($timestamps); $i < $count; $i++) {
			$intervals[] = [
				'start' => $timestamps[$i - 1],
				'end' => $timestamps[$i],
				'duration' => $timestamps[$i] - $timestamps[$i - 1],
			];
		}

		return $intervals;
	}

	/**
	 * @param list<array{start:int,end:int,duration:int}> $intervals
	 * @return array{mean:float|null,stddev:float|null}
	 */
	private function frequencyStats(array $intervals): array {
		if ($intervals === []) {
			return ['mean' => null, 'stddev' => null];
		}

		$durations = array_map(static fn (array $interval): int => $interval['duration'], $intervals);
		$mean = array_sum($durations) / count($durations);
		$variance = array_sum(array_map(
			static fn (int $duration): float => ($duration - $mean) ** 2,
			$durations
		)) / count($durations);

		return ['mean' => $mean, 'stddev' => sqrt($variance)];
	}

	/**
	 * @param iterable<array{entry_id:int,entry_timestamp:int,duration:int}> $durations
	 */
	private function averageDuration(iterable $durations): ?float {
		$values = [];
		foreach ($durations as $duration) {
			$values[] = $duration['duration'];
		}

		if ($values === []) {
			return null;
		}

		return array_sum($values) / count($values);
	}

	/**
	 * @param list<array{start:int,end:int,duration:int}> $gaps
	 * @return array{start:int,end:int,duration:int}|null
	 */
	private function maxGap(array $gaps): ?array {
		if ($gaps === []) {
			return null;
		}

		usort($gaps, static fn (array $a, array $b): int => $b['duration'] <=> $a['duration']);

		return $gaps[0];
	}
}
