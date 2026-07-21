<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Service;

use OCA\StructuredDiary\Db\AnalysisArtifact;
use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;

class AnalysisArtifactStorageService {
	public function __construct(
		private IRootFolder $rootFolder,
		private DiaryMapper $diaryMapper,
		private AnalysisArtifactMapper $artifactMapper,
	) {
	}

	public function storeArtifactContent(AnalysisJob $job, AnalysisArtifact $artifact, string $content): AnalysisArtifact {
		if ($artifact->getSize() > 0 && strlen($content) > $artifact->getSize()) {
			throw new \RuntimeException('Uploaded artifact is larger than expected.');
		}
		$checksum = hash('sha256', $content);
		if ($artifact->getDownloaded()) {
			if ($artifact->getChecksum() !== null && !hash_equals($artifact->getChecksum(), $checksum)) {
				throw new \RuntimeException('Uploaded artifact checksum does not match the existing artifact.');
			}

			return $artifact;
		}
		$diary = $this->diaryMapper->getDiary($job->getDiaryId());
		$userFolder = $this->rootFolder->getUserFolder($diary->getUserId());
		$targetPath = ltrim($job->getStoragePath() . '/' . $artifact->getFilePath(), '/');
		$this->ensureParentFolders($userFolder, dirname($targetPath));
		$this->rotateExistingFile($userFolder, $targetPath);
		$file = $userFolder->newFile($targetPath, $content);
		$file->touch($artifact->getCreatedAt());
		if ($artifact->getChecksum() !== null && $artifact->getChecksum() !== '' && !hash_equals($artifact->getChecksum(), $checksum)) {
			$file->delete();
			throw new \RuntimeException('Downloaded artifact checksum does not match.');
		}

		return $this->artifactMapper->markDownloaded($artifact, $file->getId(), strlen($content), $checksum);
	}

	private function ensureParentFolders(Folder $folder, string $path): void {
		$path = trim($path, '/.');
		if ($path === '') {
			return;
		}
		$current = '';
		foreach (explode('/', $path) as $segment) {
			if ($segment === '') {
				continue;
			}
			$current = $current === '' ? $segment : $current . '/' . $segment;
			if (!$folder->nodeExists($current)) {
				$folder->newFolder($current);
			}
		}
	}

	private function rotateExistingFile(Folder $folder, string $targetPath): void {
		if (!$folder->nodeExists($targetPath)) {
			return;
		}
		$oldPath = $this->oldPath($targetPath);
		if ($folder->nodeExists($oldPath)) {
			throw new \RuntimeException('Existing artifact and old artifact backup both exist.');
		}
		$folder->get($targetPath)->move($folder->getFullPath($oldPath));
	}

	private function oldPath(string $targetPath): string {
		$directory = dirname($targetPath);
		$fileName = basename($targetPath);
		$dot = strrpos($fileName, '.');
		$oldName = $dot === false
			? $fileName . '_old'
			: substr($fileName, 0, $dot) . '_old' . substr($fileName, $dot);

		return ($directory === '.' ? '' : $directory . '/') . $oldName;
	}
}
