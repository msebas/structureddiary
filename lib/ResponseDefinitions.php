<?php

declare(strict_types=1);

namespace OCA\StructuredDiary;

/**
 * @psalm-type StructuredDiaryDiary = array{
 *   id: int,
 *   user_id: string,
 *   title: string,
 *   description: string,
 *   reminder_active: bool,
 *   reminder_time: int,
 *   reminder_count: int,
 *   reminder_delay: int,
 *   reminder_signal_first: string,
 *   reminder_signal_repeat: string,
 *   entry_schedule: int,
 *   access_level: int,
 *   is_owner: bool
 * }
 *
 * @psalm-type StructuredDiaryDiaryShare = array{
 *   id: int,
 *   diary_id: int,
 *   shared_with: string,
 *   permission: int
 * }
 *
 * @psalm-type StructuredDiaryFrequencyStats = array{
 *   mean: float|null,
 *   stddev: float|null
 * }
 *
 * @psalm-type StructuredDiaryGapInfo = array{
 *   start: int,
 *   end: int,
 *   duration: int
 * }
 *
 * @psalm-type StructuredDiaryDiaryStats = array{
 *   question_count: int,
 *   entry_count: int,
 *   answer_count: int,
 *   average_answer_count: float,
 *   first_entry_at: int|null,
 *   latest_entry_at: int|null,
 *   entry_frequency: StructuredDiaryFrequencyStats,
 *   entry_frequency_last_month: StructuredDiaryFrequencyStats,
 *   gap_count_above_ten_target_intervals: int,
 *   last_large_gap: StructuredDiaryGapInfo|null,
 *   longest_gap: StructuredDiaryGapInfo|null,
 *   average_entry_duration: float|null,
 *   average_entry_duration_last_month: float|null,
 *   latest_answer_at: int|null
 * }
 *
 * @psalm-type StructuredDiaryEntry = array{
 *   id: int,
 *   diary_id: int,
 *   timestamp: int,
 *   title: string|null
 * }
 *
 * @psalm-type StructuredDiaryQuestion = array{
 *   id: int,
 *   chain_id: int,
 *   diary_id: int,
 *   diary_question_order: int,
 *   created_at: int,
 *   label: string,
 *   display_text: string,
 *   type: string,
 *   minimum: float|null,
 *   maximum: float|null,
 *   choices: list<string>|null,
 *   active: bool,
 *   template_text: string,
 *   previous_version_id: int|null,
 *   next_version_id: int|null
 * }
 *
 * @psalm-type StructuredDiaryQuestionTypeDefinition = array{
 *   id: string,
 *   value: string
 * }
 *
 * @psalm-type StructuredDiaryAnswer = array{
 *   id: int,
 *   diary_id: int,
 *   entry_id: int,
 *   question_id: int,
 *   created_at: int,
 *   text_content: string|null,
 *   numeric_content: float|null,
 *   previous_version_id: int|null,
 *   next_version_id: int|null
 * }
 *
 * @psalm-type StructuredDiaryAnswerCount = array{
 *   count: int
 * }
 */
class ResponseDefinitions {
}
