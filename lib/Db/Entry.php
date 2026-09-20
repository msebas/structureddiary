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
 * @method string|null getClientMutationId()
 * @method void setClientMutationId(?string $clientMutationId)
 * @method int getRevisionCount()
 * @method void setRevisionCount(int $revisionCount)
 */
class Entry extends Entity implements JsonSerializable {
	protected $diaryId;
	protected $timestamp;
	protected $title;
	protected $clientMutationId;
	protected $revisionCount = 1;

	public function __construct() {
		$this->addType('diaryId', 'integer');
		$this->addType('timestamp', 'integer');
		$this->addType('title', 'string');
		$this->addType('clientMutationId', 'string');
		$this->addType('revisionCount', 'integer');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'diary_id' => (int)$this->diaryId,
			'timestamp' => (int)$this->timestamp,
			'title' => $this->title,
			'client_mutation_id' => $this->clientMutationId,
			'revision_count' => (int)$this->revisionCount,
		];
	}
}
