<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\Diary;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\DiaryPermissions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DiaryMapperTest extends TestCase {
	private IDBConnection&MockObject $db;

	protected function setUp(): void {
		parent::setUp();
		$this->db = $this->createMock(IDBConnection::class);
	}

	public function testCreateDiaryInitializesEntityWithDefaults(): void {
		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert'])
			->getMock();

		$mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Diary $diary): Diary {
				$this->assertSame('alice', $diary->getUserId());
				$this->assertSame('Journal', $diary->getTitle());
				$this->assertSame('Daily notes', $diary->getDescription());
				$this->assertTrue($diary->getReminderActive());
				$this->assertSame(36000, $diary->getReminderTime());
				$this->assertSame(4, $diary->getReminderCount());
				$this->assertSame(1800, $diary->getReminderDelay());
				$this->assertSame('bell', $diary->getReminderSignalFirst());
				$this->assertSame('vibrate', $diary->getReminderSignalRepeat());
				$this->assertSame(86400, $diary->getEntrySchedule());

				return $diary;
			});

		$result = $mapper->createDiary('alice', 'Journal', 'Daily notes', true, 36000, 4, 1800, 'bell', 'vibrate', 86400);

		$this->assertInstanceOf(Diary::class, $result);
		$this->assertSame(DiaryPermissions::OWNER, $result->getAccessLevel());
		$this->assertTrue($result->getIsOwner());
	}

	public function testCreateDiaryRejectsInvalidEntrySchedule(): void {
		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert'])
			->getMock();

		$mapper->expects($this->never())->method('insert');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('entrySchedule must be at least 43200 seconds (half a day).');

		$mapper->createDiary('alice', 'Journal', 'Daily notes', false, 0, 3, 2700, '', '', 43199);
	}

	public function testCreateDiaryRejectsNegativeReminderTime(): void {
		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert'])
			->getMock();

		$mapper->expects($this->never())->method('insert');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('reminderTime must be between 0 and 86399.');

		$mapper->createDiary('alice', 'Journal', 'Daily notes', false, -1);
	}

	public function testCreateDiaryRejectsReminderTimeAboveRange(): void {
		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert'])
			->getMock();

		$mapper->expects($this->never())->method('insert');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('reminderTime must be between 0 and 86399.');

		$mapper->createDiary('alice', 'Journal', 'Daily notes', false, 86400);
	}

	public function testCreateDiaryRejectsNegativeReminderCount(): void {
		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert'])
			->getMock();

		$mapper->expects($this->never())->method('insert');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('reminderCount must be zero or positive.');

		$mapper->createDiary('alice', 'Journal', 'Daily notes', false, 0, -1);
	}

	public function testCreateDiaryRejectsNegativeReminderDelay(): void {
		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert'])
			->getMock();

		$mapper->expects($this->never())->method('insert');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('reminderDelay must be zero or positive.');

		$mapper->createDiary('alice', 'Journal', 'Daily notes', false, 0, 3, -1);
	}

	public function testCreateDiaryAcceptsBoundaryReminderAndScheduleValues(): void {
		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['insert'])
			->getMock();

		$mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Diary $diary): Diary {
				$this->assertSame(86399, $diary->getReminderTime());
				$this->assertSame(43200, $diary->getEntrySchedule());
				return $diary;
			});

		$mapper->createDiary('alice', 'Journal', 'Daily notes', false, 86399, 0, 0, '', '', 43200);
	}

	public function testGetDiaryForUserDecoratesOwnerAccess(): void {
		$diary = $this->createDiaryEntity('alice');

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getDiary', 'getAccessLevel'])
			->getMock();

		$mapper->method('getDiary')->with(42)->willReturn($diary);
		$mapper->method('getAccessLevel')->with(42, 'alice', 'alice')->willReturn(DiaryPermissions::OWNER);

		$result = $mapper->getDiaryForUser(42, 'alice', DiaryPermissions::MANAGE);

		$this->assertTrue($result->getIsOwner());
		$this->assertSame(DiaryPermissions::OWNER, $result->getAccessLevel());
	}

	public function testGetDiaryForUserRejectsMissingManagePermission(): void {
		$diary = $this->createDiaryEntity('owner');

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getDiary', 'getAccessLevel'])
			->getMock();

		$mapper->method('getDiary')->willReturn($diary);
		$mapper->method('getAccessLevel')->willReturn(DiaryPermissions::READ | DiaryPermissions::WRITE);

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Diary not manageable');

		$mapper->getDiaryForUser(42, 'bob', DiaryPermissions::MANAGE);
	}

	public function testGetDiaryForUserRejectsMissingReadPermission(): void {
		$diary = $this->createDiaryEntity('owner');

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getDiary', 'getAccessLevel'])
			->getMock();

		$mapper->method('getDiary')->willReturn($diary);
		$mapper->method('getAccessLevel')->willReturn(0);

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Diary not accessible');

		$mapper->getDiaryForUser(42, 'bob', DiaryPermissions::READ);
	}

	public function testGetDiaryForUserRejectsMissingWritePermission(): void {
		$diary = $this->createDiaryEntity('owner');

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getDiary', 'getAccessLevel'])
			->getMock();

		$mapper->method('getDiary')->willReturn($diary);
		$mapper->method('getAccessLevel')->willReturn(DiaryPermissions::READ);

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Diary not writable');

		$mapper->getDiaryForUser(42, 'bob', DiaryPermissions::WRITE);
	}

	public function testGetDiaryForUserRejectsMissingAnalyzePermission(): void {
		$diary = $this->createDiaryEntity('owner');

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getDiary', 'getAccessLevel'])
			->getMock();

		$mapper->method('getDiary')->willReturn($diary);
		$mapper->method('getAccessLevel')->willReturn(DiaryPermissions::READ | DiaryPermissions::WRITE);

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Diary not analyzable');

		$mapper->getDiaryForUser(42, 'bob', DiaryPermissions::ANALYZE);
	}

	public function testGetDiaryForUserAcceptsAnalyzePermissionAndDecoratesAccess(): void {
		$diary = $this->createDiaryEntity('owner');

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getDiary', 'getAccessLevel'])
			->getMock();

		$mapper->method('getDiary')->with(42)->willReturn($diary);
		$mapper->method('getAccessLevel')->with(42, 'analyst', 'owner')->willReturn(DiaryPermissions::READ | DiaryPermissions::ANALYZE);

		$result = $mapper->getDiaryForUser(42, 'analyst', DiaryPermissions::ANALYZE);

		$this->assertSame(DiaryPermissions::READ | DiaryPermissions::ANALYZE, $result->getAccessLevel());
		$this->assertFalse($result->getIsOwner());
	}

	public function testUpdateDiaryChangesOnlyProvidedFields(): void {
		$diary = $this->createDiaryEntity('owner');
		$diary->setReminderTime(30000);
		$diary->setReminderCount(3);
		$diary->setReminderDelay(2700);
		$diary->setReminderSignalFirst('old-first');
		$diary->setReminderSignalRepeat('old-repeat');
		$diary->setEntrySchedule(86400);
		$diary->setAccessLevel(DiaryPermissions::READ | DiaryPermissions::MANAGE);
		$diary->setIsOwner(false);
		$diary->resetUpdatedFields();

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getDiaryForUser', 'update', 'getAccessLevel'])
			->getMock();

		$mapper->expects($this->once())
			->method('getDiaryForUser')
			->with(42, 'manager', DiaryPermissions::MANAGE)
			->willReturn($diary);

		$mapper->expects($this->once())
			->method('update')
			->willReturnCallback(function (Diary $updatedDiary): Diary {
				$this->assertSame('Updated title', $updatedDiary->getTitle());
				$this->assertSame('Old description', $updatedDiary->getDescription());
				$this->assertTrue($updatedDiary->getReminderActive());
				$this->assertSame(36000, $updatedDiary->getReminderTime());
				$this->assertSame(3, $updatedDiary->getReminderCount());
				$this->assertSame('old-first', $updatedDiary->getReminderSignalFirst());
				$this->assertSame(172800, $updatedDiary->getEntrySchedule());

				return $updatedDiary;
			});

		$mapper->expects($this->once())
			->method('getAccessLevel')
			->with(42, 'manager', 'owner')
			->willReturn(DiaryPermissions::READ | DiaryPermissions::MANAGE);

		$result = $mapper->updateDiary(
			42,
			'manager',
			'Updated title',
			null,
			null,
			true,
			36000,
			null,
			null,
			null,
			null,
			172800,
		);

		$this->assertFalse($result->getIsOwner());
		$this->assertSame(DiaryPermissions::READ | DiaryPermissions::MANAGE, $result->getAccessLevel());
	}

	public function testUpdateDiaryAllowsOwnerChangeWhenNoEntriesExist(): void {
		$diary = $this->createDiaryEntity('owner');
		$diary->setAccessLevel(DiaryPermissions::OWNER);
		$diary->setIsOwner(true);
		$diary->resetUpdatedFields();

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getDiaryForUser', 'hasEntriesForDiary', 'update', 'getAccessLevel'])
			->getMock();

		$mapper->method('getDiaryForUser')->with(42, 'owner', DiaryPermissions::MANAGE)->willReturn($diary);
		$mapper->method('hasEntriesForDiary')->with(42)->willReturn(false);
		$mapper->expects($this->once())
			->method('update')
			->with($diary)
			->willReturnCallback(function (Diary $updated): Diary {
				$this->assertSame('new-owner', $updated->getUserId());
				return $updated;
			});
		$mapper->method('getAccessLevel')->with(42, 'owner', 'new-owner')->willReturn(DiaryPermissions::MANAGE);

		$result = $mapper->updateDiary(42, 'owner', null, null, 'new-owner');

		$this->assertSame('new-owner', $result->getUserId());
		$this->assertFalse($result->getIsOwner());
	}

	public function testUpdateDiaryRejectsEmptyOwnerUserId(): void {
		$diary = $this->createDiaryEntity('owner');

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getDiaryForUser', 'update'])
			->getMock();

		$mapper->method('getDiaryForUser')->with(42, 'owner', DiaryPermissions::MANAGE)->willReturn($diary);
		$mapper->expects($this->never())->method('update');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('ownerUserId cannot be empty.');

		$mapper->updateDiary(42, 'owner', null, null, '');
	}

	public function testUpdateDiaryRejectsOwnerChangeWhenEntriesExist(): void {
		$diary = $this->createDiaryEntity('owner');

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getDiaryForUser', 'hasEntriesForDiary', 'update'])
			->getMock();

		$mapper->method('getDiaryForUser')->with(42, 'owner', DiaryPermissions::MANAGE)->willReturn($diary);
		$mapper->method('hasEntriesForDiary')->with(42)->willReturn(true);
		$mapper->expects($this->never())->method('update');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('The diary owner can only change if the diary has no entries.');

		$mapper->updateDiary(42, 'owner', null, null, 'new-owner');
	}

	public function testUpdateDiaryRejectsInvalidReminderTimeDuringValidation(): void {
		$diary = $this->createDiaryEntity('owner');

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getDiaryForUser', 'update'])
			->getMock();

		$mapper->method('getDiaryForUser')->with(42, 'owner', DiaryPermissions::MANAGE)->willReturn($diary);
		$mapper->expects($this->never())->method('update');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('reminderTime must be between 0 and 86399.');

		$mapper->updateDiary(42, 'owner', null, null, null, null, 86400);
	}

	public function testUpdateDiaryKeepsOwnerWhenOwnerUserIdIsUnchangedAndSkipsEntryCheck(): void {
		$diary = $this->createDiaryEntity('owner');
		$diary->setAccessLevel(DiaryPermissions::OWNER);
		$diary->setIsOwner(true);
		$diary->resetUpdatedFields();

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getDiaryForUser', 'hasEntriesForDiary', 'update', 'getAccessLevel'])
			->getMock();

		$mapper->expects($this->once())->method('getDiaryForUser')->with(42, 'owner', DiaryPermissions::MANAGE)->willReturn($diary);
		$mapper->expects($this->never())->method('hasEntriesForDiary');
		$mapper->expects($this->once())->method('update')->with($diary)->willReturn($diary);
		$mapper->expects($this->once())->method('getAccessLevel')->with(42, 'owner', 'owner')->willReturn(DiaryPermissions::OWNER);

		$result = $mapper->updateDiary(42, 'owner', 'Renamed', null, 'owner');

		$this->assertSame('owner', $result->getUserId());
		$this->assertTrue($result->getIsOwner());
		$this->assertSame(DiaryPermissions::OWNER, $result->getAccessLevel());
	}

	public function testDeleteDiaryReturnsDeletedDiaryWithPreservedAccessLevel(): void {
		$diary = $this->createDiaryEntity('owner');

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getDiaryForUser', 'getAccessLevel', 'delete'])
			->getMock();

		$mapper->method('getDiaryForUser')
			->with(42, 'manager', DiaryPermissions::MANAGE)
			->willReturn($diary);
		$mapper->method('getAccessLevel')
			->with(42, 'manager', 'owner')
			->willReturn(DiaryPermissions::READ | DiaryPermissions::MANAGE);
		$mapper->expects($this->once())
			->method('delete')
			->with($diary)
			->willReturn($diary);

		$result = $mapper->deleteDiary(42, 'manager');

		$this->assertSame(DiaryPermissions::READ | DiaryPermissions::MANAGE, $result->getAccessLevel());
		$this->assertFalse($result->getIsOwner());
	}

	public function testAssertManageAccessRejectsUserWithoutManagePermission(): void {
		$diary = $this->createDiaryEntity('owner');

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getAccessLevel'])
			->getMock();

		$mapper->method('getAccessLevel')->with(42, 'bob', 'owner')->willReturn(DiaryPermissions::READ);

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Diary not manageable by current user');

		$mapper->assertManageAccess($diary, 'bob');
	}

	public function testAssertOwnerAcceptsOwner(): void {
		$diary = $this->createDiaryEntity('alice');

		$mapper = new DiaryMapper($this->db);

		$mapper->assertOwner($diary, 'alice');

		$this->addToAssertionCount(1);
	}

	public function testAssertOwnerRejectsNonOwner(): void {
		$diary = $this->createDiaryEntity('alice');

		$mapper = new DiaryMapper($this->db);

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('Diary not owned by current user');

		$mapper->assertOwner($diary, 'bob');
	}

	public function testAssertManageAccessAcceptsUserWithManagePermission(): void {
		$diary = $this->createDiaryEntity('owner');

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getAccessLevel'])
			->getMock();

		$mapper->method('getAccessLevel')->with(42, 'manager', 'owner')->willReturn(DiaryPermissions::READ | DiaryPermissions::MANAGE);

		$mapper->assertManageAccess($diary, 'manager');

		$this->addToAssertionCount(1);
	}

	public function testAssertManageAccessAcceptsOwnerPermission(): void {
		$diary = $this->createDiaryEntity('owner');

		$mapper = $this->getMockBuilder(DiaryMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getAccessLevel'])
			->getMock();

		$mapper->method('getAccessLevel')->with(42, 'owner', 'owner')->willReturn(DiaryPermissions::OWNER);

		$mapper->assertManageAccess($diary, 'owner');

		$this->addToAssertionCount(1);
	}

	private function createDiaryEntity(string $ownerId): Diary {
		$diary = new Diary();
		$diary->setId(42);
		$diary->setUserId($ownerId);
		$diary->setTitle('Original title');
		$diary->setDescription('Old description');
		$diary->setReminderActive(false);
		$diary->setReminderTime(0);
		$diary->setReminderCount(3);
		$diary->setReminderDelay(2700);
		$diary->setReminderSignalFirst('');
		$diary->setReminderSignalRepeat('');
		$diary->setEntrySchedule(86400);
		$diary->resetUpdatedFields();

		return $diary;
	}
}
