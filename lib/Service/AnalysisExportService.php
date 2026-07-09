<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Service;

use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\Answer;
use OCA\StructuredDiary\Db\AnswerMapper;
use OCA\StructuredDiary\Db\Diary;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Db\Entry;
use OCA\StructuredDiary\Db\EntryMapper;
use OCA\StructuredDiary\Db\Question;
use OCA\StructuredDiary\Db\TableNames;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class AnalysisExportService {
	public const SCHEMA_VERSION = 1;
	private const MAX_ANSWERS_PER_PAGE = 10000;

	public function __construct(
		private IDBConnection $db,
		private DiaryMapper $diaryMapper,
		private EntryMapper $entryMapper,
		private AnswerMapper $answerMapper,
	) {
	}

	/**
	 * @return array<string, mixed>
	 * @throws Exception
	 */
	public function exportDiary(AnalysisJob $job): array {
		$diary = $this->diaryMapper->getDiary($job->getDiaryId());
		$questions = $this->getQuestionsForJob($job);

		return [
			'schema_version' => self::SCHEMA_VERSION,
			'diary' => [
				'id' => $diary->getId(),
				'title' => $diary->getTitle(),
				'entrySchedule' => $diary->getEntrySchedule(),
			],
			'questions' => array_map(fn (Question $question): array => $this->serializeQuestion($question), $questions),
		];
	}

	/**
	 * @return array<string, mixed>
	 * @throws Exception
	 */
	public function exportEntries(AnalysisJob $job, int $offset = 0): array {
		$questionCount = max(1, count($this->getQuestionChainIdsForJob($job)));
		$questionIds = array_map(static fn (Question $question): int => $question->getId(), $this->getQuestionsForJob($job));
		$limit = max(1, intdiv(self::MAX_ANSWERS_PER_PAGE, $questionCount));
		$totalEntries = $this->countEntriesForJob($job);
		$entries = $this->getEntriesForJob($job, $limit, max(0, $offset));
		$serializedEntries = [];
		foreach ($entries as $entry) {
			$answers = array_values(array_filter(
				$this->answerMapper->getCurrentAnswersForEntry($entry->getId()),
				static fn (Answer $answer): bool => in_array($answer->getQuestionId(), $questionIds, true)
			));
			$serializedEntries[] = $this->serializeEntry($entry, $answers);
		}

		return [
			'schema_version' => self::SCHEMA_VERSION,
			'offset' => max(0, $offset),
			'limit' => $limit,
			'total_entries' => $totalEntries,
			'from_entry' => $entries === [] ? null : $entries[0]->getId(),
			'until_entry' => $entries === [] ? null : $entries[array_key_last($entries)]->getId(),
			'has_more' => max(0, $offset) + count($entries) < $totalEntries,
			'entries' => $serializedEntries,
		];
	}

	/**
	 * @return list<Question>
	 * @throws Exception
	 */
	private function getQuestionsForJob(AnalysisJob $job): array {
		$versionsByChain = $this->getQuestionVersionsByChain($job);
		$answeredQuestionIds = $this->getAnsweredQuestionIdsForJob($job);
		$selected = [];
		foreach ($versionsByChain as $versions) {
			$answered = array_values(array_filter(
				$versions,
				static fn (Question $question): bool => in_array($question->getId(), $answeredQuestionIds, true)
			));
			if ($answered !== []) {
				array_push($selected, ...$answered);
				continue;
			}
			$candidates = array_values(array_filter(
				$versions,
				static fn (Question $question): bool => $question->getCreatedAt() <= $job->getDataUntil()
			));
			if ($candidates !== []) {
				$selected[] = $candidates[array_key_last($candidates)];
			}
		}
		usort($selected, static fn (Question $a, Question $b): int => [$a->getDiaryQuestionOrder(), $a->getCreatedAt(), $a->getId()] <=> [$b->getDiaryQuestionOrder(), $b->getCreatedAt(), $b->getId()]);

		return $selected;
	}

	/**
	 * @return list<int>
	 * @throws Exception
	 */
	private function getQuestionChainIdsForJob(AnalysisJob $job): array {
		return array_values(array_unique(array_map(
			static fn (Question $question): int => $question->getChainId(),
			$this->getQuestionsForJob($job)
		)));
	}

	/**
	 * @return array<int, list<Question>>
	 * @throws Exception
	 */
	private function getQuestionVersionsByChain(AnalysisJob $job): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(TableNames::QUESTIONS)
			->where($qb->expr()->eq('diary_id', $qb->createNamedParameter($job->getDiaryId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lte('created_at', $qb->createNamedParameter($job->getDataUntil(), IQueryBuilder::PARAM_INT)))
			->orderBy('chain_id', 'ASC')
			->addOrderBy('created_at', 'ASC')
			->addOrderBy('id', 'ASC');

		/** @var list<Question> $questions */
		$questions = (new class($this->db) extends \OCP\AppFramework\Db\QBMapper {
			public function __construct(IDBConnection $db) {
				parent::__construct($db, TableNames::QUESTIONS, Question::class);
			}
			public function all(IQueryBuilder $qb): array {
				return $this->findEntities($qb);
			}
		})->all($qb);

		$byChain = [];
		foreach ($questions as $question) {
			if ($this->questionVersionOverlapsJob($question, $questions, $job)) {
				$byChain[$question->getChainId()][] = $question;
			}
		}

		return $byChain;
	}

	/**
	 * @param list<Question> $allQuestions
	 */
	private function questionVersionOverlapsJob(Question $question, array $allQuestions, AnalysisJob $job): bool {
		if ($question->getCreatedAt() > $job->getDataUntil()) {
			return false;
		}
		$nextCreatedAt = null;
		foreach ($allQuestions as $candidate) {
			if ($candidate->getId() === $question->getNextVersionId()) {
				$nextCreatedAt = $candidate->getCreatedAt();
				break;
			}
		}

		return $nextCreatedAt === null || $nextCreatedAt >= $job->getDataFrom();
	}

	/**
	 * @return list<int>
	 * @throws Exception
	 */
	private function getAnsweredQuestionIdsForJob(AnalysisJob $job): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('a.question_id')
			->from(TableNames::ANSWERS, 'a')
			->innerJoin('a', TableNames::ENTRIES, 'e', $qb->expr()->eq('a.entry_id', 'e.id'))
			->where($qb->expr()->eq('e.diary_id', $qb->createNamedParameter($job->getDiaryId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->gte('e.timestamp', $qb->createNamedParameter($job->getDataFrom(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lte('e.timestamp', $qb->createNamedParameter($job->getDataUntil(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('a.next_version_id'));

		$result = $qb->executeQuery();
		$ids = [];
		while (($row = $result->fetch()) !== false) {
			$ids[] = (int)$row['question_id'];
		}
		$result->closeCursor();

		return $ids;
	}

	/**
	 * @return list<Entry>
	 * @throws Exception
	 */
	private function getEntriesForJob(AnalysisJob $job, int $limit, int $offset): array {
		$entries = $this->entryMapper->getEntriesForDiary($job->getDiaryId(), $job->getDataFrom(), $job->getDataUntil(), $limit, $offset);
		usort($entries, static fn (Entry $a, Entry $b): int => [$a->getTimestamp(), $a->getId()] <=> [$b->getTimestamp(), $b->getId()]);

		return $entries;
	}

	/**
	 * @throws Exception
	 */
	private function countEntriesForJob(AnalysisJob $job): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from(TableNames::ENTRIES)
			->where($qb->expr()->eq('diary_id', $qb->createNamedParameter($job->getDiaryId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->gte('timestamp', $qb->createNamedParameter($job->getDataFrom(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lte('timestamp', $qb->createNamedParameter($job->getDataUntil(), IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializeQuestion(Question $question): array {
		return [
			'id' => $question->getId(),
			'diaryQuestionOrder' => $question->getDiaryQuestionOrder(),
			'label' => $question->getLabel(),
			'displayText' => $question->getDisplayText(),
			'type' => $question->getType(),
			'minimum' => $question->getMinimum(),
			'maximum' => $question->getMaximum(),
			'jsonChoices' => $question->getJsonChoices(),
			'templateText' => $question->getTemplateText(),
			'previousVersionId' => $question->getPreviousVersionId(),
			'nextVersionId' => $question->getNextVersionId(),
			'chainId' => $question->getChainId(),
		];
	}

	/**
	 * @param list<Answer> $answers
	 * @return array<string, mixed>
	 */
	private function serializeEntry(Entry $entry, array $answers): array {
		return [
			'id' => $entry->getId(),
			'diaryId' => $entry->getDiaryId(),
			'timestamp' => $entry->getTimestamp(),
			'title' => $entry->getTitle(),
			'answers' => array_map(static fn (Answer $answer): array => [
				'id' => $answer->getId(),
				'diaryId' => $answer->getDiaryId(),
				'entryId' => $answer->getEntryId(),
				'questionId' => $answer->getQuestionId(),
				'createdAt' => $answer->getCreatedAt(),
				'textContent' => $answer->getTextContent(),
				'numericContent' => $answer->getNumericContent(),
				'previousVersionId' => $answer->getPreviousVersionId(),
				'nextVersionId' => $answer->getNextVersionId(),
			], $answers),
		];
	}
}
