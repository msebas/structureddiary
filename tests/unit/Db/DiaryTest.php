<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\Diary;
use OCA\StructuredDiary\Db\DiaryPermissions;
use PHPUnit\Framework\TestCase;

final class DiaryTest extends TestCase {
	public function testJsonSerializeReturnsExpectedPayload(): void {
		$diary = new Diary();
		$diary->setId(42);
		$diary->setUserId('alice');
		$diary->setTitle('Morning Journal');
		$diary->setDescription('Daily check-in');
		$diary->setReminderActive(true);
		$diary->setReminderTime(28800);
		$diary->setReminderCount(5);
		$diary->setReminderDelay(2700);
		$diary->setReminderSignalFirst('bell');
		$diary->setReminderSignalRepeat('vibrate');
		$diary->setEntrySchedule(86400);
		$diary->setAccessLevel(15);
		$diary->setIsOwner(true);

		$this->assertSame([
			'id' => 42,
			'user_id' => 'alice',
			'title' => 'Morning Journal',
			'description' => 'Daily check-in',
			'reminder_active' => true,
			'reminder_time' => 28800,
			'reminder_count' => 5,
			'reminder_delay' => 2700,
			'reminder_signal_first' => 'bell',
			'reminder_signal_repeat' => 'vibrate',
			'entry_schedule' => 86400,
			'access_level' => 15,
			'is_owner' => true,
		], $diary->jsonSerialize());
	}

	public function testApplyAccessMetadataUpdatesComputedFields(): void {
		$diary = new Diary();
		$diary->applyAccessMetadata(DiaryPermissions::READ | DiaryPermissions::MANAGE, false);

		$this->assertSame(DiaryPermissions::READ | DiaryPermissions::MANAGE, $diary->getAccessLevel());
		$this->assertFalse($diary->getIsOwner());
	}
}
