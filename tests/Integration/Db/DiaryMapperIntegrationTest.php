<?php

declare(strict_types=1);

namespace OCA\Tests\StructuredDiary\Integration\Db;

use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCA\StructuredDiary\Db\DiaryShareMapper;
use OCA\StructuredDiary\Db\QuestionMapper;
use OCA\StructuredDiary\Db\AnswerMapper;
use OCA\StructuredDiary\Db\QuestionTypes;
use OCA\StructuredDiary\Db\EntryMapper;
use OCA\Tests\StructuredDiary\Integration\TestUtil\IntegrationTestParentClass;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * @runTestsInSeparateProcesses
 */
final class DiaryMapperIntegrationTest extends IntegrationTestParentClass {

	private DiaryMapper $diaryMapper;
	private DiaryShareMapper $shareMapper;
	private EntryMapper $entryMapper;
	private QuestionMapper $questionMapper;
	private AnswerMapper $answerMapper;


	protected function setUp(): void {
		parent::setUp();

		$this->diaryMapper = self::$container->get(DiaryMapper::class);
		$this->shareMapper = self::$container->get(DiaryShareMapper::class);
		$this->entryMapper = self::$container->get(EntryMapper::class);
		$this->questionMapper = self::$container->get(QuestionMapper::class);
		$this->answerMapper = self::$container->get(AnswerMapper::class);
	}

	public function testCreateAndFetchDiaryPersistsFieldsAndOwnerAccess(): void {
		$created = $this->diaryMapper->createDiary('alice', 'Alice Diary', 'owned by alice', true, 36000, 4, 1800, 'bell', 'vibrate', 90000);

		$this->assertGreaterThan(0, $created->getId());
		$this->assertTrue($created->getIsOwner());
		$this->assertSame(DiaryPermissions::OWNER, $created->getAccessLevel());

		$fetched = $this->diaryMapper->getDiary($created->getId());

		$this->assertSame('alice', $fetched->getUserId());
		$this->assertSame('Alice Diary', $fetched->getTitle());
		$this->assertSame('owned by alice', $fetched->getDescription());
		$this->assertTrue($fetched->getReminderActive());
		$this->assertSame(36000, $fetched->getReminderTime());
		$this->assertSame(4, $fetched->getReminderCount());
		$this->assertSame(1800, $fetched->getReminderDelay());
		$this->assertSame('bell', $fetched->getReminderSignalFirst());
		$this->assertSame('vibrate', $fetched->getReminderSignalRepeat());
		$this->assertSame(90000, $fetched->getEntrySchedule());

		$forOwner = $this->diaryMapper->getDiaryForUser($created->getId(), 'alice', DiaryPermissions::MANAGE);
		$this->assertTrue($forOwner->getIsOwner());
		$this->assertSame(DiaryPermissions::OWNER, $forOwner->getAccessLevel());
	}

	public function testGetAccessibleDiariesIncludesOwnedAndSharedDiariesWithPermissions(): void {
		$aliceDiary = $this->diaryMapper->createDiary('alice', 'Alice Diary', 'owned by alice', true, 36000, 4, 1800, 'bell', 'vibrate', 90000);
		$bobDiary = $this->diaryMapper->createDiary('bob', 'Bob Diary', 'owned by bob', true, 32400, 5, 1200, 'chime', 'buzz', 172800);
		$this->shareMapper->upsertShare($bobDiary->getId(), 'alice', DiaryPermissions::READ | DiaryPermissions::MANAGE);

		$diaries = $this->diaryMapper->getAccessibleDiaries('alice');

		$this->assertCount(2, $diaries);
		$this->assertSame(['Alice Diary', 'Bob Diary'], array_map(static fn ($diary) => $diary->getTitle(), $diaries));

		$owned = $diaries[0];
		$shared = $diaries[1];

		$this->assertSame($aliceDiary->getId(), $owned->getId());
		$this->assertTrue($owned->getIsOwner());
		$this->assertSame(DiaryPermissions::OWNER, $owned->getAccessLevel());

		$this->assertSame($bobDiary->getId(), $shared->getId());
		$this->assertFalse($shared->getIsOwner());
		$this->assertSame(DiaryPermissions::READ | DiaryPermissions::MANAGE, $shared->getAccessLevel());
	}

	public function testGetAccessibleDiariesReturnsEmptyListForUserWithoutAccess(): void {
		$this->diaryMapper->createDiary('alice', 'Alice Diary', 'owned by alice');
		$this->diaryMapper->createDiary('bob', 'Bob Diary', 'owned by bob');

		$this->assertSame([], $this->diaryMapper->getAccessibleDiaries('charlie'));
	}

	public function testSharedAccessAndPermissionChecksUseStoredSharePermissions(): void {
		$bobDiary = $this->diaryMapper->createDiary('bob', 'Bob Diary', 'owned by bob');
		$this->shareMapper->upsertShare($bobDiary->getId(), 'alice', DiaryPermissions::READ | DiaryPermissions::ANALYZE);

		$this->assertSame(0, $this->diaryMapper->getAccessLevel($bobDiary->getId(), 'charlie', 'bob'));
		$this->assertSame(
			DiaryPermissions::READ | DiaryPermissions::ANALYZE,
			$this->diaryMapper->getAccessLevel($bobDiary->getId(), 'alice', 'bob')
		);

		$sharedDiary = $this->diaryMapper->getDiaryForUser($bobDiary->getId(), 'alice', DiaryPermissions::READ);
		$this->assertFalse($sharedDiary->getIsOwner());
		$this->assertSame(DiaryPermissions::READ | DiaryPermissions::ANALYZE, $sharedDiary->getAccessLevel());

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Diary not writable');
		$this->diaryMapper->getDiaryForUser($bobDiary->getId(), 'alice', DiaryPermissions::WRITE);
	}

	public function testGetDiaryForUserRejectsUserWithoutAnyAccess(): void {
		$created = $this->diaryMapper->createDiary('alice', 'Alice Diary', 'owned by alice');

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Diary not accessible');

		$this->diaryMapper->getDiaryForUser($created->getId(), 'charlie', DiaryPermissions::READ);
	}

	public function testGetDiaryForUserAllowsAnalyzePermissionAndRejectsManage(): void {
		$created = $this->diaryMapper->createDiary('alice', 'Alice Diary', 'owned by alice');
		$this->shareMapper->upsertShare($created->getId(), 'bob', DiaryPermissions::READ | DiaryPermissions::ANALYZE);

		$analyzable = $this->diaryMapper->getDiaryForUser($created->getId(), 'bob', DiaryPermissions::ANALYZE);
		$this->assertFalse($analyzable->getIsOwner());
		$this->assertSame(DiaryPermissions::READ | DiaryPermissions::ANALYZE, $analyzable->getAccessLevel());

		try {
			$this->diaryMapper->getDiaryForUser($created->getId(), 'bob', DiaryPermissions::MANAGE);
			$this->fail('Expected shared analyze-only user to be denied manage access.');
		} catch (DoesNotExistException $e) {
			$this->assertSame('Diary not manageable', $e->getMessage());
		}
	}

	public function testUpdateDiaryPersistsOwnerChangeWhenThereAreNoEntries(): void {
		$created = $this->diaryMapper->createDiary('alice', 'Alice Diary', 'owned by alice');

		$updated = $this->diaryMapper->updateDiary(
			$created->getId(),
			'alice',
			'Renamed Diary',
			'updated description',
			'bob',
			true,
			28800,
			5,
			900,
			'gentle',
			'loud',
			172800,
		);

		$this->assertSame('bob', $updated->getUserId());
		$this->assertFalse($updated->getIsOwner());
		$this->assertSame(0, $updated->getAccessLevel());

		$fetched = $this->diaryMapper->getDiaryForUser($created->getId(), 'bob', DiaryPermissions::MANAGE);
		$this->assertSame('Renamed Diary', $fetched->getTitle());
		$this->assertSame('updated description', $fetched->getDescription());
		$this->assertTrue($fetched->getReminderActive());
		$this->assertSame(28800, $fetched->getReminderTime());
		$this->assertSame(5, $fetched->getReminderCount());
		$this->assertSame(900, $fetched->getReminderDelay());
		$this->assertSame('gentle', $fetched->getReminderSignalFirst());
		$this->assertSame('loud', $fetched->getReminderSignalRepeat());
		$this->assertSame(172800, $fetched->getEntrySchedule());
		$this->assertTrue($fetched->getIsOwner());
		$this->assertSame(DiaryPermissions::OWNER, $fetched->getAccessLevel());
	}

	public function testOwnerTransferToAlreadySharedUserYieldsSingleOwnedDiary(): void {
		$created = $this->diaryMapper->createDiary('alice', 'Alice Diary', 'owned by alice');
		$this->shareMapper->upsertShare($created->getId(), 'bob', DiaryPermissions::READ | DiaryPermissions::MANAGE);

		$updated = $this->diaryMapper->updateDiary($created->getId(), 'alice', null, null, 'bob');

		$this->assertSame('bob', $updated->getUserId());
		$this->assertFalse($updated->getIsOwner());
		$this->assertSame(0, $updated->getAccessLevel());

		$bobDiaries = $this->diaryMapper->getAccessibleDiaries('bob');
		$this->assertCount(1, $bobDiaries);
		$this->assertSame($created->getId(), $bobDiaries[0]->getId());
		$this->assertTrue($bobDiaries[0]->getIsOwner());
		$this->assertSame(DiaryPermissions::OWNER, $bobDiaries[0]->getAccessLevel());

		$this->assertSame([], $this->diaryMapper->getAccessibleDiaries('alice'));
	}

	public function testUpdateDiaryAllowsSharedManagerToModifyDiary(): void {
		$created = $this->diaryMapper->createDiary('alice', 'Alice Diary', 'owned by alice');
		$this->shareMapper->upsertShare($created->getId(), 'bob', DiaryPermissions::READ | DiaryPermissions::MANAGE);

		$updated = $this->diaryMapper->updateDiary(
			$created->getId(),
			'bob',
			'Managed Rename',
			'managed description',
		);

		$this->assertSame($created->getId(), $updated->getId());
		$this->assertFalse($updated->getIsOwner());
		$this->assertSame(DiaryPermissions::READ | DiaryPermissions::MANAGE, $updated->getAccessLevel());

		$fetched = $this->diaryMapper->getDiary($created->getId());
		$this->assertSame('Managed Rename', $fetched->getTitle());
		$this->assertSame('managed description', $fetched->getDescription());
	}

	public function testUpdateDiaryByOwnerPreservesOwnerAccessMetadata(): void {
		$created = $this->diaryMapper->createDiary('alice', 'Alice Diary', 'owned by alice');

		$updated = $this->diaryMapper->updateDiary(
			$created->getId(),
			'alice',
			'Owner Rename',
			null,
			null,
			false,
			86399,
			0,
			0,
			'',
			'',
			43200,
		);

		$this->assertTrue($updated->getIsOwner());
		$this->assertSame(DiaryPermissions::OWNER, $updated->getAccessLevel());
		$this->assertSame('Owner Rename', $updated->getTitle());
		$this->assertSame(86399, $updated->getReminderTime());
		$this->assertSame(0, $updated->getReminderCount());
		$this->assertSame(0, $updated->getReminderDelay());
		$this->assertSame(43200, $updated->getEntrySchedule());
	}

	public function testUpdateDiaryRejectsOwnerChangeWhenEntriesExist(): void {
		$created = $this->diaryMapper->createDiary('alice', 'Alice Diary', 'owned by alice');
		$this->entryMapper->createEntry($created->getId(), 1713254400, 'day one');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('The diary owner can only change if the diary has no entries.');

		$this->diaryMapper->updateDiary($created->getId(), 'alice', null, null, 'bob');
	}

	public function testGetDiaryStatsReturnsComputedCountsAndDurations(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$entryOne = $this->entryMapper->createEntry($diary->getId(), 1000, 'one');
		$entryTwo = $this->entryMapper->createEntry($diary->getId(), 1400, 'two');
		$question = $this->questionMapper->createQuestion($diary->getId(), 'Mood', 'Mood', QuestionTypes::TEXT, null, null, null, true, '');
		$answerOne = $this->answerMapper->createAnswer($diary->getId(), $entryOne->getId(), $question->getId(), 'a', null);
		$this->answerMapper->updateAnswer($answerOne, 'b', null);
		$this->answerMapper->createAnswer($diary->getId(), $entryTwo->getId(), $question->getId(), 'c', null);

		$stats = $this->diaryMapper->getDiaryStats($diary->getId(), 'alice');

		$this->assertSame(1, $stats['question_count']);
		$this->assertSame(2, $stats['entry_count']);
		$this->assertSame(3, $stats['answer_count']);
		$this->assertSame(1.5, $stats['average_answer_count']);
		$this->assertSame(1000, $stats['first_entry_at']);
		$this->assertSame(1400, $stats['latest_entry_at']);
		$this->assertSame(0, $stats['gap_count_above_ten_target_intervals']);
		$this->assertNull($stats['last_large_gap']);
		$this->assertNull($stats['longest_gap']);
		$this->assertGreaterThanOrEqual(0.0, $stats['average_entry_duration']);
		$this->assertArrayHasKey('mean', $stats['entry_frequency']);
		$this->assertArrayHasKey('stddev', $stats['entry_frequency']);
	}

	public function testGetDiaryStatsRequiresAnalyzePermission(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Diary not analyzable');

		$this->diaryMapper->getDiaryStats($diary->getId(), 'bob');
	}

	public function testUpdateDiaryRejectsEmptyOwnerUserId(): void {
		$created = $this->diaryMapper->createDiary('alice', 'Alice Diary', 'owned by alice');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('ownerUserId cannot be empty.');

		$this->diaryMapper->updateDiary($created->getId(), 'alice', null, null, '');
	}

	public function testDeleteDiaryRemovesDiaryAndShares(): void {
		$created = $this->diaryMapper->createDiary('alice', 'Alice Diary', 'owned by alice');
		$this->shareMapper->upsertShare($created->getId(), 'bob', DiaryPermissions::READ | DiaryPermissions::MANAGE);

		$deleted = $this->diaryMapper->deleteDiary($created->getId(), 'alice');
		$this->assertSame($created->getId(), $deleted->getId());
		$this->assertSame([], $this->diaryMapper->getAccessibleDiaries('bob'));
		$this->assertSame(0, $this->diaryMapper->getAccessLevel($created->getId(), 'bob'));

		$this->expectException(DoesNotExistException::class);
		$this->diaryMapper->getDiary($created->getId());
	}

	public function testDeleteDiaryAllowsSharedManagerToDeleteDiary(): void {
		$created = $this->diaryMapper->createDiary('alice', 'Alice Diary', 'owned by alice');
		$this->shareMapper->upsertShare($created->getId(), 'bob', DiaryPermissions::READ | DiaryPermissions::MANAGE);

		$deleted = $this->diaryMapper->deleteDiary($created->getId(), 'bob');

		$this->assertSame($created->getId(), $deleted->getId());
		$this->assertFalse($deleted->getIsOwner());
		$this->assertSame(DiaryPermissions::READ | DiaryPermissions::MANAGE, $deleted->getAccessLevel());
		$this->assertSame([], $this->diaryMapper->getAccessibleDiaries('alice'));
	}

	public function testAssertOwnerAndAssertManageAccessReflectStoredPermissions(): void {
		$created = $this->diaryMapper->createDiary('alice', 'Alice Diary', 'owned by alice');
		$this->shareMapper->upsertShare($created->getId(), 'bob', DiaryPermissions::READ | DiaryPermissions::MANAGE);
		$this->shareMapper->upsertShare($created->getId(), 'charlie', DiaryPermissions::READ);

		$diary = $this->diaryMapper->getDiary($created->getId());

		$this->diaryMapper->assertOwner($diary, 'alice');
		$this->diaryMapper->assertManageAccess($diary, 'alice');
		$this->diaryMapper->assertManageAccess($diary, 'bob');

		try {
			$this->diaryMapper->assertOwner($diary, 'bob');
			$this->fail('Expected non-owner to be rejected by assertOwner.');
		} catch (DoesNotExistException $e) {
			$this->assertSame('Diary not owned by current user', $e->getMessage());
		}

		try {
			$this->diaryMapper->assertManageAccess($diary, 'charlie');
			$this->fail('Expected non-manager to be rejected by assertManageAccess.');
		} catch (DoesNotExistException $e) {
			$this->assertSame('Diary not manageable by current user', $e->getMessage());
		}
	}
}
