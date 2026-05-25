export type DiaryPermission = 0 | 1 | 3 | 5 | 9 | 7 | 11 | 13 | 15

export const Permissions = {
    READ: 1,
    WRITE: 2,
    ANALYZE: 4,
    MANAGE: 8
}

export type QuestionType =
    | 'text'
    | 'boolean'
    | 'rating'
    | 'number'
    | 'integer'
    | 'time'
    | 'select'
    | 'editable_select'

export interface Diary {
    id: number
    user_id: string
    title: string
    description: string
    reminder_active: boolean
    reminder_time: number
    reminder_count: number
    reminder_delay: number
    reminder_signal_first: string
    reminder_signal_repeat: string
    entry_schedule: number
    access_level: number
    is_owner: boolean
}

export interface DiaryShare {
    id: number
    diary_id: number
    shared_with: string
    permission: number
}

export interface AlarmSound {
    id: number
    path: string | null
    name: string
    last_seen_at: number
    created_at: number
    is_default: boolean
    os_affinity: string[]
}

export interface AlarmSoundCreatePayload {
    name: string
    path?: string | null
    osAffinity?: string[]
    isDefault?: boolean
}

export interface AlarmSoundUpdatePayload {
    name?: string
    path?: string | null
    osAffinity?: string[]
    isDefault?: boolean
}

export interface Entry {
    id: number
    diary_id: number
    timestamp: number
    title: string | null
}

export interface Question {
    id: number
    chain_id: number
    diary_id: number
    diary_question_order: number
    created_at: number
    label: string
    display_text: string
    type: QuestionType
    minimum: number | null
    maximum: number | null
    choices: string[] | null
    active: boolean
    template_text: string
    previous_version_id: number | null
    next_version_id: number | null
}

export interface Answer {
    id: number
    diary_id: number
    entry_id: number
    question_id: number
    created_at: number
    text_content: string | null
    numeric_content: number | null
    previous_version_id: number | null
    next_version_id: number | null
}

export interface QuestionTypeDefinition {
    id: string
    value: QuestionType
}

export interface FrequencyStats {
    mean: number | null
    stddev: number | null
}

export interface GapInfo {
    start: number
    end: number
    duration: number
}

export interface DiaryStats {
    question_count: number
    entry_count: number
    answer_count: number
    average_answer_count: number
    first_entry_at: number | null
    latest_entry_at: number | null
    entry_frequency: FrequencyStats
    entry_frequency_last_month: FrequencyStats
    gap_count_above_ten_target_intervals: number
    last_large_gap: GapInfo | null
    longest_gap: GapInfo | null
    average_entry_duration: number | null
    average_entry_duration_last_month: number | null
    latest_answer_at: number | null
}

export interface DiaryUpdatePayload {
    title?: string
    description?: string
    ownerUserId?: string
    reminderActive?: boolean
    reminderTime?: number
    reminderCount?: number
    reminderDelay?: number
    reminderSignalFirst?: string
    reminderSignalRepeat?: string
    entrySchedule?: number
}

export interface DiaryCreatePayload extends Required<DiaryUpdatePayload> {
    title: string
    description: string
}

export interface EntryCreatePayload {
    timestamp: number
    title?: string | null
}

export interface EntryUpdatePayload {
    timestamp?: number
    title?: string | null
}

export interface QuestionCreatePayload {
    diaryId: number
    label: string | null
    displayText?: string | null
    type: QuestionType
    minimum?: number | null
    maximum?: number | null
    choices?: string[] | null
    active: boolean
    templateText?: string
}

export interface QuestionUpdatePayload {
    questionId: number
    chainId: number
    label?: string | null
    displayText?: string | null
    type?: QuestionType | null
    minimum?: number | null
    maximum?: number | null
    choices?: string[] | null
    active?: boolean | null
    templateText?: string | null
    diaryId?: undefined
}
export interface AnswerCreatePayload {
    questionId: number
    textContent?: string | null
    numericContent?: number | null
}

export interface AnswerUpdatePayload {
    textContent?: string | null
    numericContent?: number | null
}

export interface DiaryGroupSet {
    owned: Diary[]
    managed: Diary[]
    writable: Diary[]
    readable: Diary[]
}

export interface SelectOption<T = string> {
    label: string
    value: T
}
