import type { Answer, Diary, Entry, Question } from '@/types/types'

type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

export interface StructuredDiarySeed {
	diary: Diary
	question: Question
	entry: Entry
	answer: Answer
	answerVersions: Answer[]
	questionVersions: Question[]
}

export interface SeedOptions {
	answerVersions?: number
	questionVersions?: number
	questionType?: Question['type']
	questionMinimum?: number | null
	questionMaximum?: number | null
	questionChoices?: string[] | null
}

declare global {
	namespace Cypress {
		interface Chainable {
			apiRequest<T>(method: ApiMethod, path: string, body?: unknown): Chainable<T>
			ensureNextcloudUser(userId: string, displayName?: string): Chainable<void>
			loginToNextcloud(): Chainable<void>
			seedStructuredDiary(options?: SeedOptions): Chainable<StructuredDiarySeed>
			visitStructuredDiary(path?: string): Chainable<void>
		}
	}
}

function credentials(): { username: string, password: string } {
	return {
		username: String(Cypress.env('ncUsername') ?? 'admin'),
		password: String(Cypress.env('ncPassword') ?? 'admin'),
	}
}

function nextcloudOrigin(): string {
	return new URL(String(Cypress.config('baseUrl'))).origin
}

function unwrapOcs<T>(body: T | { ocs?: { data?: T } }): T {
	if (typeof body === 'object' && body !== null && 'ocs' in body) {
		return (body as { ocs?: { data?: T } }).ocs?.data as T
	}

	return body as T
}

Cypress.Commands.add('apiRequest', <T>(method: ApiMethod, path: string, body?: unknown) => {
	return cy.request({
		method,
		url: `${nextcloudOrigin()}/ocs/v2.php/apps/structureddiary/api/v1/${path.replace(/^\//, '')}`,
		auth: credentials(),
		headers: {
			Accept: 'application/json',
			'Content-Type': 'application/json',
			'OCS-APIRequest': 'true',
		},
		body: body as Cypress.RequestBody,
	}).then((response) => unwrapOcs<T>(response.body))
})

Cypress.Commands.add('ensureNextcloudUser', (userId: string, displayName = userId) => {
	const { username, password } = credentials()
	cy.request({
		method: 'POST',
		url: `${nextcloudOrigin()}/ocs/v1.php/cloud/users`,
		auth: { username, password },
		headers: {
			Accept: 'application/json',
			'OCS-APIRequest': 'true',
		},
		form: true,
		body: { userid: userId, password, displayName },
		failOnStatusCode: false,
	}).then((response) => {
		const statusCode = response.body?.ocs?.meta?.statuscode
		expect([100, 102], `create Nextcloud user ${userId}`).to.include(statusCode)
	})
})

Cypress.Commands.add('loginToNextcloud', () => {
	const configuredBaseUrl = String(Cypress.config('baseUrl'))
	const url = new URL(configuredBaseUrl)
	const origin = `${url.protocol}//${url.host}`
	const { username, password } = credentials()

	cy.viewport(1440, 1000)
	cy.visit(`${origin}/index.php/login`)
	cy.location('pathname', { timeout: 20000 }).then((pathname) => {
		if (!pathname.includes('/login')) {
			return
		}

		cy.get('input#user, input[name="user"], input[autocomplete="username"]', { timeout: 10000 })
			.first()
			.clear()
			.type(username)
		cy.get('input#password, input[name="password"], input[autocomplete="current-password"]', { timeout: 10000 })
			.first()
			.clear()
			.type(password, { log: false })
		cy.contains('button, input[type="submit"]', 'Log in', { timeout: 10000 }).click()
	})

	cy.location('pathname', { timeout: 20000 }).should('not.include', '/login')
})

Cypress.Commands.add('visitStructuredDiary', (path = '') => {
	const configuredBaseUrl = String(Cypress.config('baseUrl'))
	const url = new URL(configuredBaseUrl)
	const target = `${url.protocol}//${url.host}/index.php/apps/structureddiary/${path.replace(/^\//, '')}`

	cy.viewport(1440, 1000)
	cy.visit(target, {
		onBeforeLoad(win) {
			win.localStorage.removeItem('structureddiary:lastDiaryId')
		},
	})
	cy.location('pathname', { timeout: 20000 }).should('include', '/apps/structureddiary')
})

Cypress.Commands.add('seedStructuredDiary', (options: SeedOptions = {}) => {
	const now = Math.floor(Date.now() / 1000)
	// Questions are selected by their creation time at the entry timestamp.
	// Keep the seeded entry just after the API-created question.
	const entryTimestamp = now + 5
	let diary: Diary
	let entry: Entry
	let question: Question
	let answer: Answer
	const answerVersions: Answer[] = []
	const questionVersions: Question[] = []

	cy.apiRequest<Diary>('POST', 'diaries', {
		title: 'Health journal',
		description: 'Daily notes',
		reminderActive: false,
		reminderTime: 32400,
		reminderCount: 3,
		reminderDelay: 2700,
		reminderSignalFirst: '',
		reminderSignalRepeat: '',
		entrySchedule: 86400,
	}).then((createdDiary) => {
		diary = createdDiary
		return cy.apiRequest<Question>('POST', `diaries/${diary.id}/questions`, {
			label: 'Mood',
			displayText: 'How do you feel today?',
			type: options.questionType ?? 'text',
			minimum: options.questionMinimum ?? null,
			maximum: options.questionMaximum ?? null,
			choices: options.questionChoices ?? null,
			active: true,
			templateText: options.questionType && options.questionType !== 'text' ? '' : 'Write a short note',
		})
	}).then((createdQuestion) => {
		question = createdQuestion
		questionVersions.push(question)
		return cy.apiRequest<Entry>('POST', `diaries/${diary.id}/entries`, {
			timestamp: entryTimestamp,
			title: 'Morning check-in',
		})
	}).then((createdEntry) => {
		entry = createdEntry
		const numeric = ['rating', 'number', 'integer'].includes(options.questionType ?? '')
		const constrainedText = options.questionMaximum !== undefined && options.questionMaximum !== null
			? 'okay'.slice(0, options.questionMaximum)
			: 'Feeling stable today.'
		return cy.apiRequest<Answer>('POST', `entries/${entry.id}/answers`, {
			questionId: question.id,
			textContent: numeric ? null : constrainedText,
			numericContent: numeric ? 3 : null,
		})
	}).then((createdAnswer) => {
		answer = createdAnswer
		answerVersions.push(answer)

		for (let revision = 2; revision <= (options.answerVersions ?? 0) + 1; revision++) {
			cy.then(() => cy.apiRequest<Answer>('PUT', `answers/${answer.id}`, {
				revisionCount: revision,
				questionId: question.id,
				textContent: ['rating', 'number', 'integer'].includes(options.questionType ?? '')
					? null
					: revision === 2 ? 'Feeling better now.' : `Feeling version ${revision}.`,
				numericContent: ['rating', 'number', 'integer'].includes(options.questionType ?? '') ? revision + 2 : null,
			})).then((updated) => {
				answer = updated
				answerVersions.push(answer)
			})
		}

		for (let revision = 2; revision <= (options.questionVersions ?? 0) + 1; revision++) {
			cy.then(() => cy.apiRequest<Question>('PUT', `questions/${question.id}`, {
				questionId: question.id,
				chainId: question.chain_id,
				label: revision === 2 ? 'Mood check-in' : `Mood version ${revision}`,
				displayText: revision === 2 ? 'How strong is your mood?' : `Mood question version ${revision}`,
				type: question.type,
				minimum: question.minimum,
				maximum: question.maximum,
				choices: question.choices,
				active: true,
				templateText: revision === 2 ? 'Write a longer note' : question.template_text,
				revisionCount: revision,
			})).then((updated) => {
				question = updated
				questionVersions.push(question)
			})
		}
	}).then(() => ({ diary, question, entry, answer, answerVersions, questionVersions }))
})

export {}
