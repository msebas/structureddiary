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
 * @psalm-type StructuredDiaryAlarmSound = array{
 *   id: int,
 *   path: string|null,
 *   name: string,
 *   last_seen_at: int,
 *   created_at: int,
 *   is_default: bool,
 *   os_affinity: list<string>
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
 *
 * @psalm-type StructuredDiaryAnalysisJob = array{
 *   id: int,
 *   diary_id: int,
 *   created_by: string,
 *   created_at: int,
 *   updated_at: int,
 *   data_from: int,
 *   data_until: int,
 *   started_at: int|null,
 *   finished_at: int|null,
 *   title: string,
 *   language: string,
 *   analysis_type: string,
 *   status: string,
 *   progress: float,
 *   output_types: list<string>,
 *   parameters: array<string, mixed>,
 *   storage_url: string|null,
 *   status_message: string,
 *   error_message: string|null,
 *   cancel_requested_at: int|null
 * }
 *
 * @psalm-type StructuredDiaryAnalysisArtifact = array{
 *   id: int,
 *   parent_id: int|null,
 *   job_id: int,
 *   artifact_type: string,
 *   mime_type: string,
 *   file_name: string,
 *   file_path: string,
 *   file_id: int|null,
 *   size: int,
 *   checksum: string|null,
 *   created_at: int
 * }
 *
 * @psalm-type StructuredDiaryAdminSettings = array{
 *   service_url: string,
 *   service_secret_configured: bool,
 *   output_base_folder: string,
 *   https_warning: bool
 * }
 *
 * @psalm-type StructuredDiaryAnalysisConnectionTest = array{
 *   ok: bool,
 *   health?: array<string, mixed>,
 *   error?: string,
 *   settings: StructuredDiaryAdminSettings
 * }
 *
 * @psalm-type StructuredDiaryHealthcheck = array{
 *   ok: bool
 * }
 *
 * @psalm-type StructuredDiaryAnalysisExportQuestion = array{
 *   id: int,
 *   diaryQuestionOrder: int,
 *   label: string,
 *   displayText: string,
 *   type: string,
 *   minimum: float|null,
 *   maximum: float|null,
 *   jsonChoices: string|null,
 *   templateText: string,
 *   previousVersionId: int|null,
 *   nextVersionId: int|null,
 *   chainId: int
 * }
 *
 * @psalm-type StructuredDiaryAnalysisExportAnswer = array{
 *   id: int,
 *   diaryId: int,
 *   entryId: int,
 *   questionId: int,
 *   createdAt: int,
 *   textContent: string|null,
 *   numericContent: float|null,
 *   previousVersionId: int|null,
 *   nextVersionId: int|null
 * }
 *
 * @psalm-type StructuredDiaryAnalysisExportEntry = array{
 *   id: int,
 *   diaryId: int,
 *   timestamp: int,
 *   title: string|null,
 *   answers: list<StructuredDiaryAnalysisExportAnswer>
 * }
 *
 * @psalm-type StructuredDiaryAnalysisExportDiary = array{
 *   schema_version: int,
 *   diary: array{
 *     id: int,
 *     title: string,
 *     entrySchedule: int
 *   },
 *   questions: list<StructuredDiaryAnalysisExportQuestion>
 * }
 *
 * @psalm-type StructuredDiaryAnalysisExportEntries = array{
 *   schema_version: int,
 *   offset: int,
 *   limit: int,
 *   total_entries: int,
 *   from_entry: int|null,
 *   until_entry: int|null,
 *   has_more: bool,
 *   entries: list<StructuredDiaryAnalysisExportEntry>
 * }
 */
class ResponseDefinitions {
}
