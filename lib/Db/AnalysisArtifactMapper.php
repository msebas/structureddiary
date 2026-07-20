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

class AnalysisArtifactMapper extends QBMapper {
	public function __construct(IDBConnection $db, private AnalysisJobMapper $jobMapper) {
		parent::__construct($db, TableNames::ANALYSIS_ARTIFACTS, AnalysisArtifact::class);
	}

	/**
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getArtifact(int $id): AnalysisArtifact {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	/**
	 * @return list<AnalysisArtifact>
	 * @throws Exception
	 */
	public function getArtifactsForJob(int $jobId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('job_id', $qb->createNamedParameter($jobId, IQueryBuilder::PARAM_INT)))
			->orderBy('file_path', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @throws Exception
	 */
	public function artifactPathExists(int $jobId, string $filePath): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'cnt')
			->from($this->getTableName())
			->where($qb->expr()->eq('job_id', $qb->createNamedParameter($jobId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('file_path', $qb->createNamedParameter($this->normalizeRelativePath($filePath), IQueryBuilder::PARAM_STR)));

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return ((int)($row['cnt'] ?? 0)) > 0;
	}

	/**
	 * @return list<AnalysisArtifact>
	 * @throws Exception
	 */
	public function getArtifactsForUser(int $jobId, string $userId): array {
		$job = $this->jobMapper->getJobForUser($jobId, $userId);
		return $this->getArtifactsForJob($job->getId());
	}

	/**
	 * @throws Exception
	 */
	public function createArtifact(
		int $jobId,
		string $artifactType,
		string $mimeType,
		string $fileName,
		string $filePath,
		?int $parentId = null,
		?int $fileId = null,
		?int $pythonParentId = null,
		?int $pythonFileId = null,
		int $size = 0,
		?string $checksum = null,
		bool $downloaded = false,
	): AnalysisArtifact {
		$artifactType = strtoupper(trim($artifactType));
		if (!in_array($artifactType, AnalysisArtifact::types(), true)) {
			throw new InvalidArgumentException('Unsupported artifact type.');
		}
		$fileName = trim($fileName);
		$filePath = $this->normalizeRelativePath($filePath);
		if ($fileName === '') {
			throw new InvalidArgumentException('Artifact file name is required.');
		}
		if ($size < 0) {
			throw new InvalidArgumentException('Artifact size must be zero or positive.');
		}

		$artifact = new AnalysisArtifact();
		$artifact->setParentId($parentId);
		$artifact->setJobId($jobId);
		$artifact->setArtifactType($artifactType);
		$artifact->setMimeType(trim($mimeType) === '' ? 'application/octet-stream' : trim($mimeType));
		$artifact->setFileName($fileName);
		$artifact->setFilePath($filePath);
		$artifact->setFileId($fileId);
		$artifact->setPythonParentId($pythonParentId);
		$artifact->setPythonFileId($pythonFileId);
		$artifact->setSize($size);
		$artifact->setChecksum($checksum);
		$artifact->setCreatedAt($this->getCurrentTimestamp());
		$artifact->setDownloaded($downloaded);

		return $this->insert($artifact);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @throws Exception
	 */
	public function createFromPythonArtifact(int $jobId, array $payload, ?int $localParentId = null): AnalysisArtifact {
		$filePath = (string)($payload['file_path'] ?? $payload['filePath'] ?? $payload['file_name'] ?? $payload['fileName'] ?? 'artifact');
		if ($this->artifactPathExists($jobId, $filePath)) {
			throw new InvalidArgumentException('Artifact file path already exists for this job.');
		}
		$pythonParentId = isset($payload['parent_id']) ? (int)$payload['parent_id'] : (isset($payload['python_parent_id']) ? (int)$payload['python_parent_id'] : null);

		return $this->createArtifact(
			$jobId,
			(string)($payload['artifact_type'] ?? $payload['output_type'] ?? $payload['type'] ?? 'LOG'),
			(string)($payload['mime_type'] ?? $payload['mimeType'] ?? 'application/octet-stream'),
			(string)($payload['file_name'] ?? $payload['fileName'] ?? basename((string)($payload['file_path'] ?? $payload['filePath'] ?? 'artifact'))),
			$filePath,
			$localParentId,
			null,
			$pythonParentId,
			isset($payload['id']) ? (int)$payload['id'] : (isset($payload['python_file_id']) ? (int)$payload['python_file_id'] : null),
			isset($payload['size']) ? (int)$payload['size'] : 0,
			isset($payload['checksum']) ? (string)$payload['checksum'] : null,
			false,
		);
	}

	/**
	 * @throws Exception
	 */
	public function markDownloaded(AnalysisArtifact $artifact, ?int $fileId, int $size, ?string $checksum): AnalysisArtifact {
		$artifact->setFileId($fileId);
		$artifact->setSize($size);
		$artifact->setChecksum($checksum);
		$artifact->setDownloaded(true);

		return $this->update($artifact);
	}

	protected function getCurrentTimestamp(): int {
		return time();
	}

	private function normalizeRelativePath(string $path): string {
		$path = trim(str_replace('\\', '/', $path));
		if ($path === '' || str_starts_with($path, '/')) {
			throw new InvalidArgumentException('Artifact file path must be a relative path without parent traversal.');
		}
		foreach (explode('/', $path) as $segment) {
			if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/^[A-Za-z]:$/', $segment) === 1) {
				throw new InvalidArgumentException('Artifact file path must be a relative path without parent traversal.');
			}
		}

		return $path;
	}
}
