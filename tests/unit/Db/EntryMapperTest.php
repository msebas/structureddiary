<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\Entry;
use OCA\StructuredDiary\Db\EntryMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class EntryMapperTest extends TestCase {
	private IDBConnection&MockObject $db;

	protected function setUp(): void {
		parent::setUp();
		$this->db = $this->createMock(IDBConnection::class);
	}

	public function testCreateEntryInitializesEntity(): void {
		$mapper = $this->getMockBuilder(EntryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert'])
			->getMock();

		$mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Entry $entry): Entry {
				$this->assertSame(42, $entry->getDiaryId());
				$this->assertSame(1713254400, $entry->getTimestamp());
				$this->assertSame('Title', $entry->getTitle());
				return $entry;
			});

		$result = $mapper->createEntry(42, 1713254400, 'Title');

		$this->assertInstanceOf(Entry::class, $result);
	}

	public function testCreateEntryAllowsNullTitle(): void {
		$mapper = $this->getMockBuilder(EntryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert'])
			->getMock();

		$mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Entry $entry): Entry {
				$this->assertNull($entry->getTitle());
				return $entry;
			});

		$mapper->createEntry(42, 1713254400, null);
	}

	public function testUpdateEntryChangesOnlyProvidedFields(): void {
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);
		$entry->setTimestamp(1000);
		$entry->setTitle('Old');
		$entry->resetUpdatedFields();

		$mapper = $this->getMockBuilder(EntryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['update'])
			->getMock();

		$mapper->expects($this->once())
			->method('update')
			->with($entry)
			->willReturnCallback(function (Entry $updated): Entry {
				$this->assertSame(2000, $updated->getTimestamp());
				$this->assertSame('Old', $updated->getTitle());
				return $updated;
			});

		$result = $mapper->updateEntry($entry, 2000, null);

		$this->assertSame($entry, $result);
	}

	public function testUpdateEntryCanChangeOnlyTitle(): void {
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);
		$entry->setTimestamp(1000);
		$entry->setTitle('Old');
		$entry->resetUpdatedFields();

		$mapper = $this->getMockBuilder(EntryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['update'])
			->getMock();

		$mapper->expects($this->once())
			->method('update')
			->with($entry)
			->willReturnCallback(function (Entry $updated): Entry {
				$this->assertSame(1000, $updated->getTimestamp());
				$this->assertSame('New', $updated->getTitle());
				return $updated;
			});

		$mapper->updateEntry($entry, null, 'New');
	}

	public function testDeleteEntryReturnsDeletedEntry(): void {
		$entry = new Entry();
		$entry->setId(5);

		$mapper = $this->getMockBuilder(EntryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['delete'])
			->getMock();

		$mapper->expects($this->once())->method('delete')->with($entry)->willReturn($entry);

		$this->assertSame($entry, $mapper->deleteEntry($entry));
	}
}
