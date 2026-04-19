<?php

declare(strict_types=1);

namespace OCA\Tests\StructuredDiary\Integration\Db;

use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\EntryMapper;
use OCA\Tests\StructuredDiary\Integration\TestUtil\IntegrationTestParentClass;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * @runTestsInSeparateProcesses
 */
final class EntryMapperIntegrationTest extends IntegrationTestParentClass {
	private DiaryMapper $diaryMapper;
	private EntryMapper $entryMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->diaryMapper = self::$container->get(DiaryMapper::class);
		$this->entryMapper = self::$container->get(EntryMapper::class);
	}

	public function testCreateGetUpdateAndDeleteEntryPersistValues(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$created = $this->entryMapper->createEntry($diary->getId(), 1713254400, null);

		$this->assertGreaterThan(0, $created->getId());
		$this->assertNull($created->getTitle());

		$fetched = $this->entryMapper->getEntry($created->getId());
		$this->assertSame($diary->getId(), $fetched->getDiaryId());
		$this->assertSame(1713254400, $fetched->getTimestamp());
		$this->assertNull($fetched->getTitle());

		$updated = $this->entryMapper->updateEntry($fetched, 1713340800, 'Renamed');
		$this->assertSame('Renamed', $updated->getTitle());

		$persisted = $this->entryMapper->getEntry($created->getId());
		$this->assertSame(1713340800, $persisted->getTimestamp());
		$this->assertSame('Renamed', $persisted->getTitle());

		$deleted = $this->entryMapper->deleteEntry($persisted);
		$this->assertSame($created->getId(), $deleted->getId());

		$this->expectException(DoesNotExistException::class);
		$this->entryMapper->getEntry($created->getId());
	}

	public function testGetEntriesForDiaryFiltersByDiaryAndSortsNewestFirst(): void {
		$diaryA = $this->diaryMapper->createDiary('alice', 'Diary A', 'desc');
		$diaryB = $this->diaryMapper->createDiary('bob', 'Diary B', 'desc');

		$old = $this->entryMapper->createEntry($diaryA->getId(), 1000, 'old');
		$new = $this->entryMapper->createEntry($diaryA->getId(), 3000, 'new');
		$this->entryMapper->createEntry($diaryB->getId(), 2000, 'other diary');

		$entries = $this->entryMapper->getEntriesForDiary($diaryA->getId());

		$this->assertCount(2, $entries);
		$this->assertSame([$new->getId(), $old->getId()], array_map(static fn ($entry) => $entry->getId(), $entries));
		$this->assertSame(['new', 'old'], array_map(static fn ($entry) => $entry->getTitle(), $entries));
	}

	public function testGetEntriesForDiaryCanFilterByTimestampRange(): void {
		$diary = $this->diaryMapper->createDiary('alice', 'Diary', 'desc');
		$tooOld = $this->entryMapper->createEntry($diary->getId(), 1000, 'too old');
		$inRangeA = $this->entryMapper->createEntry($diary->getId(), 2000, 'in range a');
		$inRangeB = $this->entryMapper->createEntry($diary->getId(), 3000, 'in range b');
		$tooNew = $this->entryMapper->createEntry($diary->getId(), 4000, 'too new');

		$entries = $this->entryMapper->getEntriesForDiary($diary->getId(), 1500, 3500);

		$this->assertSame(
			[$inRangeB->getId(), $inRangeA->getId()],
			array_map(static fn ($entry) => $entry->getId(), $entries)
		);
		$this->assertNotContains($tooOld->getId(), array_map(static fn ($entry) => $entry->getId(), $entries));
		$this->assertNotContains($tooNew->getId(), array_map(static fn ($entry) => $entry->getId(), $entries));
	}
}
