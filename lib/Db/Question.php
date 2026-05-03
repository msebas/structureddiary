<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getChainId()
 * @method void setChainId(int $chainId)
 * @method int getDiaryId()
 * @method void setDiaryId(int $diaryId)
 * @method int getDiaryQuestionOrder()
 * @method void setDiaryQuestionOrder(int $diaryQuestionOrder)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method string getLabel()
 * @method void setLabel(string $label)
 * @method string getDisplayText()
 * @method void setDisplayText(string $displayText)
 * @method string getType()
 * @method void setType(string $type)
 * @method float|null getMinimum()
 * @method void setMinimum(?float $minimum)
 * @method float|null getMaximum()
 * @method void setMaximum(?float $maximum)
 * @method string|null getJsonChoices()
 * @method void setJsonChoices(?string $jsonChoices)
 * @method bool getActive()
 * @method void setActive(bool $active)
 * @method string getTemplateText()
 * @method void setTemplateText(string $templateText)
 * @method int|null getPreviousVersionId()
 * @method void setPreviousVersionId(?int $previousVersionId)
 * @method int|null getNextVersionId()
 * @method void setNextVersionId(?int $nextVersionId)
 */
class Question extends Entity implements JsonSerializable {
	public const TYPE_TEXT = QuestionTypes::TEXT;
	public const TYPE_BOOLEAN = QuestionTypes::BOOLEAN;
	public const TYPE_RATING = QuestionTypes::RATING;
	public const TYPE_NUMBER = QuestionTypes::NUMBER;
	public const TYPE_INTEGER = QuestionTypes::INTEGER;
	public const TYPE_TIME = QuestionTypes::TIME;
	public const TYPE_SELECT = QuestionTypes::SELECT;
	public const TYPE_EDITABLE_SELECT = QuestionTypes::EDITABLE_SELECT;

	protected $chainId;
	protected $diaryId;
	protected $diaryQuestionOrder;
	protected $createdAt;
	protected $label;
	protected $displayText;
	protected $type;
	protected $minimum;
	protected $maximum;
	protected $jsonChoices;
	protected $active = false;
	protected $templateText = '';
	protected $previousVersionId;
	protected $nextVersionId;

	public function __construct() {
		$this->addType('chainId', 'integer');
		$this->addType('diaryId', 'integer');
		$this->addType('diaryQuestionOrder', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('label', 'string');
		$this->addType('displayText', 'string');
		$this->addType('type', 'string');
		$this->addType('minimum', 'float');
		$this->addType('maximum', 'float');
		$this->addType('jsonChoices', 'string');
		$this->addType('active', 'boolean');
		$this->addType('templateText', 'string');
		$this->addType('previousVersionId', 'integer');
		$this->addType('nextVersionId', 'integer');
	}

	/**
	 * @return list<string>|null
	 */
	public function getChoices(): ?array {
		if ($this->jsonChoices === null || $this->jsonChoices === '') {
			return null;
		}

		$decoded = json_decode($this->jsonChoices, true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($decoded)) {
			return null;
		}

		return array_values(array_map(static fn (mixed $value): string => (string)$value, $decoded));
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'chain_id' => (int)$this->chainId,
			'diary_id' => (int)$this->diaryId,
			'diary_question_order' => (int)$this->diaryQuestionOrder,
			'created_at' => (int)$this->createdAt,
			'label' => $this->label,
			'display_text' => $this->displayText,
			'type' => $this->type,
			'minimum' => $this->minimum,
			'maximum' => $this->maximum,
			'choices' => $this->getChoices(),
			'active' => (bool)$this->active,
			'template_text' => $this->templateText,
			'previous_version_id' => $this->previousVersionId === null ? null : (int)$this->previousVersionId,
			'next_version_id' => $this->nextVersionId === null ? null : (int)$this->nextVersionId,
		];
	}
}
