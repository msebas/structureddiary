<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getDiaryId()
 * @method void setDiaryId(int $diaryId)
 * @method int getTimestamp()
 * @method void setTimestamp(int $timestamp)
 * @method string|null getTitle()
 * @method void setTitle(?string $title)
 */
class Entry extends Entity implements JsonSerializable {
	protected $diaryId;
	protected $timestamp;
	protected $title;

	public function __construct() {
		$this->addType('diaryId', 'integer');
		$this->addType('timestamp', 'integer');
		$this->addType('title', 'string');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'diary_id' => (int)$this->diaryId,
			'timestamp' => (int)$this->timestamp,
			'title' => $this->title,
		];
	}
}
