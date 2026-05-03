import type { Answer, DiaryStats, Entry, Question, QuestionType } from '@/types/types'

export function formatDateTime(timestamp: number | null | undefined): string {
	if (timestamp === null || timestamp === undefined) {
		return 'n/a'
	}

	return new Intl.DateTimeFormat(undefined, {
		dateStyle: 'medium',
		timeStyle: 'short',
	}).format(new Date(timestamp * 1000))
}

export function formatDate(timestamp: number | null | undefined): string {
	if (timestamp === null || timestamp === undefined) {
		return 'n/a'
	}

	return new Intl.DateTimeFormat(undefined, {
		dateStyle: 'medium',
	}).format(new Date(timestamp * 1000))
}

export function hasExplicitEntryTitle(entry: Pick<Entry, 'title'>): boolean {
	return (entry.title?.trim() ?? '') !== ''
}

export function formatEntryTitle(entry: Pick<Entry, 'title' | 'timestamp'>): string {
	return hasExplicitEntryTitle(entry) ? entry.title!.trim() : formatDateTime(entry.timestamp)
}

export function formatTimeOnly(secondsOrTimestamp: number | null | undefined): string {
	if (secondsOrTimestamp === null || secondsOrTimestamp === undefined) {
		return 'n/a'
	}

	const hours = Math.floor(secondsOrTimestamp / 3600) % 24
	const minutes = Math.floor((secondsOrTimestamp % 3600) / 60)
	return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`
}

export function formatDurationSeconds(seconds: number | null | undefined): string {
	if (seconds === null || seconds === undefined) {
		return 'n/a'
	}
	if (seconds < 60) {
		return `${seconds}s`
	}
	if (seconds < 3600) {
		return `${Math.round(seconds / 60)}m`
	}
	if (seconds < 86400) {
		return `${(seconds / 3600).toFixed(1)}h`
	}
	return `${(seconds / 86400).toFixed(1)}d`
}

export function formatQuestionValue(answer: Answer | undefined, question: Question | undefined): string {
	if (!answer || !question) {
		return ''
	}

	const numeric = answer.numeric_content
	const text = answer.text_content ?? ''

	switch (question.type) {
		case 'boolean':
			return numeric === 1 ? 'Yes' : 'No'
		case 'rating':
			return numeric === null ? '' : `${numeric.toFixed(1)} / 10`
		case 'number':
			return numeric === null ? '' : formatValueWithTemplate(numeric.toFixed(2), question.template_text)
		case 'integer':
			return numeric === null ? '' : formatValueWithTemplate(Math.round(numeric).toString(), question.template_text)
		case 'time':
			return text
		default:
			return text
	}
}

function formatValueWithTemplate(value: string, templateText: string): string {
	return templateText.trim() === '' ? value : `${value} ${templateText}`
}

export function isAnswerEmptyForQuestion(question: Question, answer: Answer | undefined): boolean {
	if (!answer) {
		return true
	}

	switch (question.type) {
		case 'boolean':
		case 'rating':
		case 'number':
		case 'integer':
			return answer.numeric_content === null
		default:
			return !answer.text_content
	}
}

export function entryQuestionProgress(entry: Entry | undefined, questions: Question[], answers: Answer[]): string {
	if (!entry) {
		return '0/0'
	}
	const currentQuestions = questions.filter((question) => question.created_at <= entry.timestamp && question.active)
	const answeredQuestionIds = new Set(answers.map((answer) => answer.question_id))
	const answeredCount = currentQuestions.filter((question) => answeredQuestionIds.has(question.id)).length
	return `${answeredCount}/${currentQuestions.length}`
}

export function frequencyLabel(stats: DiaryStats['entry_frequency']): string {
	if (stats.mean === null || stats.stddev === null) {
		return 'n/a'
	}

	return `${formatDurationSeconds(Math.round(stats.mean))} ± ${formatDurationSeconds(Math.round(stats.stddev))}`
}

export function supportsNumericInput(type: QuestionType): boolean {
	return type === 'number' || type === 'integer' || type === 'rating'
}
