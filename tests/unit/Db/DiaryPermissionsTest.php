<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\DiaryPermissions;
use PHPUnit\Framework\TestCase;

final class DiaryPermissionsTest extends TestCase {
	public function testAllReturnsSupportedSharePermissionCombinations(): void {
		$this->assertSame([
			DiaryPermissions::READ,
			DiaryPermissions::READ | DiaryPermissions::WRITE,
			DiaryPermissions::READ | DiaryPermissions::ANALYZE,
			DiaryPermissions::READ | DiaryPermissions::MANAGE,
			DiaryPermissions::READ | DiaryPermissions::WRITE | DiaryPermissions::ANALYZE,
			DiaryPermissions::READ | DiaryPermissions::WRITE | DiaryPermissions::MANAGE,
			DiaryPermissions::READ | DiaryPermissions::ANALYZE | DiaryPermissions::MANAGE,
			DiaryPermissions::READ | DiaryPermissions::WRITE | DiaryPermissions::ANALYZE | DiaryPermissions::MANAGE,
		], DiaryPermissions::all());
	}

	public function testCanReadRejectsZeroPermission(): void {
		$this->assertFalse(DiaryPermissions::canRead(0));
	}

	public function testCanReadAcceptsReadPermission(): void {
		$this->assertTrue(DiaryPermissions::canRead(DiaryPermissions::READ));
	}

	public function testCanWriteRejectsReadOnlyPermission(): void {
		$this->assertFalse(DiaryPermissions::canWrite(DiaryPermissions::READ));
	}

	public function testCanWriteAcceptsWritePermission(): void {
		$this->assertTrue(DiaryPermissions::canWrite(DiaryPermissions::READ | DiaryPermissions::WRITE));
	}

	public function testCanAnalyzeRejectsMissingAnalyzeBit(): void {
		$this->assertFalse(DiaryPermissions::canAnalyze(DiaryPermissions::READ | DiaryPermissions::WRITE));
	}

	public function testCanAnalyzeAcceptsAnalyzePermission(): void {
		$this->assertTrue(DiaryPermissions::canAnalyze(DiaryPermissions::READ | DiaryPermissions::ANALYZE));
	}

	public function testCanManageRejectsMissingManageBit(): void {
		$this->assertFalse(DiaryPermissions::canManage(DiaryPermissions::READ | DiaryPermissions::WRITE));
	}

	public function testCanManageAcceptsManagePermission(): void {
		$this->assertTrue(DiaryPermissions::canManage(DiaryPermissions::READ | DiaryPermissions::MANAGE));
	}

	public function testOwnerPermissionIncludesAllCapabilities(): void {
		$this->assertTrue(DiaryPermissions::canRead(DiaryPermissions::OWNER));
		$this->assertTrue(DiaryPermissions::canWrite(DiaryPermissions::OWNER));
		$this->assertTrue(DiaryPermissions::canAnalyze(DiaryPermissions::OWNER));
		$this->assertTrue(DiaryPermissions::canManage(DiaryPermissions::OWNER));
	}
}
