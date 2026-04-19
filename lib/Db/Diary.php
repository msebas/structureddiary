<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string getDescription()
 * @method void setDescription(string $description)
 * @method bool getReminderActive()
 * @method void setReminderActive(bool $reminderActive)
 * @method int getReminderTime()
 * @method void setReminderTime(int $reminderTime)
 * @method int getReminderCount()
 * @method void setReminderCount(int $reminderCount)
 * @method int getReminderDelay()
 * @method void setReminderDelay(int $reminderDelay)
 * @method string getReminderSignalFirst()
 * @method void setReminderSignalFirst(string $reminderSignalFirst)
 * @method string getReminderSignalRepeat()
 * @method void setReminderSignalRepeat(string $reminderSignalRepeat)
 * @method int getEntrySchedule()
 * @method void setEntrySchedule(int $entrySchedule)
 * @method int getAccessLevel()
 * @method void setAccessLevel(int $accessLevel)
 * @method bool getIsOwner()
 * @method void setIsOwner(bool $isOwner)
 */
class Diary extends Entity implements JsonSerializable {
	protected $userId;
	protected $title;
	protected $description;
	protected $reminderActive = false;
	protected $reminderTime = 0;
	protected $reminderCount = 3;
	protected $reminderDelay = 2700;
	protected $reminderSignalFirst = '';
	protected $reminderSignalRepeat = '';
	protected $entrySchedule = 86400;
	protected $accessLevel = DiaryPermissions::OWNER;
	protected $isOwner = false;

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('title', 'string');
		$this->addType('description', 'string');
		$this->addType('reminderActive', 'boolean');
		$this->addType('reminderTime', 'integer');
		$this->addType('reminderCount', 'integer');
		$this->addType('reminderDelay', 'integer');
		$this->addType('reminderSignalFirst', 'string');
		$this->addType('reminderSignalRepeat', 'string');
		$this->addType('entrySchedule', 'integer');
		$this->addType('accessLevel', 'integer');
		$this->addType('isOwner', 'boolean');
	}

	public function applyAccessMetadata(int $accessLevel, bool $isOwner): void {
		$this->accessLevel = $accessLevel;
		$this->isOwner = $isOwner;
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'title' => $this->title,
			'description' => $this->description,
			'reminder_active' => (bool)$this->reminderActive,
			'reminder_time' => (int)$this->reminderTime,
			'reminder_count' => (int)$this->reminderCount,
			'reminder_delay' => (int)$this->reminderDelay,
			'reminder_signal_first' => $this->reminderSignalFirst,
			'reminder_signal_repeat' => $this->reminderSignalRepeat,
			'entry_schedule' => (int)$this->entrySchedule,
			'access_level' => (int)$this->accessLevel,
			'is_owner' => (bool)$this->isOwner,
		];
	}
}
