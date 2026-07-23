<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

use InvalidArgumentException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCA\StructuredDiary\Service\AnalysisConfigService;
use OCP\IURLGenerator;
use Sabre\DAV\UUIDUtil;

class AnalysisJobMapper extends QBMapper {
	/** @var list<string> */
	private const COLUMNS = [
		'id', 'diary_id', 'uuid', 'created_by', 'created_at', 'updated_at',
		'data_from', 'data_until', 'started_at', 'finished_at', 'title',
		'language', 'analysis_type', 'status', 'progress', 'output_types',
		'parameters_json', 'llm_url', 'llm_header', 'python_job_id', 'token',
		'storage_path', 'manifest_path', 'status_message', 'error_message',
		'cancel_requested_at', 'artifacts_downloaded', 'python_deleted',
	];
	private const PYTHON_UPDATE_TRANSITIONS = [
		AnalysisJob::STATUS_SUBMITTED => [AnalysisJob::STATUS_READY_QUEUE, AnalysisJob::STATUS_QUEUED, AnalysisJob::STATUS_LOAD_DATA, AnalysisJob::STATUS_RUNNING, AnalysisJob::STATUS_RESTART, AnalysisJob::STATUS_WORKER_UPLOAD, AnalysisJob::STATUS_JOB_FAILED, AnalysisJob::STATUS_JOB_COMPLETED],
		AnalysisJob::STATUS_READY_QUEUE => [AnalysisJob::STATUS_QUEUED, AnalysisJob::STATUS_LOAD_DATA, AnalysisJob::STATUS_RUNNING, AnalysisJob::STATUS_RESTART, AnalysisJob::STATUS_WORKER_UPLOAD, AnalysisJob::STATUS_JOB_FAILED, AnalysisJob::STATUS_JOB_COMPLETED],
		AnalysisJob::STATUS_QUEUED => [AnalysisJob::STATUS_LOAD_DATA, AnalysisJob::STATUS_RUNNING, AnalysisJob::STATUS_RESTART, AnalysisJob::STATUS_WORKER_UPLOAD, AnalysisJob::STATUS_JOB_FAILED, AnalysisJob::STATUS_JOB_COMPLETED],
		AnalysisJob::STATUS_LOAD_DATA => [AnalysisJob::STATUS_RUNNING, AnalysisJob::STATUS_RESTART, AnalysisJob::STATUS_WORKER_UPLOAD, AnalysisJob::STATUS_JOB_FAILED, AnalysisJob::STATUS_JOB_COMPLETED],
		AnalysisJob::STATUS_RUNNING => [AnalysisJob::STATUS_RESTART, AnalysisJob::STATUS_WORKER_UPLOAD, AnalysisJob::STATUS_JOB_FAILED, AnalysisJob::STATUS_JOB_COMPLETED],
		AnalysisJob::STATUS_WORKER_UPLOAD => [AnalysisJob::STATUS_JOB_FAILED, AnalysisJob::STATUS_JOB_COMPLETED],
		AnalysisJob::STATUS_RESTART => [AnalysisJob::STATUS_RUNNING, AnalysisJob::STATUS_WORKER_UPLOAD, AnalysisJob::STATUS_JOB_FAILED, AnalysisJob::STATUS_JOB_COMPLETED],
		AnalysisJob::STATUS_CANCEL_REQUESTED => [AnalysisJob::STATUS_JOB_CANCELED],
	];

	public function __construct(
		IDBConnection $db,
		private DiaryMapper $diaryMapper,
		private AnalysisConfigService $configService,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($db, TableNames::ANALYSIS_JOBS, AnalysisJob::class);
	}

	/**
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getJob(int $id): AnalysisJob {
		$qb = $this->db->getQueryBuilder();
		$qb->select(...self::COLUMNS)
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		return $this->decorateStorageUrl($this->findEntity($qb));
	}

	/**
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getJobByUuid(string $uuid): AnalysisJob {
		$uuid = trim($uuid);
		if ($uuid === '') {
			throw new DoesNotExistException('Analysis job not found.');
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select(...self::COLUMNS)
			->from($this->getTableName())
			->where($qb->expr()->eq('uuid', $qb->createNamedParameter($uuid, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);

		return $this->decorateStorageUrl($this->findEntity($qb));
	}

	/**
	 * @return list<AnalysisJob>
	 * @throws Exception
	 */
	public function getJobsForUser(string $userId, ?int $changedSince = null): array {
		$qb = $this->db->getQueryBuilder();
		$expr = $qb->expr();
		$qb->select(...array_map(static fn (string $column): string => 'j.' . $column, self::COLUMNS))
			->from($this->getTableName(), 'j')
			->innerJoin('j', TableNames::DIARIES, 'd', $expr->eq('j.diary_id', 'd.id'))
			->leftJoin(
				'j',
				TableNames::DIARY_SHARES,
				's',
				$expr->andX(
					$expr->eq('j.diary_id', 's.diary_id'),
					$expr->eq('s.shared_with', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
				)
			)
			->where(
				$expr->orX(
					$expr->eq('d.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
					$expr->isNotNull('s.id')
				)
			)
			->orderBy('j.updated_at', 'DESC')
			->addOrderBy('j.id', 'DESC');

		if ($changedSince !== null) {
			$qb->andWhere($expr->gte('j.updated_at', $qb->createNamedParameter($changedSince, IQueryBuilder::PARAM_INT)));
		}

		/** @var list<AnalysisJob> $jobs */
		$jobs = $this->findEntities($qb);
		$jobs = array_values(array_filter($jobs, function (AnalysisJob $job) use ($userId): bool {
			try {
				$this->assertUserCanAnalyzeJob($job, $userId);
				return true;
			} catch (DoesNotExistException) {
				return false;
			}
		}));

		return array_map(fn (AnalysisJob $job): AnalysisJob => $this->decorateStorageUrl($job), $jobs);
	}

	/**
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getJobForUser(int $id, string $userId): AnalysisJob {
		$job = $this->getJob($id);
		$this->assertUserCanAnalyzeJob($job, $userId);

		return $job;
	}

	/**
	 * @param list<string>|null $outputTypes
	 * @param array<string, mixed>|null $parameters
	 * @throws Exception
	 */
	public function createJob(
		int $diaryId,
		string $createdBy,
		int $dataFrom,
		int $dataUntil,
		string $title,
		string $language,
		bool $start,
		?array $outputTypes = null,
		?array $parameters = null,
	): AnalysisJob {
		$this->diaryMapper->getDiaryForUser($diaryId, $createdBy, DiaryPermissions::ANALYZE);
		$title = trim($title);
		if ($title === '') {
			throw new InvalidArgumentException('Job title is required.');
		}
		if ($dataFrom > $dataUntil) {
			throw new InvalidArgumentException('dataFrom must be less than or equal to dataUntil.');
		}
		$now = $this->getCurrentTimestamp();
		$token = $this->createToken();
		$job = new AnalysisJob();
		$job->setDiaryId($diaryId);
		$job->setUuid($this->createUuid());
		$job->setCreatedBy($createdBy);
		$job->setCreatedAt($now);
		$job->setUpdatedAt($now);
		$job->setDataFrom($dataFrom);
		$job->setDataUntil($dataUntil);
		$job->setStartedAt(null);
		$job->setFinishedAt(null);
		$job->setTitle($title);
		$job->setLanguage($this->normalizeLanguage($language));
		$job->setAnalysisType(AnalysisJob::TYPE_STANDARD);
		$job->setStatus($start ? AnalysisJob::STATUS_SUBMITTED : AnalysisJob::STATUS_DRAFT);
		$job->setProgress(0.0);
		$job->setOutputTypes(json_encode($this->normalizeOutputTypes($outputTypes), JSON_THROW_ON_ERROR));
		$job->setParametersJson(json_encode($this->normalizeParameters($parameters), JSON_THROW_ON_ERROR));
		$job->setLlmUrl(null);
		$job->setLlmHeader(null);
		$job->setPythonJobId(null);
		$job->setToken($token);
		$job->setStoragePath($this->configService->getOutputBaseFolder() . '/.pending-' . $now . '-' . substr($token, 0, 16));
		$job->setManifestPath(null);
		$job->setStatusMessage('');
		$job->setErrorMessage(null);
		$job->setCancelRequestedAt(null);
		$job->setArtifactsDownloaded(false);
		$job->setPythonDeleted(false);

		$inserted = $this->insert($job);
		$diary = $this->diaryMapper->getDiary($diaryId);
		$inserted->setStoragePath($this->configService->getOutputBaseFolder() . '/' . $this->slug($diary->getTitle()) . '-' . $diaryId . '/' . $this->slug($title) . '-' . $inserted->getId());

		return $this->decorateStorageUrl($this->update($inserted));
	}

	/**
	 * @param list<string>|null $outputTypes
	 * @param array<string, mixed>|null $parameters
	 * @throws Exception
	 */
	public function updateDraftJob(
		AnalysisJob $job,
		?int $dataFrom = null,
		?int $dataUntil = null,
		?string $title = null,
		?string $language = null,
		?array $outputTypes = null,
		?array $parameters = null,
		?string $status = null,
	): AnalysisJob {
		if ($job->getStatus() !== AnalysisJob::STATUS_DRAFT) {
			throw new InvalidArgumentException('Only draft jobs can be edited.');
		}
		if ($dataFrom !== null) {
			$job->setDataFrom($dataFrom);
		}
		if ($dataUntil !== null) {
			$job->setDataUntil($dataUntil);
		}
		if ($job->getDataFrom() > $job->getDataUntil()) {
			throw new InvalidArgumentException('dataFrom must be less than or equal to dataUntil.');
		}
		if ($title !== null) {
			$title = trim($title);
			if ($title === '') {
				throw new InvalidArgumentException('Job title cannot be empty.');
			}
			$job->setTitle($title);
		}
		if ($language !== null) {
			$job->setLanguage($this->normalizeLanguage($language));
		}
		if ($outputTypes !== null) {
			$job->setOutputTypes(json_encode($this->normalizeOutputTypes($outputTypes), JSON_THROW_ON_ERROR));
		}
		if ($parameters !== null) {
			$job->setParametersJson(json_encode($this->normalizeParameters($parameters), JSON_THROW_ON_ERROR));
		}
		if ($status !== null) {
			if ($status !== AnalysisJob::STATUS_SUBMITTED) {
				throw new InvalidArgumentException('Draft jobs can only be started with SUBMITTED.');
			}
			$job->setStatus(AnalysisJob::STATUS_SUBMITTED);
		}
		$job->setUpdatedAt($this->getCurrentTimestamp());

		return $this->decorateStorageUrl($this->update($job));
	}

	/**
	 * @throws Exception
	 */
	public function requestCancel(AnalysisJob $job): AnalysisJob {
		if (in_array($job->getStatus(), [
			AnalysisJob::STATUS_JOB_FAILED,
			AnalysisJob::STATUS_JOB_COMPLETED,
		], true)) {
			$now = $this->getCurrentTimestamp();
			$job->setStatus(AnalysisJob::STATUS_JOB_CANCELED);
			$job->setCancelRequestedAt($now);
			$job->setFinishedAt($job->getFinishedAt() ?? $now);
			$job->setUpdatedAt($now);

			return $this->decorateStorageUrl($this->update($job));
		}

		if (!in_array($job->getStatus(), [
			AnalysisJob::STATUS_SUBMITTED,
			AnalysisJob::STATUS_READY_QUEUE,
			AnalysisJob::STATUS_QUEUED,
			AnalysisJob::STATUS_LOAD_DATA,
			AnalysisJob::STATUS_RUNNING,
			AnalysisJob::STATUS_RESTART,
		], true)) {
			throw new InvalidArgumentException('Job cannot be canceled in its current status.');
		}
		$now = $this->getCurrentTimestamp();
		$job->setStatus(AnalysisJob::STATUS_CANCEL_REQUESTED);
		$job->setCancelRequestedAt($now);
		$job->setUpdatedAt($now);

		return $this->decorateStorageUrl($this->update($job));
	}

	/**
	 * @throws Exception
	 */
	public function deleteFinishedJob(AnalysisJob $job): AnalysisJob {
		if (!$job->isTerminalWebStatus()) {
			throw new InvalidArgumentException('Only canceled, failed, or completed jobs can be deleted.');
		}
		$this->delete($job);

		return $this->decorateStorageUrl($job);
	}

	/**
	 * @return list<AnalysisJob>
	 * @throws Exception
	 */
	public function getJobsForPython(?int $changedSince = null): array {
		$qb = $this->db->getQueryBuilder();
		$expr = $qb->expr();
		$statuses = [
			AnalysisJob::STATUS_SUBMITTED,
			AnalysisJob::STATUS_READY_QUEUE,
			AnalysisJob::STATUS_QUEUED,
			AnalysisJob::STATUS_LOAD_DATA,
			AnalysisJob::STATUS_RUNNING,
			AnalysisJob::STATUS_WORKER_UPLOAD,
			AnalysisJob::STATUS_RESTART,
			AnalysisJob::STATUS_CANCEL_REQUESTED,
			AnalysisJob::STATUS_JOB_CANCELED,
			AnalysisJob::STATUS_JOB_FAILED,
			AnalysisJob::STATUS_JOB_COMPLETED,
		];
		$statusConditions = [];
		foreach ($statuses as $index => $status) {
			$statusConditions[] = $expr->eq('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_STR, ':python_status' . $index));
		}
		$qb->select(...self::COLUMNS)
			->from($this->getTableName())
			->where($expr->orX(...$statusConditions))
			->orderBy('updated_at', 'ASC')
			->addOrderBy('id', 'ASC');

		if ($changedSince !== null) {
			$qb->andWhere($expr->gte('updated_at', $qb->createNamedParameter($changedSince, IQueryBuilder::PARAM_INT)));
		}

		return array_map(fn (AnalysisJob $job): AnalysisJob => $this->decorateStorageUrl($job), $this->findEntities($qb));
	}

	/**
	 * @param list<string> $statuses
	 * @return list<AnalysisJob>
	 * @throws Exception
	 */
	public function getJobsByStatuses(array $statuses, int $limit = 50): array {
		if ($statuses === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$conditions = [];
		foreach ($statuses as $index => $status) {
			$conditions[] = $qb->expr()->eq('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_STR, ':status' . $index));
		}
		$qb->select(...self::COLUMNS)
			->from($this->getTableName())
			->where($qb->expr()->orX(...$conditions))
			->orderBy('updated_at', 'ASC')
			->addOrderBy('id', 'ASC')
			->setMaxResults(max(1, $limit));

		/** @var list<AnalysisJob> $jobs */
		$jobs = $this->findEntities($qb);

		return array_map(fn (AnalysisJob $job): AnalysisJob => $this->decorateStorageUrl($job), $jobs);
	}

	/**
	 * @throws Exception
	 */
	public function failJob(AnalysisJob $job, string $message): AnalysisJob {
		$job->setStatus(AnalysisJob::STATUS_FAILED);
		$job->setErrorMessage($message);
		$job->setFinishedAt($this->getCurrentTimestamp());
		$job->setUpdatedAt($this->getCurrentTimestamp());

		return $this->decorateStorageUrl($this->update($job));
	}

	/**
	 * @throws Exception
	 */
	public function finishCleanup(AnalysisJob $job): AnalysisJob {
		$expectedStatus = $job->getStatus();
		$target = match ($expectedStatus) {
			AnalysisJob::STATUS_JOB_CANCELED => AnalysisJob::STATUS_CANCELED,
			AnalysisJob::STATUS_JOB_FAILED => AnalysisJob::STATUS_FAILED,
			AnalysisJob::STATUS_JOB_COMPLETED => AnalysisJob::STATUS_COMPLETED,
			default => throw new InvalidArgumentException('Job is not ready for cleanup finalization.'),
		};
		$now = $this->getCurrentTimestamp();
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('status', $qb->createNamedParameter($target, IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
			->set('finished_at', $qb->createNamedParameter($job->getFinishedAt() ?? $now, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($job->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($expectedStatus, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();

		return $this->getJob($job->getId());
	}

    /**
     * @throws Exception
     */
    public function finishPythonDelete(AnalysisJob $job): AnalysisJob {
        $this->assertPythonCanDelete($job);
        $expectedStatus = $job->getStatus();
        $now = $this->getCurrentTimestamp();
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('python_deleted', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
            ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($job->getId(), IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($expectedStatus, IQueryBuilder::PARAM_STR)));
        $qb->executeStatement();

        return $this->getJob($job->getId());
    }

    /**
     * @return list<string>
     * @throws Exception
     */
    public function getJobsForPythonToDelete(?int $changedSince = null): array {
        $qb = $this->db->getQueryBuilder();
        $expr = $qb->expr();
        $statuses = [
            AnalysisJob::STATUS_CANCELED,
            AnalysisJob::STATUS_FAILED,
            AnalysisJob::STATUS_COMPLETED,
        ];
        $statusConditions = [];
        foreach ($statuses as $index => $status) {
            $statusConditions[] = $expr->eq('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_STR, ':python_status' . $index));
        }
        $qb->select(...self::COLUMNS)
            ->from($this->getTableName())
            ->where($expr->andX($expr->orX(...$statusConditions), $expr->eq("python_deleted", $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))))
            ->orderBy('updated_at', 'ASC')
            ->addOrderBy('id', 'ASC');

        if ($changedSince !== null) {
            $qb->andWhere($expr->gte('updated_at', $qb->createNamedParameter($changedSince, IQueryBuilder::PARAM_INT)));
        }

        return array_map(static fn (AnalysisJob $job): string => $job->getUuid(), $this->findEntities($qb));
    }

	/**
	 * Atomically reserve the artifact manifest for a job. The caller must keep
	 * the surrounding database transaction open until every artifact is stored.
	 *
	 * @throws Exception
	 */
	public function claimArtifactManifest(AnalysisJob $job): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('artifacts_downloaded', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->set('updated_at', $qb->createNamedParameter($this->getCurrentTimestamp(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($job->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('artifacts_downloaded', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));

		return $qb->executeStatement() === 1;
	}

	/**
	 * @throws Exception
	 */
	public function updateFromPython(AnalysisJob $job, ?string $status, ?float $progress, ?string $statusMessage, ?string $errorMessage): AnalysisJob {
		$statusChanges = $status !== null && $status !== $job->getStatus();
		if (!$statusChanges && $job->isTerminalWebStatus()) {
			throw new InvalidArgumentException('Finalized jobs cannot be updated by the analysis service.');
		}
		if (!$statusChanges && $progress !== null && $progress < $job->getProgress()) {
			throw new InvalidArgumentException('Progress cannot decrease without a status change.');
		}
		if ($statusChanges) {
			if ($job->getStatus() === AnalysisJob::STATUS_CANCEL_REQUESTED && in_array($status, [AnalysisJob::STATUS_JOB_FAILED, AnalysisJob::STATUS_JOB_COMPLETED], true)) {
				$status = AnalysisJob::STATUS_JOB_CANCELED;
			}
			$this->assertPythonTransition($job->getStatus(), $status);
			$job->setStatus($status);
			if ($status === AnalysisJob::STATUS_QUEUED && $job->getStartedAt() === null) {
				$job->setStartedAt($this->getCurrentTimestamp());
			}
			if (in_array($status, [AnalysisJob::STATUS_JOB_FAILED, AnalysisJob::STATUS_JOB_COMPLETED, AnalysisJob::STATUS_JOB_CANCELED], true)) {
				$job->setFinishedAt($this->getCurrentTimestamp());
				if ($status === AnalysisJob::STATUS_JOB_COMPLETED) {
					$job->setProgress(100.0);
				}
			}
		}
		if ($progress !== null) {
			$job->setProgress($this->normalizeProgress($progress));
		}
		if ($statusMessage !== null) {
			$job->setStatusMessage($statusMessage);
		}
		if ($errorMessage !== null) {
			$job->setErrorMessage($errorMessage);
		}
		$job->setUpdatedAt($this->getCurrentTimestamp());

		return $this->decorateStorageUrl($this->update($job));
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function assertPythonAccess(AnalysisJob $job, string $token, ?string $jobUuid, bool $requireLoadData, int $now): void {
		if ($job->getToken() === null || !hash_equals($job->getToken(), $token)) {
			throw new DoesNotExistException('Invalid job token.');
		}
		if ($jobUuid !== $job->getUuid()) {
			throw new DoesNotExistException('Invalid analysis job uuid.');
		}
		if ($requireLoadData && $job->getStatus() !== AnalysisJob::STATUS_LOAD_DATA) {
			throw new DoesNotExistException('Job is not loading data.');
		}
		if ($requireLoadData && $job->getUpdatedAt() < ($now - 86400)) {
			throw new DoesNotExistException('Job data access timed out.');
		}
	}

	/**
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function assertUserCanAnalyzeJob(AnalysisJob $job, string $userId): void {
		$this->diaryMapper->getDiaryForUser($job->getDiaryId(), $userId, DiaryPermissions::ANALYZE);
	}

	protected function getCurrentTimestamp(): int {
		return time();
	}

	/**
	 * @param list<string>|null $outputTypes
	 * @return list<string>
	 */
	private function normalizeOutputTypes(?array $outputTypes): array {
		$outputTypes ??= [AnalysisJob::OUTPUT_JSON, AnalysisJob::OUTPUT_HTML];
		$normalized = [];
		foreach ($outputTypes as $outputType) {
			$type = strtoupper(trim((string)$outputType));
			if (!in_array($type, AnalysisJob::outputTypes(), true)) {
				throw new InvalidArgumentException('Unsupported output type: ' . $type);
			}
			$normalized[] = $type;
		}
		$normalized = array_values(array_unique($normalized));
		if ($normalized === []) {
			throw new InvalidArgumentException('At least one output type is required.');
		}

		return $normalized;
	}

	/**
	 * @param array<string, mixed>|null $parameters
	 * @return array<string, mixed>
	 */
	private function normalizeParameters(?array $parameters): array {
		$parameters ??= [];
		return [
			'includeTextAnalysis' => array_key_exists('includeTextAnalysis', $parameters) ? (bool)$parameters['includeTextAnalysis'] : true,
			'shifting_median_width' => array_key_exists('shifting_median_width', $parameters) ? max(1, (int)$parameters['shifting_median_width']) : 11,
			'plot_std_error' => array_key_exists('plot_std_error', $parameters) ? (bool)$parameters['plot_std_error'] : false,
			'show_single_data_points' => array_key_exists('show_single_data_points', $parameters) ? (bool)$parameters['show_single_data_points'] : true,
		];
	}

	private function normalizeLanguage(string $language): string {
		$language = trim($language);
		if ($language === '' || strlen($language) > 35 || !preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $language)) {
			throw new InvalidArgumentException('language must be a valid BCP 47 language tag.');
		}

		return $language;
	}

	private function normalizeProgress(float $progress): float {
		return max(0.0, min(100.0, $progress));
	}

    private function assertPythonTransition(string $from, string $to): void {
        if (!in_array($to, AnalysisJob::statuses(), true)) {
            throw new InvalidArgumentException('Unsupported job status.');
        }
        if (!in_array($to, self::PYTHON_UPDATE_TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidArgumentException('Invalid Python job status transition.');
        }
    }

    private function assertPythonCanDelete(AnalysisJob $job): void {
        if ($job->getPythonDeleted()) {
            throw new InvalidArgumentException('Job was already marked as deleted.');
        }
        if (!in_array($job->getStatus(), AnalysisJob::terminalWebStatuses(), true)) {
            throw new InvalidArgumentException('Invalid Python job status for deletion.');
        }
    }

	private function createToken(): string {
		return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
	}

	private function createUuid(): string {
		return UUIDUtil::getUUID();
	}

	private function slug(string $value): string {
		$value = preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '';
		$value = trim($value, '-');

		return $value === '' ? 'Analysis' : substr($value, 0, 80);
	}

	private function decorateStorageUrl(AnalysisJob $job): AnalysisJob {
		$filesBase = rtrim($this->urlGenerator->linkTo('files', ''), '/');
		$job->setStorageUrl($this->urlGenerator->getAbsoluteURL('/index.php' . $filesBase . '/files/0?' . http_build_query([
			'dir' => $job->getStoragePath(),
		])));

		return $job;
	}
}
