<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

final class QuestionTypes {
	public const TEXT = 'text';
	public const BOOLEAN = 'boolean';
	public const RATING = 'rating';
	public const NUMBER = 'number';
	public const INTEGER = 'integer';
	public const TIME = 'time';
	public const SELECT = 'select';
	public const EDITABLE_SELECT = 'editable_select';

	/**
	 * @return list<string>
	 */
	public static function values(): array {
		return [
			self::TEXT,
			self::BOOLEAN,
			self::RATING,
			self::NUMBER,
			self::INTEGER,
			self::TIME,
			self::SELECT,
			self::EDITABLE_SELECT,
		];
	}

	/**
	 * @return list<array{id: string, value: string}>
	 */
	public static function definitions(): array {
		return [
			['id' => 'TEXT', 'value' => self::TEXT],
			['id' => 'BOOLEAN', 'value' => self::BOOLEAN],
			['id' => 'RATING', 'value' => self::RATING],
			['id' => 'NUMBER', 'value' => self::NUMBER],
			['id' => 'INTEGER', 'value' => self::INTEGER],
			['id' => 'TIME', 'value' => self::TIME],
			['id' => 'SELECT', 'value' => self::SELECT],
			['id' => 'EDITABLE_SELECT', 'value' => self::EDITABLE_SELECT],
		];
	}

	private function __construct() {
	}
}
