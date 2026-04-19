<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\Entry;
use PHPUnit\Framework\TestCase;

final class EntryTest extends TestCase {
	public function testJsonSerializeReturnsExpectedPayload(): void {
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);
		$entry->setTimestamp(1713254400);
		$entry->setTitle('Evening Reflection');

		$this->assertSame([
			'id' => 5,
			'diary_id' => 42,
			'timestamp' => 1713254400,
			'title' => 'Evening Reflection',
		], $entry->jsonSerialize());
	}

	public function testJsonSerializeKeepsNullTitle(): void {
		$entry = new Entry();
		$entry->setId(5);
		$entry->setDiaryId(42);
		$entry->setTimestamp(1713254400);
		$entry->setTitle(null);

		$this->assertSame([
			'id' => 5,
			'diary_id' => 42,
			'timestamp' => 1713254400,
			'title' => null,
		], $entry->jsonSerialize());
	}
}
