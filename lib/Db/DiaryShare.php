<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getDiaryId()
 * @method void setDiaryId(int $diaryId)
 * @method string getSharedWith()
 * @method void setSharedWith(string $sharedWith)
 * @method int getPermission()
 * @method void setPermission(int $permission)
 */
class DiaryShare extends Entity implements JsonSerializable {
	protected $diaryId;
	protected $sharedWith;
	protected $permission;

	public function __construct() {
		$this->addType('diaryId', 'integer');
		$this->addType('sharedWith', 'string');
		$this->addType('permission', 'integer');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'diary_id' => (int)$this->diaryId,
			'shared_with' => $this->sharedWith,
			'permission' => (int)$this->permission,
		];
	}
}
