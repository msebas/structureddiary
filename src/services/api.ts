import type {
	Answer,
	AnswerCreatePayload,
	AnswerUpdatePayload,
	Diary,
	DiaryCreatePayload,
	DiaryShare,
	DiaryStats,
	DiaryUpdatePayload,
	Entry,
	EntryCreatePayload,
	EntryUpdatePayload,
	Question,
	QuestionCreatePayload,
	QuestionTypeDefinition,
	QuestionUpdatePayload,
	SelectOption,
} from '@/types/types'

declare global {
	interface Window {
		OC?: {
			generateUrl?: (path: string) => string
		}
	}
}

interface ApiErrorPayload {
	error?: string
	message?: string
}

function apiPath(path: string): string {
	if (window.OC?.generateUrl) {
		return window.OC.generateUrl(`/apps/structureddiary${path}`)
	}

	return `/apps/structureddiary${path}`
}

function ocsPath(path: string): string {
	if (window.OC?.generateUrl) {
		return window.OC.generateUrl(path)
	}

	return path
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
	const response = await fetch(apiPath(path), {
		headers: {
			Accept: 'application/json',
			'Content-Type': 'application/json',
			...(init?.headers ?? {}),
		},
		credentials: 'same-origin',
		...init,
	})

	if (!response.ok) {
		let message = `HTTP ${response.status}`
		try {
			const payload = await response.json() as ApiErrorPayload
			message = payload.error ?? payload.message ?? message
		} catch {
			// ignore invalid error payloads
		}
		throw new Error(message)
	}

	const payload = await response.json() as T | { ocs?: { data?: T } }
	if (typeof payload === 'object' && payload !== null && 'ocs' in payload) {
		return payload.ocs?.data as T
	}

	return payload as T
}

async function ocsRequest<T>(path: string, init?: RequestInit): Promise<T> {
	const response = await fetch(ocsPath(path), {
		headers: {
			Accept: 'application/json',
			'Content-Type': 'application/json',
			'OCS-APIRequest': 'true',
			...(init?.headers ?? {}),
		},
		credentials: 'same-origin',
		...init,
	})

	if (!response.ok) {
		throw new Error(`HTTP ${response.status}`)
	}

	const payload = await response.json() as { ocs?: { data?: T } }
	return payload.ocs?.data as T
}

function withQuery(path: string, params: Record<string, string | number | null | undefined>): string {
	const search = new URLSearchParams()
	for (const [key, value] of Object.entries(params)) {
		if (value !== null && value !== undefined && value !== '') {
			search.set(key, String(value))
		}
	}
	const query = search.toString()
	return query === '' ? path : `${path}?${query}`
}

export const diaryService = {
	list(): Promise<Diary[]> {
		return request('/api/v1/diaries')
	},
	get(id: number): Promise<Diary> {
		return request(`/api/v1/diaries/${id}`)
	},
	create(payload: DiaryCreatePayload): Promise<Diary> {
		return request('/api/v1/diaries', {
			method: 'POST',
			body: JSON.stringify(payload),
		})
	},
	update(id: number, payload: DiaryUpdatePayload): Promise<Diary> {
		return request(`/api/v1/diaries/${id}`, {
			method: 'PUT',
			body: JSON.stringify(payload),
		})
	},
	remove(id: number): Promise<Diary> {
		return request(`/api/v1/diaries/${id}`, { method: 'DELETE' })
	},
	shares(id: number): Promise<DiaryShare[]> {
		return request(`/api/v1/diaries/${id}/shares`)
	},
	createShare(id: number, sharedWith: string, permission: number): Promise<DiaryShare> {
		return request(`/api/v1/diaries/${id}/shares`, {
			method: 'POST',
			body: JSON.stringify({ sharedWith, permission }),
		})
	},
	updateShare(id: number, shareId: number, permission: number): Promise<DiaryShare> {
		return request(`/api/v1/diaries/${id}/shares/${shareId}`, {
			method: 'PUT',
			body: JSON.stringify({ permission }),
		})
	},
	deleteShare(id: number, shareId: number): Promise<DiaryShare> {
		return request(`/api/v1/diaries/${id}/shares/${shareId}`, { method: 'DELETE' })
	},
	stats(id: number): Promise<DiaryStats> {
		return request(`/api/v1/diaries/${id}/stats`)
	},
}

interface OcsAutocompleteEntry {
	id?: string
	label?: string
	displayName?: string
	subline?: string
	shareWithDisplayNameUnique?: string
}

export const userService = {
	async search(search: string): Promise<SelectOption<string>[]> {
		const query = search.trim()
		if (query === '') {
			return []
		}

		const params = new URLSearchParams()
		params.set('search', query)
		params.set('itemType', ' ')
		params.set('itemId', ' ')
		params.set('limit', '20')
		params.append('shareTypes[]', '0')

		const raw = await ocsRequest<OcsAutocompleteEntry[]>(`/ocs/v2.php/core/autocomplete/get?${params.toString()}`)
		const seen = new Set<string>()

		return raw
			.map((entry) => {
				const value = entry.id ?? ''
				const label = entry.displayName ?? entry.label ?? value
				const suffix = entry.subline ?? entry.shareWithDisplayNameUnique
				return {
					value,
					label: suffix && suffix !== label ? `${label} (${suffix})` : label,
				}
			})
			.filter((entry) => {
				if (entry.value === '' || seen.has(entry.value)) {
					return false
				}
				seen.add(entry.value)
				return true
			})
	},
}

export const entryService = {
	list(diaryId: number, fromTimestamp?: number | null, untilTimestamp?: number | null): Promise<Entry[]> {
		return request(withQuery(`/api/v1/diaries/${diaryId}/entries`, { fromTimestamp, untilTimestamp }))
	},
	get(id: number): Promise<Entry> {
		return request(`/api/v1/entries/${id}`)
	},
	create(diaryId: number, payload: EntryCreatePayload): Promise<Entry> {
		return request(`/api/v1/diaries/${diaryId}/entries`, {
			method: 'POST',
			body: JSON.stringify(payload),
		})
	},
	update(id: number, payload: EntryUpdatePayload): Promise<Entry> {
		return request(`/api/v1/entries/${id}`, {
			method: 'PUT',
			body: JSON.stringify(payload),
		})
	},
	remove(id: number): Promise<Entry> {
		return request(`/api/v1/entries/${id}`, { method: 'DELETE' })
	},
}

export const questionService = {
	list(diaryId: number): Promise<Question[]> {
		return request(`/api/v1/diaries/${diaryId}/questions`)
	},
	listActive(diaryId: number, timestamp: number): Promise<Question[]> {
		return request(withQuery(`/api/v1/diaries/${diaryId}/questions/active`, { timestamp }))
	},
	get(id: number): Promise<Question> {
		return request(`/api/v1/questions/${id}`)
	},
	versions(id: number): Promise<Question[]> {
		return request(`/api/v1/questions/${id}/versions`)
	},
	create(diaryId: number, payload: QuestionCreatePayload): Promise<Question> {
		return request(`/api/v1/diaries/${diaryId}/questions`, {
			method: 'POST',
			body: JSON.stringify(payload),
		})
	},
	update(id: number, payload: QuestionUpdatePayload): Promise<Question> {
		return request(`/api/v1/questions/${id}`, {
			method: 'PUT',
			body: JSON.stringify(payload),
		})
	},
	remove(id: number): Promise<Question> {
		return request(`/api/v1/questions/${id}`, { method: 'DELETE' })
	},
	types(): Promise<QuestionTypeDefinition[]> {
		return request('/api/v1/question-types')
	},
}

export const answerService = {
	list(entryId: number): Promise<Answer[]> {
		return request(`/api/v1/entries/${entryId}/answers`)
	},
	get(id: number): Promise<Answer> {
		return request(`/api/v1/answers/${id}`)
	},
	history(entryId: number, questionId: number): Promise<Answer[]> {
		return request(`/api/v1/entries/${entryId}/questions/${questionId}/answers/history`)
	},
	create(entryId: number, payload: AnswerCreatePayload): Promise<Answer> {
		return request(`/api/v1/entries/${entryId}/answers`, {
			method: 'POST',
			body: JSON.stringify(payload),
		})
	},
	update(id: number, payload: AnswerUpdatePayload): Promise<Answer> {
		return request(`/api/v1/answers/${id}`, {
			method: 'PUT',
			body: JSON.stringify(payload),
		})
	},
	remove(id: number): Promise<Answer> {
		return request(`/api/v1/answers/${id}`, { method: 'DELETE' })
	},
}
