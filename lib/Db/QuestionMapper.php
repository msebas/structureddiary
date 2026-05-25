<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use InvalidArgumentException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class QuestionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, TableNames::QUESTIONS, Question::class);
	}

	/**
	 * @throws Exception
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function getQuestion(int $id): Question {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	/**
	 * @return list<Question>
	 * @throws Exception
	 */
	public function getCurrentQuestionsForDiary(int $diaryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('diary_id', $qb->createNamedParameter($diaryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere($qb->expr()->isNull('next_version_id'))
			->orderBy('diary_question_order', 'ASC')
			->addOrderBy('id', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @return list<Question>
	 * @throws Exception
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function getQuestionChain(int $questionId): array {
		$question = $this->getQuestion($questionId);

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('chain_id', $qb->createNamedParameter($question->getChainId(), IQueryBuilder::PARAM_INT))
			)
			->orderBy('created_at', 'ASC')
			->addOrderBy('id', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @return list<Question>
	 * @throws Exception
	 */
	public function getQuestionsForDiaryAtTimestamp(int $diaryId, int $timestamp, bool $onlyActive = true): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('diary_id', $qb->createNamedParameter($diaryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->lte('created_at', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT))
			)
			->orderBy('created_at', 'ASC')
			->addOrderBy('id', 'ASC');

		/** @var list<Question> $questions */
		$questions = $this->findEntities($qb);
		$currentByChain = [];
		foreach ($questions as $question) {
			$currentByChain[$question->getChainId()] = $question;
		}

		$result = array_values(array_filter(
			$currentByChain,
			static fn (Question $question): bool => !$onlyActive || $question->getActive()
		));
		usort(
			$result,
			static fn (Question $a, Question $b): int => [$a->getDiaryQuestionOrder(), $a->getId()] <=> [$b->getDiaryQuestionOrder(), $b->getId()]
		);

		return $result;
	}

	/**
	 * @param list<string>|null $choices
	 * @throws Exception
	 */
	public function createQuestion(
		int $diaryId,
		string $label,
		string $displayText,
		string $type,
		?float $minimum,
		?float $maximum,
		?array $choices,
		bool $active,
		string $templateText
	): Question {
		QuestionTypeValidator::validateQuestionDefinition($type, $minimum, $maximum, $choices);
		$question = new Question();
		$question->setChainId(0);
		$question->setDiaryId($diaryId);
		$question->setDiaryQuestionOrder(0);
		$question->setCreatedAt($this->getCurrentTimestamp());
		$question->setLabel($label);
		$question->setDisplayText($displayText);
		$question->setType($type);
		$question->setMinimum($minimum);
		$question->setMaximum($maximum);
		$question->setJsonChoices($choices === null ? null : json_encode($choices, JSON_THROW_ON_ERROR));
		$question->setActive($active);
		$question->setTemplateText($templateText);
		$question->setPreviousVersionId(null);
		$question->setNextVersionId(null);
		$question = $this->insert($question);

		$question->setChainId($question->getId());
		$question->setDiaryQuestionOrder($question->getId());
		$this->assertUniqueCurrentOrder($question->getDiaryId(), $question->getDiaryQuestionOrder(), null);

		return $this->update($question);
	}

	/**
	 * @param list<string>|null $choices
	 * @throws Exception
	 */
	public function updateQuestion(
		Question $question,
		?string $label,
		?string $displayText,
		?string $type,
		?float $minimum,
		?float $maximum,
		?array $choices,
		?bool $active,
		?string $templateText
	): Question {
		$hasAnswers = $this->hasAnswersInChain($question);
		$targetLabel = $label ?? $question->getLabel();
		$targetDisplayText = $displayText ?? $question->getDisplayText();
		$targetType = $type ?? $question->getType();
		$targetMinimum = $minimum ?? $question->getMinimum();
		$targetMaximum = $maximum ?? $question->getMaximum();
		$targetChoices = $choices ?? $question->getChoices();
		$targetActive = $active ?? $question->getActive();
		$targetTemplateText = $templateText ?? $question->getTemplateText();
		QuestionTypeValidator::validateQuestionDefinition($targetType, $targetMinimum, $targetMaximum, $targetChoices);
		if ($this->questionPayloadMatches(
			$question,
			$targetLabel,
			$targetDisplayText,
			$targetType,
			$targetMinimum,
			$targetMaximum,
			$targetChoices,
			$targetActive,
			$targetTemplateText
		)) {
			return $question;
		}

		if (!$hasAnswers) {
			$question->setLabel($targetLabel);
			$question->setDisplayText($targetDisplayText);
			$question->setType($targetType);
			$question->setMinimum($targetMinimum);
			$question->setMaximum($targetMaximum);
			$question->setJsonChoices($targetChoices === null ? null : json_encode($targetChoices, JSON_THROW_ON_ERROR));
			$question->setActive($targetActive);
			$question->setTemplateText($targetTemplateText);
			$this->assertUniqueCurrentOrder($question->getDiaryId(), $question->getDiaryQuestionOrder(), $question->getId());

			return $this->update($question);
		}

		if ($question->getNextVersionId() !== null) {
			throw new InvalidArgumentException('Only the current question version can be changed.');
		}

		$newQuestion = $this->createVersionFromQuestion(
			$question,
			$targetLabel,
			$targetDisplayText,
			$targetType,
			$targetMinimum,
			$targetMaximum,
			$targetChoices,
			$targetActive,
			$targetTemplateText,
			$question->getDiaryQuestionOrder(),
		);

		return $newQuestion;
	}

	/**
	 * @throws Exception
	 */
	public function reorderQuestion(Question $question, int $targetOrder): Question {
		if ($question->getNextVersionId() !== null) {
			throw new InvalidArgumentException('Only the current question version can be reordered.');
		}

		$current = $this->getCurrentQuestionsForDiary($question->getDiaryId());
		if ($current === []) {
			throw new InvalidArgumentException('The diary has no current questions.');
		}

		$ordered = array_values($current);
		$movedIndex = null;
		foreach ($ordered as $index => $candidate) {
			if ($candidate->getId() === $question->getId()) {
				$movedIndex = $index;
				break;
			}
		}
		if ($movedIndex === null) {
			throw new InvalidArgumentException('Only the current question version can be reordered.');
		}

		$normalizedTarget = max(1, min($targetOrder, count($ordered)));
		if ($question->getDiaryQuestionOrder() === $normalizedTarget) {
			return $question;
		}

		$moved = $ordered[$movedIndex];
		array_splice($ordered, $movedIndex, 1);
		array_splice($ordered, $normalizedTarget - 1, 0, [$moved]);

		foreach ($ordered as $index => $candidate) {
			$desiredOrder = $index + 1;
			if ($candidate->getId() === $question->getId()) {
				continue;
			}
			if ($candidate->getDiaryQuestionOrder() !== $desiredOrder) {
				$candidate->setDiaryQuestionOrder($desiredOrder);
				$this->update($candidate);
			}
		}

		return $this->createVersionFromQuestion(
			$question,
			$question->getLabel(),
			$question->getDisplayText(),
			$question->getType(),
			$question->getMinimum(),
			$question->getMaximum(),
			$question->getChoices(),
			$question->getActive(),
			$question->getTemplateText(),
			$normalizedTarget,
		);
	}

	/**
	 * @throws Exception
	 */
	public function deleteQuestion(Question $question): Question {
		$answers = $this->getAnswersForQuestion($question->getId());
		$replacement = $this->findCompatibleReplacement($question, $answers);

		if ($replacement === null && $answers !== []) {
			throw new InvalidArgumentException('Questions with answers cannot be deleted without a compatible replacement version.');
		}

		if ($replacement !== null) {
			foreach ($answers as $answer) {
				$answer->setQuestionId($replacement->getId());
				$this->updateAnswerEntity($answer);
			}
		}

		if ($question->getPreviousVersionId() !== null) {
			$previous = $this->getQuestion($question->getPreviousVersionId());
			$previous->setNextVersionId($question->getNextVersionId());
			$this->update($previous);
		}

		if ($question->getNextVersionId() !== null) {
			$next = $this->getQuestion($question->getNextVersionId());
			$next->setPreviousVersionId($question->getPreviousVersionId());
			$this->update($next);
		}

		$this->delete($question);

		return $question;
	}

	/**
	 * @throws Exception
	 */
	public function hasAnswersInChain(Question $question): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'cnt')
			->from(TableNames::ANSWERS, 'a')
			->innerJoin('a', TableNames::QUESTIONS, 'q', $qb->expr()->eq('a.question_id', 'q.id'))
			->where(
				$qb->expr()->eq('q.chain_id', $qb->createNamedParameter($question->getChainId(), IQueryBuilder::PARAM_INT))
			);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return ((int)($row['cnt'] ?? 0)) > 0;
	}

	/**
	 * @return list<Answer>
	 * @throws Exception
	 */
	protected function getAnswersForQuestion(int $questionId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(TableNames::ANSWERS)
			->where(
				$qb->expr()->eq('question_id', $qb->createNamedParameter($questionId, IQueryBuilder::PARAM_INT))
			)
			->orderBy('id', 'ASC');

		$result = $qb->executeQuery();
		try {
			$answers = [];
			while ($row = $result->fetch()) {
				/** @var Answer $answer */
				$answer = Answer::fromRow($row);
				$answers[] = $answer;
			}

			return $answers;
		} finally {
			$result->closeCursor();
		}
	}

	/**
	 * @throws Exception
	 */
	public function countAnswersForQuestion(int $questionId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'cnt')
			->from(TableNames::ANSWERS)
			->where(
				$qb->expr()->eq('question_id', $qb->createNamedParameter($questionId, IQueryBuilder::PARAM_INT))
			);

		$result = $qb->executeQuery();
		try {
			$row = $result->fetch();
			return (int)($row['cnt'] ?? 0);
		} finally {
			$result->closeCursor();
		}
	}

	/**
	 * @throws Exception
	 */
	protected function updateAnswerEntity(Answer $answer): Answer {
		$properties = $answer->getUpdatedFields();
		if (count($properties) === 0) {
			return $answer;
		}

		unset($properties['id']);

		$qb = $this->db->getQueryBuilder();
		$qb->update(TableNames::ANSWERS);
		foreach ($properties as $property => $_updated) {
			$column = $answer->propertyToColumn($property);
			$getter = 'get' . ucfirst($property);
			$qb->set($column, $qb->createNamedParameter($answer->$getter()));
		}
		$qb->where(
			$qb->expr()->eq('id', $qb->createNamedParameter($answer->getId(), IQueryBuilder::PARAM_INT))
		);
		$qb->executeStatement();
		$answer->resetUpdatedFields();

		return $answer;
	}

	/**
	 * @param list<Answer> $answers
	 * @throws Exception
	 */
	protected function findCompatibleReplacement(Question $question, array $answers): ?Question {
		$candidateIds = [];
		if ($question->getNextVersionId() !== null) {
			$candidateIds[] = $question->getNextVersionId();
		}
		if ($question->getPreviousVersionId() !== null) {
			$candidateIds[] = $question->getPreviousVersionId();
		}

		foreach ($candidateIds as $candidateId) {
			$candidate = $this->getQuestion($candidateId);
			$isCompatible = true;
			foreach ($answers as $answer) {
				if (!QuestionTypeValidator::answerIsValidForQuestion($answer, $candidate)) {
					$isCompatible = false;
					break;
				}
			}
			if ($isCompatible) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * @param list<string>|null $choices
	 * @throws Exception
	 */
	private function createVersionFromQuestion(
		Question $question,
		string $label,
		string $displayText,
		string $type,
		?float $minimum,
		?float $maximum,
		?array $choices,
		bool $active,
		string $templateText,
		int $diaryQuestionOrder,
	): Question {
		$this->assertUniqueCurrentOrder($question->getDiaryId(), $diaryQuestionOrder, $question->getId());

		$newQuestion = new Question();
		$newQuestion->setChainId($question->getChainId());
		$newQuestion->setDiaryId($question->getDiaryId());
		$newQuestion->setDiaryQuestionOrder($diaryQuestionOrder);
		$newQuestion->setCreatedAt($this->getCurrentTimestamp());
		$newQuestion->setLabel($label);
		$newQuestion->setDisplayText($displayText);
		$newQuestion->setType($type);
		$newQuestion->setMinimum($minimum);
		$newQuestion->setMaximum($maximum);
		$newQuestion->setJsonChoices($choices === null ? null : json_encode($choices, JSON_THROW_ON_ERROR));
		$newQuestion->setActive($active);
		$newQuestion->setTemplateText($templateText);
		$newQuestion->setPreviousVersionId($question->getId());
		$newQuestion->setNextVersionId(null);
		$newQuestion = $this->insert($newQuestion);

		$question->setNextVersionId($newQuestion->getId());
		$this->update($question);

		return $newQuestion;
	}

	/**
	 * @throws Exception
	 */
	protected function assertUniqueCurrentOrder(int $diaryId, int $order, ?int $ignoreQuestionId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('diary_id', $qb->createNamedParameter($diaryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('diary_question_order', $qb->createNamedParameter($order, IQueryBuilder::PARAM_INT))
			)
			->andWhere($qb->expr()->isNull('next_version_id'))
			->setMaxResults(1);

		if ($ignoreQuestionId !== null) {
			$qb->andWhere(
				$qb->expr()->neq('id', $qb->createNamedParameter($ignoreQuestionId, IQueryBuilder::PARAM_INT))
			);
		}

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		if ($row !== false) {
			throw new InvalidArgumentException('Current question order values must be unique per diary.');
		}
	}

	protected function getCurrentTimestamp(): int {
		return time();
	}

	/**
	 * @param list<string>|null $choices
	 */
	private function questionPayloadMatches(
		Question $question,
		string $label,
		string $displayText,
		string $type,
		?float $minimum,
		?float $maximum,
		?array $choices,
		bool $active,
		string $templateText,
	): bool {
		return $question->getLabel() === $label
			&& $question->getDisplayText() === $displayText
			&& $question->getType() === $type
			&& $question->getMinimum() === $minimum
			&& $question->getMaximum() === $maximum
			&& $question->getChoices() === $choices
			&& $question->getActive() === $active
			&& $question->getTemplateText() === $templateText;
	}
}
