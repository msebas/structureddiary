<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\DiaryShare;
use OCA\StructuredDiary\Db\DiaryShareMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DiaryShareMapperTest extends TestCase {
	private IDBConnection&MockObject $db;

	protected function setUp(): void {
		parent::setUp();
		$this->db = $this->createMock(IDBConnection::class);
	}

	public function testDeleteShareReturnsLoadedShare(): void {
		$share = new DiaryShare();
		$share->setId(7);

		$mapper = $this->getMockBuilder(DiaryShareMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getShare', 'delete'])
			->getMock();

		$mapper->expects($this->once())->method('getShare')->with(7)->willReturn($share);
		$mapper->expects($this->once())->method('delete')->with($share)->willReturn($share);

		$this->assertSame($share, $mapper->deleteShare(7));
	}

	public function testDeleteSharePropagatesMissingShare(): void {
		$mapper = $this->getMockBuilder(DiaryShareMapper::class)
			->setConstructorArgs([$this->db])
			->onlyMethods(['getShare', 'delete'])
			->getMock();

		$mapper->expects($this->once())->method('getShare')->with(7)->willThrowException(new DoesNotExistException('missing'));
		$mapper->expects($this->never())->method('delete');

		$this->expectException(DoesNotExistException::class);
		$this->expectExceptionMessage('missing');

		$mapper->deleteShare(7);
	}
}
