<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use InvalidArgumentException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class AnswerMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, TableNames::ANSWERS, Answer::class);
	}

	/**
	 * @throws Exception
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function getAnswer(int $id): Answer {
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
	 * @return list<Answer>
	 * @throws Exception
	 */
	public function getCurrentAnswersForEntry(int $entryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere($qb->expr()->isNull('next_version_id'))
			->orderBy('question_id', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @return list<Answer>
	 * @throws Exception
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function getAnswerChainForEntryQuestion(int $entryId, int $questionId): array {
		$currentAnswer = $this->getCurrentAnswerForEntryQuestion($entryId, $questionId);
		if ($currentAnswer === null) {
			return [];
		}

		$chain = [$currentAnswer];
		while ($currentAnswer->getPreviousVersionId() !== null) {
			$currentAnswer = $this->getAnswer($currentAnswer->getPreviousVersionId());
			$chain[] = $currentAnswer;
		}

		return array_reverse($chain);
	}

	/**
	 * @throws Exception
	 */
	public function createAnswer(int $diaryId, int $entryId, int $questionId, ?string $textContent, ?float $numericContent): Answer {
		if ($this->currentAnswerExists($entryId, $questionId)) {
			throw new InvalidArgumentException('An answer for this question already exists in the entry.');
		}

		$answer = new Answer();
		$answer->setDiaryId($diaryId);
		$answer->setEntryId($entryId);
		$answer->setQuestionId($questionId);
		$answer->setCreatedAt($this->getCurrentTimestamp());
		$answer->setTextContent($textContent);
		$answer->setNumericContent($numericContent);
		$answer->setPreviousVersionId(null);
		$answer->setNextVersionId(null);

		return $this->insert($answer);
	}

	/**
	 * @throws Exception
	 */
	public function updateAnswer(Answer $answer, ?string $textContent, ?float $numericContent): Answer {
		if ($answer->getNextVersionId() !== null) {
			throw new InvalidArgumentException('Only the current answer version can be changed.');
		}

		$newAnswer = new Answer();
		$newAnswer->setDiaryId($answer->getDiaryId());
		$newAnswer->setEntryId($answer->getEntryId());
		$newAnswer->setQuestionId($answer->getQuestionId());
		$newAnswer->setCreatedAt($this->getCurrentTimestamp());
		$newAnswer->setTextContent($textContent);
		$newAnswer->setNumericContent($numericContent);
		$newAnswer->setPreviousVersionId($answer->getId());
		$newAnswer->setNextVersionId(null);
		$newAnswer = $this->insert($newAnswer);

		$answer->setNextVersionId($newAnswer->getId());
		$this->update($answer);

		return $newAnswer;
	}

	/**
	 * @throws Exception
	 */
	public function deleteAnswer(Answer $answer): Answer {
		if ($answer->getPreviousVersionId() !== null) {
			$previousAnswer = $this->getAnswer($answer->getPreviousVersionId());
			$previousAnswer->setNextVersionId($answer->getNextVersionId());
			$this->update($previousAnswer);
		}

		if ($answer->getNextVersionId() !== null) {
			$nextAnswer = $this->getAnswer($answer->getNextVersionId());
			$nextAnswer->setPreviousVersionId($answer->getPreviousVersionId());
			$this->update($nextAnswer);
		}

		$this->delete($answer);

		return $answer;
	}

	/**
	 * @throws Exception
	 */
	private function currentAnswerExists(int $entryId, int $questionId): bool {
		return $this->getCurrentAnswerForEntryQuestion($entryId, $questionId) !== null;
	}

	/**
	 * @throws Exception
	 */
	protected function getCurrentAnswerForEntryQuestion(int $entryId, int $questionId): ?Answer {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('question_id', $qb->createNamedParameter($questionId, IQueryBuilder::PARAM_INT))
			)
			->andWhere($qb->expr()->isNull('next_version_id'))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			return null;
		}

		return $this->getAnswer((int)$row['id']);
	}

	protected function getCurrentTimestamp(): int {
		return time();
	}
}
