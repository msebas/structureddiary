<?php

declare(strict_types=1);

namespace OCA\StructuredDiary\Db;

final class TableNames {
	public const DIARIES = 'sd_diaries';
	public const DIARY_SHARES = 'sd_diary_shares';
	public const ENTRIES = 'sd_entries';
	public const QUESTIONS = 'sd_questions';
	public const ANSWERS = 'sd_answers';

	private function __construct() {
	}
}
