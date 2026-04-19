<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getDiaryId()
 * @method void setDiaryId(int $diaryId)
 * @method int getEntryId()
 * @method void setEntryId(int $entryId)
 * @method int getQuestionId()
 * @method void setQuestionId(int $questionId)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method string|null getTextContent()
 * @method void setTextContent(?string $textContent)
 * @method float|null getNumericContent()
 * @method void setNumericContent(?float $numericContent)
 * @method int|null getPreviousVersionId()
 * @method void setPreviousVersionId(?int $previousVersionId)
 * @method int|null getNextVersionId()
 * @method void setNextVersionId(?int $nextVersionId)
 */
class Answer extends Entity implements JsonSerializable {
	protected $diaryId;
	protected $entryId;
	protected $questionId;
	protected $createdAt;
	protected $textContent;
	protected $numericContent;
	protected $previousVersionId;
	protected $nextVersionId;

	public function __construct() {
		$this->addType('diaryId', 'integer');
		$this->addType('entryId', 'integer');
		$this->addType('questionId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('textContent', 'string');
		$this->addType('numericContent', 'float');
		$this->addType('previousVersionId', 'integer');
		$this->addType('nextVersionId', 'integer');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'diary_id' => (int)$this->diaryId,
			'entry_id' => (int)$this->entryId,
			'question_id' => (int)$this->questionId,
			'created_at' => (int)$this->createdAt,
			'text_content' => $this->textContent,
			'numeric_content' => $this->numericContent,
			'previous_version_id' => $this->previousVersionId === null ? null : (int)$this->previousVersionId,
			'next_version_id' => $this->nextVersionId === null ? null : (int)$this->nextVersionId,
		];
	}
}
