<?php

declare(strict_types=1);

namespace Db;

use OCA\StructuredDiary\Db\QuestionTypes;
use PHPUnit\Framework\TestCase;

final class QuestionTypesTest extends TestCase {
	public function testValuesReturnAllSupportedTypesInStableOrder(): void {
		$this->assertSame([
			QuestionTypes::TEXT,
			QuestionTypes::BOOLEAN,
			QuestionTypes::RATING,
			QuestionTypes::NUMBER,
			QuestionTypes::INTEGER,
			QuestionTypes::TIME,
			QuestionTypes::SELECT,
			QuestionTypes::EDITABLE_SELECT,
		], QuestionTypes::values());
	}

	public function testDefinitionsExposeUppercaseIdsMappedToValues(): void {
		$this->assertSame([
			['id' => 'TEXT', 'value' => QuestionTypes::TEXT],
			['id' => 'BOOLEAN', 'value' => QuestionTypes::BOOLEAN],
			['id' => 'RATING', 'value' => QuestionTypes::RATING],
			['id' => 'NUMBER', 'value' => QuestionTypes::NUMBER],
			['id' => 'INTEGER', 'value' => QuestionTypes::INTEGER],
			['id' => 'TIME', 'value' => QuestionTypes::TIME],
			['id' => 'SELECT', 'value' => QuestionTypes::SELECT],
			['id' => 'EDITABLE_SELECT', 'value' => QuestionTypes::EDITABLE_SELECT],
		], QuestionTypes::definitions());
	}
}
