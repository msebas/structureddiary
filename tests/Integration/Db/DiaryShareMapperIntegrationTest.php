<?php

declare(strict_types=1);

namespace OCA\Tests\StructuredDiary\Integration\Db;

use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCA\StructuredDiary\Db\DiaryShareMapper;
use OCA\Tests\StructuredDiary\Integration\TestUtil\IntegrationTestParentClass;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * @runTestsInSeparateProcesses
 */
final class DiaryShareMapperIntegrationTest extends IntegrationTestParentClass {
	private DiaryMapper $diaryMapper;
	private DiaryShareMapper $shareMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->diaryMapper = self::$container->get(DiaryMapper::class);
		$this->shareMapper = self::$container->get(DiaryShareMapper::class);
	}

	public function testUpsertCreatesSharesAndListsThemSorted(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');

		$charlie = $this->shareMapper->upsertShare($diary->getId(), 'charlie', DiaryPermissions::READ);
		$bob = $this->shareMapper->upsertShare($diary->getId(), 'bob', DiaryPermissions::READ | DiaryPermissions::MANAGE);

		$this->assertGreaterThan(0, $charlie->getId());
		$this->assertGreaterThan(0, $bob->getId());

		$shares = $this->shareMapper->getSharesForDiary($diary->getId());

		$this->assertCount(2, $shares);
		$this->assertSame(['bob', 'charlie'], array_map(static fn ($share) => $share->getSharedWith(), $shares));
		$this->assertSame(
			[DiaryPermissions::READ | DiaryPermissions::MANAGE, DiaryPermissions::READ],
			array_map(static fn ($share) => $share->getPermission(), $shares)
		);
	}

	public function testUpsertUpdatesExistingShareWithoutCreatingDuplicate(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$created = $this->shareMapper->upsertShare($diary->getId(), 'bob', DiaryPermissions::READ);

		$updated = $this->shareMapper->upsertShare($diary->getId(), 'bob', DiaryPermissions::READ | DiaryPermissions::WRITE);

		$this->assertSame($created->getId(), $updated->getId());
		$this->assertSame(DiaryPermissions::READ | DiaryPermissions::WRITE, $updated->getPermission());

		$shares = $this->shareMapper->getSharesForDiary($diary->getId());
		$this->assertCount(1, $shares);
		$this->assertSame($created->getId(), $shares[0]->getId());
		$this->assertSame(DiaryPermissions::READ | DiaryPermissions::WRITE, $shares[0]->getPermission());
	}

	public function testDeleteShareRemovesItAndMissingShareThrows(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$share = $this->shareMapper->upsertShare($diary->getId(), 'bob', DiaryPermissions::READ);

		$deleted = $this->shareMapper->deleteShare($share->getId());
		$this->assertSame($share->getId(), $deleted->getId());
		$this->assertSame([], $this->shareMapper->getSharesForDiary($diary->getId()));

		$this->expectException(DoesNotExistException::class);
		$this->shareMapper->getShare($share->getId());
	}

	public function testGetShareReturnsPersistedShareById(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$share = $this->shareMapper->upsertShare($diary->getId(), 'bob', DiaryPermissions::READ | DiaryPermissions::WRITE);

		$fetched = $this->shareMapper->getShare($share->getId());

		$this->assertSame($share->getId(), $fetched->getId());
		$this->assertSame($diary->getId(), $fetched->getDiaryId());
		$this->assertSame('bob', $fetched->getSharedWith());
		$this->assertSame(DiaryPermissions::READ | DiaryPermissions::WRITE, $fetched->getPermission());
	}

	public function testGetSharesForUserReturnsOnlySharesForThatUserSortedByDiaryId(): void {
		$diaryA = $this->diaryMapper->createDiary('alice', 'Diary A', 'desc');
		$diaryB = $this->diaryMapper->createDiary('alice', 'Diary B', 'desc');
		$this->shareMapper->upsertShare($diaryB->getId(), 'bob', DiaryPermissions::READ | DiaryPermissions::WRITE);
		$this->shareMapper->upsertShare($diaryA->getId(), 'charlie', DiaryPermissions::READ);
		$this->shareMapper->upsertShare($diaryA->getId(), 'bob', DiaryPermissions::READ);

		$shares = $this->shareMapper->getSharesForUser('bob');

		$this->assertCount(2, $shares);
		$this->assertSame([$diaryA->getId(), $diaryB->getId()], array_map(static fn ($share) => $share->getDiaryId(), $shares));
		$this->assertSame(['bob', 'bob'], array_map(static fn ($share) => $share->getSharedWith(), $shares));
		$this->assertSame(
			[DiaryPermissions::READ, DiaryPermissions::READ | DiaryPermissions::WRITE],
			array_map(static fn ($share) => $share->getPermission(), $shares)
		);
	}
}
