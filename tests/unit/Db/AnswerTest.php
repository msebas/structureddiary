<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\Answer;
use PHPUnit\Framework\TestCase;

final class AnswerTest extends TestCase {
	public function testJsonSerializeReturnsExpectedPayload(): void {
		$answer = new Answer();
		$answer->setId(21);
		$answer->setDiaryId(42);
		$answer->setEntryId(5);
		$answer->setQuestionId(11);
		$answer->setCreatedAt(1713254400);
		$answer->setTextContent('Pretty good');
		$answer->setNumericContent(7.5);
		$answer->setPreviousVersionId(20);
		$answer->setNextVersionId(22);

		$this->assertSame([
			'id' => 21,
			'diary_id' => 42,
			'entry_id' => 5,
			'question_id' => 11,
			'created_at' => 1713254400,
			'text_content' => 'Pretty good',
			'numeric_content' => 7.5,
			'previous_version_id' => 20,
			'next_version_id' => 22,
		], $answer->jsonSerialize());
	}

	public function testJsonSerializeKeepsNullVersionedPayloadFields(): void {
		$answer = new Answer();
		$answer->setId(21);
		$answer->setDiaryId(42);
		$answer->setEntryId(5);
		$answer->setQuestionId(11);
		$answer->setCreatedAt(1713254400);
		$answer->setTextContent(null);
		$answer->setNumericContent(null);
		$answer->setPreviousVersionId(null);
		$answer->setNextVersionId(null);

		$this->assertSame([
			'id' => 21,
			'diary_id' => 42,
			'entry_id' => 5,
			'question_id' => 11,
			'created_at' => 1713254400,
			'text_content' => null,
			'numeric_content' => null,
			'previous_version_id' => null,
			'next_version_id' => null,
		], $answer->jsonSerialize());
	}
}
