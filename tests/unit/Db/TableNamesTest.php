<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\TableNames;
use PHPUnit\Framework\TestCase;

final class TableNamesTest extends TestCase {
	public function testAllTableNamesUseSdPrefix(): void {
		foreach ($this->allTableNames() as $tableName) {
			$this->assertStringStartsWith('sd_', $tableName);
		}
	}

	public function testAllTableNamesStayBelowTwentySevenCharacters(): void {
		foreach ($this->allTableNames() as $tableName) {
			$this->assertLessThan(27, strlen($tableName), $tableName);
		}
	}

	public function testAllTableNamesAreUnique(): void {
		$tableNames = $this->allTableNames();

		$this->assertCount(count(array_unique($tableNames)), $tableNames);
	}

	/**
	 * @return list<string>
	 */
	private function allTableNames(): array {
		return [
			TableNames::DIARIES,
			TableNames::DIARY_SHARES,
			TableNames::ENTRIES,
			TableNames::QUESTIONS,
			TableNames::ANSWERS,
			TableNames::ALARM_SOUNDS,
		];
	}
}
