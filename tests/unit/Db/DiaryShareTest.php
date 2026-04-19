<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\DiaryShare;
use PHPUnit\Framework\TestCase;

final class DiaryShareTest extends TestCase {
	public function testJsonSerializeReturnsExpectedPayload(): void {
		$share = new DiaryShare();
		$share->setId(7);
		$share->setDiaryId(42);
		$share->setSharedWith('bob');
		$share->setPermission(9);

		$this->assertSame([
			'id' => 7,
			'diary_id' => 42,
			'shared_with' => 'bob',
			'permission' => 9,
		], $share->jsonSerialize());
	}
}
