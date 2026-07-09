<?php

declare(strict_types=1);

namespace Service;

use OCA\StructuredDiary\Db\AnalysisArtifact;
use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJob;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCA\StructuredDiary\Db\Diary;
use OCA\StructuredDiary\Db\DiaryMapper;
use OCA\StructuredDiary\Service\AnalysisArtifactArchiveService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\ITempManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class AnalysisArtifactArchiveServiceTest extends TestCase {
	private IRootFolder&MockObject $rootFolder;
	private DiaryMapper&MockObject $diaryMapper;
	private AnalysisJobMapper&MockObject $jobMapper;
	private AnalysisArtifactMapper&MockObject $artifactMapper;
	private ITempManager&MockObject $tempManager;
	private Folder&MockObject $userFolder;

	protected function setUp(): void {
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->diaryMapper = $this->createMock(DiaryMapper::class);
		$this->jobMapper = $this->createMock(AnalysisJobMapper::class);
		$this->artifactMapper = $this->createMock(AnalysisArtifactMapper::class);
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->userFolder = $this->createMock(Folder::class);

		$diary = new Diary();
		$diary->setId(5);
		$diary->setUserId('alice');
		$this->diaryMapper->method('getDiary')->with(5)->willReturn($diary);
		$this->rootFolder->method('getUserFolder')->with('alice')->willReturn($this->userFolder);
		$this->jobMapper->method('getJobForUser')->with(21, 'bob')->willReturn($this->job());
	}

	public function testReturnsSingleArtifactWithoutZip(): void {
		$artifact = $this->artifact(301, 'JSON', 'report.json', 'report.json');
		$this->artifactMapper->method('getArtifactsForJob')->with(21)->willReturn([$artifact]);
		$this->userFolder->method('get')->with('StructuredDiary/Analyses/job/report.json')->willReturn($this->file('{"ok":true}'));

		$result = $this->service()->buildDownload(21, 'bob', 'JSON', false);

		$this->assertNull($result['path']);
		$this->assertSame('{"ok":true}', $result['content']);
		$this->assertSame('report.json', $result['filename']);
		$this->assertSame('application/json', $result['contentType']);
	}

	public function testBuildsZipForAllArtifacts(): void {
		if (!class_exists(ZipArchive::class)) {
			$this->markTestSkipped('ZipArchive extension is not available.');
		}
		$zipPath = tempnam(sys_get_temp_dir(), 'sd-archive-') . '.zip';
		$artifacts = [
			$this->artifact(301, 'HTML', 'report.html', 'report.html'),
			$this->artifact(302, 'JSON', 'manifest.json', 'manifest.json'),
		];
		$this->artifactMapper->method('getArtifactsForJob')->with(21)->willReturn($artifacts);
		$this->tempManager->method('getTemporaryFile')->with('.zip')->willReturn($zipPath);
		$this->userFolder->method('get')->willReturnCallback(fn (string $path): File => $this->file('content:' . $path));

		$result = $this->service()->buildDownload(21, 'bob', null, true);

		$this->assertSame($zipPath, $result['path']);
		$this->assertSame('weekly-analysis-all.zip', $result['filename']);
		$zip = new ZipArchive();
		$this->assertTrue($zip->open($zipPath));
		$this->assertSame('content:StructuredDiary/Analyses/job/report.html', $zip->getFromName('report.html'));
		$this->assertSame('content:StructuredDiary/Analyses/job/manifest.json', $zip->getFromName('manifest.json'));
		$zip->close();
		@unlink($zipPath);
	}

	public function testTypeZipIncludesChildArtifacts(): void {
		if (!class_exists(ZipArchive::class)) {
			$this->markTestSkipped('ZipArchive extension is not available.');
		}
		$zipPath = tempnam(sys_get_temp_dir(), 'sd-html-') . '.zip';
		$artifacts = [
			$this->artifact(301, 'HTML', 'report.html', 'report.html'),
			$this->artifact(302, 'PLOT', 'plot.png', 'plots/plot.png', 301),
			$this->artifact(303, 'JSON', 'data.json', 'data.json'),
		];
		$this->artifactMapper->method('getArtifactsForJob')->with(21)->willReturn($artifacts);
		$this->tempManager->method('getTemporaryFile')->with('.zip')->willReturn($zipPath);
		$this->userFolder->method('get')->willReturnCallback(fn (string $path): File => $this->file('content:' . $path));

		$result = $this->service()->buildDownload(21, 'bob', 'HTML', false);

		$this->assertSame($zipPath, $result['path']);
		$this->assertSame('weekly-analysis-html.zip', $result['filename']);
		$zip = new ZipArchive();
		$this->assertTrue($zip->open($zipPath));
		$this->assertSame('content:StructuredDiary/Analyses/job/report.html', $zip->getFromName('report.html'));
		$this->assertSame('content:StructuredDiary/Analyses/job/plots/plot.png', $zip->getFromName('plots/plot.png'));
		$this->assertFalse($zip->getFromName('data.json'));
		$zip->close();
		@unlink($zipPath);
	}

	public function testTypeZipIncludesPythonChildArtifacts(): void {
		if (!class_exists(ZipArchive::class)) {
			$this->markTestSkipped('ZipArchive extension is not available.');
		}
		$zipPath = tempnam(sys_get_temp_dir(), 'sd-html-py-') . '.zip';
		$html = $this->artifact(301, 'HTML', 'report.html', 'report.html');
		$html->setPythonFileId(901);
		$plot = $this->artifact(302, 'PLOT', 'plot.png', 'plots/plot.png');
		$plot->setPythonParentId(901);
		$this->artifactMapper->method('getArtifactsForJob')->with(21)->willReturn([$html, $plot]);
		$this->tempManager->method('getTemporaryFile')->with('.zip')->willReturn($zipPath);
		$this->userFolder->method('get')->willReturnCallback(fn (string $path): File => $this->file('content:' . $path));

		$result = $this->service()->buildDownload(21, 'bob', 'HTML', false);

		$this->assertSame($zipPath, $result['path']);
		$zip = new ZipArchive();
		$this->assertTrue($zip->open($zipPath));
		$this->assertSame('content:StructuredDiary/Analyses/job/report.html', $zip->getFromName('report.html'));
		$this->assertSame('content:StructuredDiary/Analyses/job/plots/plot.png', $zip->getFromName('plots/plot.png'));
		$zip->close();
		@unlink($zipPath);
	}


	public function testThrowsWhenNoArtifactsMatch(): void {
		$this->artifactMapper->method('getArtifactsForJob')->with(21)->willReturn([$this->artifact(301, 'JSON', 'report.json', 'report.json', null, false)]);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('No artifacts found for download.');

		$this->service()->buildDownload(21, 'bob', null, true);
	}

	private function service(): AnalysisArtifactArchiveService {
		return new AnalysisArtifactArchiveService(
			$this->rootFolder,
			$this->diaryMapper,
			$this->jobMapper,
			$this->artifactMapper,
			$this->tempManager,
		);
	}

	private function job(): AnalysisJob {
		$job = new AnalysisJob();
		$job->setId(21);
		$job->setDiaryId(5);
		$job->setTitle('Weekly Analysis');
		$job->setStoragePath('/StructuredDiary/Analyses/job');
		return $job;
	}

	private function artifact(int $id, string $type, string $fileName, string $filePath, ?int $parentId = null, bool $downloaded = true): AnalysisArtifact {
		$artifact = new AnalysisArtifact();
		$artifact->setId($id);
		$artifact->setJobId(21);
		$artifact->setParentId($parentId);
		$artifact->setArtifactType($type);
		$artifact->setMimeType(match ($type) {
			'JSON' => 'application/json',
			'HTML' => 'text/html',
			default => 'application/octet-stream',
		});
		$artifact->setFileName($fileName);
		$artifact->setFilePath($filePath);
		$artifact->setDownloaded($downloaded);
		return $artifact;
	}

	private function file(string $content): File {
		$file = $this->createMock(File::class);
		$file->method('getContent')->willReturn($content);
		return $file;
	}
}
