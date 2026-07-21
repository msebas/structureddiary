<?php

declare(strict_types=1);

namespace Service;

use OCA\StructuredDiary\Db\AnalysisArtifact;
use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Service\AnalysisArtifactStorageService;
use OCP\Files\IRootFolder;
use PHPUnit\Framework\TestCase;

final class AnalysisArtifactStorageServiceTest extends TestCase {
	public function testRepeatedUploadWithMatchingChecksumReturnsStoredArtifactWithoutWritingFile(): void {
		$artifact = $this->artifact(hash('sha256', 'report'));
		$rootFolder = $this->createMock(IRootFolder::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$rootFolder->expects($this->never())->method('getUserFolder');
		$diaryMapper->expects($this->never())->method('getDiary');
		$artifactMapper->expects($this->never())->method('markDownloaded');

		$result = (new AnalysisArtifactStorageService($rootFolder, $diaryMapper, $artifactMapper))
			->storeArtifactContent($this->job(), $artifact, 'report');

		$this->assertSame($artifact, $result);
	}

	public function testRepeatedUploadWithDifferentChecksumIsRejectedBeforeWritingFile(): void {
		$artifact = $this->artifact(hash('sha256', 'report'));
		$rootFolder = $this->createMock(IRootFolder::class);
		$diaryMapper = $this->createMock(DiaryMapper::class);
		$artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$rootFolder->expects($this->never())->method('getUserFolder');
		$diaryMapper->expects($this->never())->method('getDiary');

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Uploaded artifact checksum does not match the existing artifact.');

		(new AnalysisArtifactStorageService($rootFolder, $diaryMapper, $artifactMapper))
			->storeArtifactContent($this->job(), $artifact, 'other!');
	}

	private function job(): AnalysisJob {
		$job = new AnalysisJob();
		$job->setDiaryId(7);
		return $job;
	}

	private function artifact(string $checksum): AnalysisArtifact {
		$artifact = new AnalysisArtifact();
		$artifact->setSize(6);
		$artifact->setChecksum($checksum);
		$artifact->setDownloaded(true);
		return $artifact;
	}
}
