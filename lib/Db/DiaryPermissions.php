<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

final class DiaryPermissions {
	public const READ = 1;
	public const WRITE = 2;
	public const ANALYZE = 4;
	public const MANAGE = 8;
	public const OWNER = self::READ | self::WRITE | self::ANALYZE | self::MANAGE;

	/**
	 * @return list<int>
	 */
	public static function all(): array {
		return [
			self::READ,
			self::READ | self::WRITE,
			self::READ | self::ANALYZE,
			self::READ | self::MANAGE,
			self::READ | self::WRITE | self::ANALYZE,
			self::READ | self::WRITE | self::MANAGE,
			self::READ | self::ANALYZE | self::MANAGE,
			self::READ | self::WRITE | self::ANALYZE | self::MANAGE,
		];
	}

	public static function canRead(int $permission): bool {
		return $permission > 0;
	}

	public static function canWrite(int $permission): bool {
		return ($permission & self::WRITE) === self::WRITE;
	}

	public static function canAnalyze(int $permission): bool {
		return ($permission & self::ANALYZE) === self::ANALYZE;
	}

	public static function canManage(int $permission): bool {
		return ($permission & self::MANAGE) === self::MANAGE;
	}
}
