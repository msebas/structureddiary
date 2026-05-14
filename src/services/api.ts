import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
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


function apiPath(path: string): string {
	while (path.startsWith('/')) {
		path = path.substring(1)
	}
	return `/ocs/v2.php/apps/structureddiary/api/v1/${path}`
}

export class ApiError extends Error {
	public request: { path: string; init?:RequestInit };
	public http_code: number;
	public result: { ocs: { data: any | {error:string}; meta: { statuscode: number; status: string; message: string } } } | null;
	constructor(message: string, request: {path:string, init?:RequestInit}, http_code:number,
				result: null | {ocs: {data: any | {error:string}, meta: {statuscode: number, status: string, message: string}}}) {
		super(message)
		this.request = request
		this.http_code = http_code
		this.result=result
	}
}

async function handleError(path:string, init?:RequestInit, response?:Response){
	let message = `HTTP ${response?.status}`
	let payload = null
	try {
		payload = await response?.json()
		message = payload.error ?? payload.message ?? message
	} catch {
		// ignore invalid error payloads
	}
	throw new ApiError(message, {path, init: init}, response?.status ?? 500, payload)

}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
	const response = await fetch(apiPath(path), {
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
		await handleError(path, init, response)
	}

	const payload = await response.json() as T | { ocs?: { data?: T } }
	if (typeof payload === 'object' && payload !== null && 'ocs' in payload) {
		return payload.ocs?.data as T
	}

	return payload as T
}

async function ocsRequest<T>(path: string, init?: RequestInit): Promise<T> {
	const response = await fetch(generateOcsUrl(path), {
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
		await handleError(path, init, response)
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
		return request('diaries')
	},
	get(id: number): Promise<Diary> {
		return request(`diaries/${id}`)
	},
	create(payload: DiaryCreatePayload): Promise<Diary> {
		return request('diaries', {
			method: 'POST',
			body: JSON.stringify(payload),
		})
	},
	update(id: number, payload: DiaryUpdatePayload): Promise<Diary> {
		return request(`diaries/${id}`, {
			method: 'PUT',
			body: JSON.stringify(payload),
		})
	},
	remove(id: number): Promise<Diary> {
		return request(`diaries/${id}`, { method: 'DELETE' })
	},
	diary_shares(id: number): Promise<DiaryShare[]> {
		return request(`diaries/${id}/shares`)
	},
	shares(): Promise<DiaryShare[]> {
		return request(`diary-shares`)
	},
	createShare(id: number, sharedWith: string, permission: number): Promise<DiaryShare> {
		return request(`diaries/${id}/shares`, {
			method: 'POST',
			body: JSON.stringify({ sharedWith, permission }),
		})
	},
	updateShare(id: number, shareId: number, permission: number): Promise<DiaryShare> {
		return request(`diaries/${id}/shares/${shareId}`, {
			method: 'PUT',
			body: JSON.stringify({ permission }),
		})
	},
	deleteShare(id: number, shareId: number): Promise<DiaryShare> {
		return request(`diaries/${id}/shares/${shareId}`, { method: 'DELETE' })
	},
	stats(id: number): Promise<DiaryStats> {
		return request(`diaries/${id}/stats`)
	},
}

interface ShareeEntry {
	label?: string
	shareWithDisplayNameUnique?: string
	value?: {
		shareWith?: string
	}
}

interface ShareesResponse {
	exact?: {
		users?: ShareeEntry[]
	}
	users?: ShareeEntry[]
}

export const userService = {
	async search(search: string): Promise<SelectOption<string>[]> {
		const query = search.trim()
		const params = new URLSearchParams()
		params.set('search', query)
		params.set('format', 'json')
		params.set('perPage', '20')
		params.set('itemType', '0,1,4,7')
		params.set('lookup', 'false')

		const raw = await ocsRequest<ShareesResponse>(`/apps/files_sharing/api/v1/sharees?${params.toString()}`)
		const seen = new Set<string>()
		const currentUserId = getCurrentUser()?.uid ?? null
		const normalizedQuery = query.toLocaleLowerCase()
		const currentUserMatches = currentUserId !== null
			&& (normalizedQuery === '' || currentUserId.toLocaleLowerCase().includes(normalizedQuery))
		const entries = [...(raw.exact?.users ?? []), ...(raw.users ?? [])]
		const options: SelectOption<string>[] = []

		if (currentUserMatches) {
			seen.add(currentUserId)
			options.push({
				value: currentUserId,
				label: currentUserId,
			})
		}

		for (const entry of entries) {
			const value = entry.value?.shareWith ?? ''
			if (value === '' || seen.has(value)) {
				continue
			}

			seen.add(value)
			const label = entry.label ?? value
			const suffix = entry.shareWithDisplayNameUnique
			options.push({
				value,
				label: suffix && suffix !== label ? `${label} (${suffix})` : label,
			})
		}

		return options
	},
}

export const entryService = {
	list(diaryId: number, fromTimestamp?: number | null, untilTimestamp?: number | null): Promise<Entry[]> {
		return request(withQuery(`diaries/${diaryId}/entries`, { fromTimestamp, untilTimestamp }))
	},
	get(id: number): Promise<Entry> {
		return request(`entries/${id}`)
	},
	async answerCount(id: number): Promise<number> {
		const response = await request<{ count: number }>(`entries/${id}/answer-count`)
		return response.count
	},
	create(diaryId: number, payload: EntryCreatePayload): Promise<Entry> {
		return request(`diaries/${diaryId}/entries`, {
			method: 'POST',
			body: JSON.stringify(payload),
		})
	},
	update(id: number, payload: EntryUpdatePayload): Promise<Entry> {
		return request(`entries/${id}`, {
			method: 'PUT',
			body: JSON.stringify(payload),
		})
	},
	remove(id: number): Promise<Entry> {
		return request(`entries/${id}`, { method: 'DELETE' })
	},
}

export const questionService = {
	list(diaryId: number): Promise<Question[]> {
		return request(`diaries/${diaryId}/questions`)
	},
	listActive(diaryId: number, timestamp: number): Promise<Question[]> {
		return request(withQuery(`diaries/${diaryId}/questions/active`, { timestamp }))
	},
	get(id: number): Promise<Question> {
		return request(`questions/${id}`)
	},
	versions(id: number): Promise<Question[]> {
		return request(`questions/${id}/versions`)
	},
	create(diaryId: number, payload: QuestionCreatePayload): Promise<Question> {
		return request(`diaries/${diaryId}/questions`, {
			method: 'POST',
			body: JSON.stringify(payload),
		})
	},
	update(id: number, payload: QuestionUpdatePayload): Promise<Question> {
		return request(`questions/${id}`, {
			method: 'PUT',
			body: JSON.stringify(payload),
		})
	},
	reorder(id: number, diaryQuestionOrder: number): Promise<Question> {
		return request(`questions/${id}/order`, {
			method: 'POST',
			body: JSON.stringify({diaryQuestionOrder}),
		})
	},
	remove(id: number): Promise<Question> {
		return request(`questions/${id}`, { method: 'DELETE' })
	},
	types(): Promise<QuestionTypeDefinition[]> {
		return request('question-types')
	},
}

export const answerService = {
	list(entryId: number): Promise<Answer[]> {
		return request(`entries/${entryId}/answers`)
	},
	get(id: number): Promise<Answer> {
		return request(`answers/${id}`)
	},
	history(entryId: number, questionId: number): Promise<Answer[]> {
		return request(`entries/${entryId}/questions/${questionId}/answers/history`)
	},
	create(entryId: number, payload: AnswerCreatePayload): Promise<Answer> {
		return request(`entries/${entryId}/answers`, {
			method: 'POST',
			body: JSON.stringify(payload),
		})
	},
	update(id: number, payload: AnswerUpdatePayload): Promise<Answer> {
		return request(`answers/${id}`, {
			method: 'PUT',
			body: JSON.stringify(payload),
		})
	},
	remove(id: number): Promise<Answer> {
		return request(`answers/${id}`, { method: 'DELETE' })
	},
}
