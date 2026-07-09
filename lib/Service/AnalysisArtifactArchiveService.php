<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Service;

use OCA\StructuredDiary\Db\AnalysisArtifact;
use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\ITempManager;
use ZipArchive;

class AnalysisArtifactArchiveService {
	public function __construct(
		private IRootFolder $rootFolder,
		private DiaryMapper $diaryMapper,
		private AnalysisJobMapper $jobMapper,
		private AnalysisArtifactMapper $artifactMapper,
		private ITempManager $tempManager,
	) {
	}

	/**
	 * @return array{path: string|null, content: string|null, filename: string, contentType: string}
	 */
	public function buildDownload(int $jobId, string $userId, ?string $artifactType, bool $forceZip): array {
		$job = $this->jobMapper->getJobForUser($jobId, $userId);
		$diary = $this->diaryMapper->getDiary($job->getDiaryId());
		$userFolder = $this->rootFolder->getUserFolder($diary->getUserId());
		$artifacts = $this->selectArtifacts($this->artifactMapper->getArtifactsForJob($job->getId()), $artifactType);
		if ($artifacts === []) {
			throw new \RuntimeException('No artifacts found for download.');
		}

		if (!$forceZip && count($artifacts) === 1) {
			$file = $this->artifactFile($userFolder, $job->getStoragePath(), $artifacts[0]);
			return [
				'path' => null,
				'content' => $file->getContent(),
				'filename' => $this->downloadName($artifacts[0]->getFileName()),
				'contentType' => $artifacts[0]->getMimeType(),
			];
		}

		return [
			'path' => $this->buildZip($userFolder, $job->getStoragePath(), $artifacts),
			'content' => null,
			'filename' => $this->zipName($job->getTitle(), $artifactType),
			'contentType' => 'application/zip',
		];
	}

	/**
	 * @return array{content: string, filename: string, contentType: string}
	 */
	public function buildInlineArtifact(int $jobId, int $artifactId, string $userId): array {
		$job = $this->jobMapper->getJobForUser($jobId, $userId);
		$diary = $this->diaryMapper->getDiary($job->getDiaryId());
		$userFolder = $this->rootFolder->getUserFolder($diary->getUserId());
		foreach ($this->artifactMapper->getArtifactsForJob($job->getId()) as $artifact) {
			if ($artifact->getId() !== $artifactId) {
				continue;
			}
			if (!$artifact->getDownloaded()) {
				throw new \RuntimeException('Artifact file is not downloaded yet.');
			}
			$file = $this->artifactFile($userFolder, $job->getStoragePath(), $artifact);
			return [
				'content' => $file->getContent(),
				'filename' => $this->downloadName($artifact->getFileName()),
				'contentType' => $artifact->getMimeType(),
			];
		}

		throw new \RuntimeException('Artifact not found.');
	}

	/**
	 * @param list<AnalysisArtifact> $artifacts
	 * @return list<AnalysisArtifact>
	 */
	private function selectArtifacts(array $artifacts, ?string $artifactType): array {
		$downloaded = array_values(array_filter($artifacts, static fn (AnalysisArtifact $artifact): bool => $artifact->getDownloaded()));
		if ($artifactType === null || $artifactType === '') {
			return $downloaded;
		}

		$type = strtoupper($artifactType);
		$selected = array_values(array_filter($downloaded, static fn (AnalysisArtifact $artifact): bool => $artifact->getArtifactType() === $type));
		$selectedIds = array_fill_keys(array_map(static fn (AnalysisArtifact $artifact): int => $artifact->getId(), $selected), true);
		$selectedPythonIds = array_fill_keys(array_values(array_filter(array_map(static fn (AnalysisArtifact $artifact): ?int => $artifact->getPythonFileId(), $selected))), true);
		$changed = true;
		while ($changed) {
			$changed = false;
			foreach ($downloaded as $artifact) {
				$parentId = $artifact->getParentId();
				$pythonParentId = $artifact->getPythonParentId();
				if (
					!isset($selectedIds[$artifact->getId()])
					&& (($parentId !== null && isset($selectedIds[$parentId])) || ($pythonParentId !== null && isset($selectedPythonIds[$pythonParentId])))
				) {
					$selected[] = $artifact;
					$selectedIds[$artifact->getId()] = true;
					$pythonFileId = $artifact->getPythonFileId();
					if ($pythonFileId !== null) {
						$selectedPythonIds[$pythonFileId] = true;
					}
					$changed = true;
				}
			}
		}

		return $selected;
	}

	/**
	 * @param list<AnalysisArtifact> $artifacts
	 */
	private function buildZip(Folder $userFolder, string $storagePath, array $artifacts): string {
		if (!class_exists(ZipArchive::class)) {
			throw new \RuntimeException('ZIP support is not available on this server.');
		}
		$zipPath = $this->tempManager->getTemporaryFile('.zip');
		if ($zipPath === false) {
			throw new \RuntimeException('Unable to create temporary ZIP file.');
		}

		$zip = new ZipArchive();
		if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			throw new \RuntimeException('Unable to open temporary ZIP file.');
		}

		try {
			foreach ($artifacts as $artifact) {
				$file = $this->artifactFile($userFolder, $storagePath, $artifact);
				if (!$zip->addFromString($artifact->getFilePath(), $file->getContent())) {
					throw new \RuntimeException('Unable to add artifact to ZIP file.');
				}
			}
		} finally {
			$zip->close();
		}

		return $zipPath;
	}

	private function artifactFile(Folder $userFolder, string $storagePath, AnalysisArtifact $artifact): File {
		$node = $userFolder->get(ltrim($storagePath . '/' . $artifact->getFilePath(), '/'));
		if (!$node instanceof File) {
			throw new \RuntimeException('Artifact file is no longer available.');
		}

		return $node;
	}

	private function zipName(string $title, ?string $artifactType): string {
		$suffix = $artifactType === null || $artifactType === '' ? 'all' : strtolower($artifactType);
		return $this->slug($title) . '-' . $suffix . '.zip';
	}

	private function downloadName(string $fileName): string {
		return str_replace(["\r", "\n", '/', '\\'], '_', $fileName);
	}

	private function slug(string $value): string {
		$slug = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '-', $value));
		$slug = trim($slug, '-');
		return $slug === '' ? 'analysis' : substr($slug, 0, 80);
	}
}
