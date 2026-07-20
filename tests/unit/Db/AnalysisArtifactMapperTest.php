<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\AnalysisArtifact;
use OCA\StructuredDiary\Db\AnalysisArtifactMapper;
use OCA\StructuredDiary\Db\AnalysisJobMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

final class AnalysisArtifactMapperTest extends TestCase {
	public function testJsonSerializeDoesNotExposePythonOrDownloadInternals(): void {
		$artifact = new AnalysisArtifact();
		$artifact->setId(10);
		$artifact->setParentId(null);
		$artifact->setJobId(42);
		$artifact->setArtifactType('JSON');
		$artifact->setMimeType('application/json');
		$artifact->setFileName('analysis.json');
		$artifact->setFilePath('analysis.json');
		$artifact->setFileId(99);
		$artifact->setPythonParentId(12);
		$artifact->setPythonFileId(13);
		$artifact->setSize(20);
		$artifact->setChecksum('abc');
		$artifact->setCreatedAt(1234);
		$artifact->setDownloaded(true);

		$data = $artifact->jsonSerialize();

		$this->assertArrayNotHasKey('python_parent_id', $data);
		$this->assertArrayNotHasKey('python_file_id', $data);
		$this->assertArrayNotHasKey('downloaded', $data);
	}

	public function testJsonPythonSerializeOnlyExposesIdMapping(): void {
		$artifact = new AnalysisArtifact();
		$artifact->setId(10);
		$artifact->setParentId(9);
		$artifact->setJobId(42);
		$artifact->setPythonParentId(12);
		$artifact->setPythonFileId(13);
		$artifact->setSize(20);
		$artifact->setChecksum('abc');
		$artifact->setCreatedAt(1234);

		$this->assertSame([
			'id' => 10,
			'parent_id' => 9,
			'python_id' => 13,
			'python_parent_id' => 12,
			'checksum' => 'abc',
		], $artifact->jsonPythonSerialize());
	}

	public function testCreateArtifactRejectsDotPathSegment(): void {
		$mapper = $this->getMockBuilder(AnalysisArtifactMapper::class)
			->setConstructorArgs([$this->createMock(IDBConnection::class), $this->createMock(AnalysisJobMapper::class)])
			->onlyMethods(['insert'])
			->getMock();
		$mapper->expects($this->never())->method('insert');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Artifact file path must be a relative path without parent traversal.');

		$mapper->createArtifact(1, 'JSON', 'application/json', 'analysis.json', 'plots/./analysis.json');
	}

	public function testCreateArtifactRejectsParentTraversal(): void {
		$mapper = $this->getMockBuilder(AnalysisArtifactMapper::class)
			->setConstructorArgs([$this->createMock(IDBConnection::class), $this->createMock(AnalysisJobMapper::class)])
			->onlyMethods(['insert'])
			->getMock();
		$mapper->expects($this->never())->method('insert');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Artifact file path must be a relative path without parent traversal.');

		$mapper->createArtifact(1, 'JSON', 'application/json', 'analysis.json', '../analysis.json');
	}

	public function testCreateArtifactRejectsWindowsAbsolutePathAfterNormalization(): void {
		$mapper = $this->getMockBuilder(AnalysisArtifactMapper::class)
			->setConstructorArgs([$this->createMock(IDBConnection::class), $this->createMock(AnalysisJobMapper::class)])
			->onlyMethods(['insert'])
			->getMock();
		$mapper->expects($this->never())->method('insert');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Artifact file path must be a relative path without parent traversal.');

		$mapper->createArtifact(1, 'JSON', 'application/json', 'analysis.json', 'C:\\Users\\alice\\analysis.json');
	}

	public function testCreateArtifactAllowsLiteralUrlEncodedTraversalText(): void {
		$mapper = $this->getMockBuilder(AnalysisArtifactMapper::class)
			->setConstructorArgs([$this->createMock(IDBConnection::class), $this->createMock(AnalysisJobMapper::class)])
			->onlyMethods(['insert'])
			->getMock();
		$mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(static fn (AnalysisArtifact $artifact): AnalysisArtifact => $artifact);

		$artifact = $mapper->createArtifact(1, 'JSON', 'application/json', 'analysis.json', 'plots/%2e%2e/analysis.json');

		$this->assertSame('plots/%2e%2e/analysis.json', $artifact->getFilePath());
	}

	public function testCreateFromPythonArtifactStoresLocalAndPythonParentIdsSeparately(): void {
		$mapper = $this->getMockBuilder(AnalysisArtifactMapper::class)
			->setConstructorArgs([$this->createMock(IDBConnection::class), $this->createMock(AnalysisJobMapper::class)])
			->onlyMethods(['artifactPathExists', 'insert'])
			->getMock();
		$mapper->expects($this->once())->method('artifactPathExists')->with(1, 'plots/detail.png')->willReturn(false);
		$mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(static fn (AnalysisArtifact $artifact): AnalysisArtifact => $artifact);

		$artifact = $mapper->createFromPythonArtifact(1, [
			'id' => 302,
			'parent_id' => 301,
			'output_type' => 'PLOT',
			'mime_type' => 'image/png',
			'file_name' => 'detail.png',
			'file_path' => 'plots/detail.png',
			'size' => 123,
		], 11);

		$this->assertSame(11, $artifact->getParentId());
		$this->assertSame(301, $artifact->getPythonParentId());
		$this->assertSame(302, $artifact->getPythonFileId());
	}
}
