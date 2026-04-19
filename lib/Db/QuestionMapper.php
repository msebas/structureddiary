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
			->orderBy('id', 'ASC');

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
		$chain = [$question];

		$current = $question;
		while ($current->getPreviousVersionId() !== null) {
			$current = $this->getQuestion($current->getPreviousVersionId());
			array_unshift($chain, $current);
		}

		$current = $question;
		while ($current->getNextVersionId() !== null) {
			$current = $this->getQuestion($current->getNextVersionId());
			$chain[] = $current;
		}

		return $chain;
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
			->orderBy('created_at', 'ASC')
			->addOrderBy('id', 'ASC');

		/** @var list<Question> $questions */
		$questions = array_values(array_filter(
			$this->findEntities($qb),
			static fn (Question $question): bool => $question->getCreatedAt() <= $timestamp
		));
		$byId = [];
		foreach ($questions as $question) {
			$byId[$question->getId()] = $question;
		}

		$currentByRoot = [];
		foreach ($questions as $question) {
			$rootId = $question->getId();
			$current = $question;
			while ($current->getPreviousVersionId() !== null && isset($byId[$current->getPreviousVersionId()])) {
				$current = $byId[$current->getPreviousVersionId()];
				$rootId = $current->getId();
			}
			$currentByRoot[$rootId] = $question;
		}

		$result = array_values(array_filter(
			$currentByRoot,
			static fn (Question $question): bool => !$onlyActive || $question->getActive()
		));
		usort(
			$result,
			static fn (Question $a, Question $b): int => [$a->getLabel(), $a->getId()] <=> [$b->getLabel(), $b->getId()]
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
		$question->setDiaryId($diaryId);
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

		return $this->insert($question);
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
		$targetLabel = $label ?? $displayText ?? $question->getLabel();
		$targetDisplayText = $displayText ?? $label ?? $question->getDisplayText();
		$targetType = $type ?? $question->getType();
		$targetMinimum = $minimum ?? $question->getMinimum();
		$targetMaximum = $maximum ?? $question->getMaximum();
		$targetChoices = $choices ?? $question->getChoices();
		$targetActive = $active ?? $question->getActive();
		$targetTemplateText = $templateText ?? $question->getTemplateText();
		QuestionTypeValidator::validateQuestionDefinition($targetType, $targetMinimum, $targetMaximum, $targetChoices);

		if (!$hasAnswers) {
			$question->setLabel($targetLabel);
			$question->setDisplayText($targetDisplayText);
			$question->setType($targetType);
			$question->setMinimum($targetMinimum);
			$question->setMaximum($targetMaximum);
			$question->setJsonChoices($targetChoices === null ? null : json_encode($targetChoices, JSON_THROW_ON_ERROR));
			$question->setActive($targetActive);
			$question->setTemplateText($targetTemplateText);

			return $this->update($question);
		}

		if ($question->getNextVersionId() !== null) {
			throw new InvalidArgumentException('Only the current question version can be changed.');
		}

		$newQuestion = new Question();
		$newQuestion->setDiaryId($question->getDiaryId());
		$newQuestion->setCreatedAt($this->getCurrentTimestamp());
		$newQuestion->setLabel($targetLabel);
		$newQuestion->setDisplayText($targetDisplayText);
		$newQuestion->setType($targetType);
		$newQuestion->setMinimum($targetMinimum);
		$newQuestion->setMaximum($targetMaximum);
		$newQuestion->setJsonChoices($targetChoices === null ? null : json_encode($targetChoices, JSON_THROW_ON_ERROR));
		$newQuestion->setActive($targetActive);
		$newQuestion->setTemplateText($targetTemplateText);
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
		$ids = $this->collectQuestionChainIds($question);
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'cnt')
			->from(TableNames::ANSWERS)
			->where(
				$qb->expr()->in('question_id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY))
			);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return ((int)($row['cnt'] ?? 0)) > 0;
	}

	/**
	 * @return list<int>
	 * @throws Exception
	 */
	private function collectQuestionChainIds(Question $question): array {
		$ids = [$question->getId()];
		$current = $question;
		while ($current->getPreviousVersionId() !== null) {
			$current = $this->getQuestion($current->getPreviousVersionId());
			$ids[] = $current->getId();
		}

		$current = $question;
		while ($current->getNextVersionId() !== null) {
			$current = $this->getQuestion($current->getNextVersionId());
			$ids[] = $current->getId();
		}

		return array_values(array_unique($ids));
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

	protected function getCurrentTimestamp(): int {
		return time();
	}
}
