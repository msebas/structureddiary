declare global {
	namespace Cypress {
		interface Chainable {
			loginToNextcloud(): Chainable<void>
			mockStructuredDiaryBootstrap(): Chainable<void>
			visitStructuredDiary(path?: string): Chainable<void>
		}
	}
}

let structuredDiaryMockState: {
	questions: Record<string, unknown>[]
	createdQuestion: Record<string, unknown> | null
} | null = null

Cypress.Commands.add('loginToNextcloud', () => {
	const configuredBaseUrl = String(Cypress.config('baseUrl') ?? 'http://nextcloud.dev.mcservice.eu/index.php/apps/structureddiary/')
	const url = new URL(configuredBaseUrl)
	const origin = `${url.protocol}//${url.host}`
	const username = String(Cypress.env('ncUsername') ?? 'admin')
	const password = String(Cypress.env('ncPassword') ?? 'admin')

	cy.viewport(1440, 1000)
	cy.visit(`${origin}/index.php/login`)
	cy.location('pathname', { timeout: 10000 }).then((pathname) => {
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

	cy.location('pathname').should('not.include', '/login')
})

Cypress.Commands.add('visitStructuredDiary', (path = '') => {
	const configuredBaseUrl = String(Cypress.config('baseUrl') ?? 'http://nextcloud.dev.mcservice.eu/index.php/apps/structureddiary/')
	const targetUrl = new URL(path.replace(/^\//, ''), configuredBaseUrl).toString()
	cy.viewport(1440, 1000)
	cy.visit(targetUrl, {
		onBeforeLoad(win) {
			if (structuredDiaryMockState === null) {
				return
			}
			const originalFetch = win.fetch.bind(win)
			win.fetch = (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
				const url = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url
				const method = init?.method ?? (typeof input === 'object' && 'method' in input ? input.method : 'GET')
				if (method.toUpperCase() === 'GET' && /\/api\/v1\/diaries\/5\/questions(?:\?.*)?$/.test(url)) {
					const questions = structuredDiaryMockState!.createdQuestion === null
						? structuredDiaryMockState!.questions
						: [...structuredDiaryMockState!.questions, structuredDiaryMockState!.createdQuestion]
					return Promise.resolve(new win.Response(JSON.stringify(questions), {
						status: 200,
						headers: { 'Content-Type': 'application/json' },
					}))
				}
				if (method.toUpperCase() === 'GET' && /\/api\/v1\/diaries\/5\/questions\/active(?:\?.*)?$/.test(url)) {
					return Promise.resolve(new win.Response(JSON.stringify(structuredDiaryMockState!.questions), {
						status: 200,
						headers: { 'Content-Type': 'application/json' },
					}))
				}
				return originalFetch(input, init)
			}
		},
	})
	cy.location('pathname', { timeout: 20000 }).should('include', '/apps/structureddiary')
})

Cypress.Commands.add('mockStructuredDiaryBootstrap', () => {
	let createdQuestion: Record<string, unknown> | null = null
	let createdEntryAnswers: Record<string, unknown>[] = []
	const diary = {
		id: 5,
		user_id: 'alice',
		title: 'Health journal',
		description: 'Daily notes',
		reminder_active: true,
		reminder_time: 32400,
		reminder_count: 3,
		reminder_delay: 2700,
		reminder_signal_first: 'bell',
		reminder_signal_repeat: 'soft-bell',
		entry_schedule: 86400,
		access_level: 15,
		is_owner: true,
	}
	const entries = [{ id: 7, diary_id: 5, timestamp: 1713517200, title: 'Morning check-in' }]
	const questions = [{
		id: 17,
		chain_id: 17,
		diary_id: 5,
		diary_question_order: 17,
		created_at: 1713500000,
		label: 'Mood',
		display_text: 'How do you feel today?',
		type: 'text',
		minimum: null,
		maximum: null,
		choices: null,
		active: true,
		template_text: 'Write a short note',
		previous_version_id: null,
		next_version_id: 18,
	}]
	const answers = [{
		id: 11,
		diary_id: 5,
		entry_id: 7,
		question_id: 17,
		created_at: 1713517800,
		text_content: 'Feeling stable today.',
		numeric_content: null,
		previous_version_id: null,
		next_version_id: null,
	}]
	const answerHistory = [
		{ ...answers[0], next_version_id: 12 },
		{
			id: 12,
			diary_id: 5,
			entry_id: 7,
			question_id: 17,
			created_at: 1713517900,
			text_content: 'Feeling better now.',
			numeric_content: null,
			previous_version_id: 11,
			next_version_id: null,
		},
	]
	const stats = {
		question_count: 1,
		entry_count: 1,
		answer_count: 1,
		average_answer_count: 1,
		first_entry_at: 1713517200,
		latest_entry_at: 1713517200,
		entry_frequency: { mean: 86400, stddev: 0 },
		entry_frequency_last_month: { mean: 86400, stddev: 0 },
		gap_count_above_ten_target_intervals: 0,
		last_large_gap: null,
		longest_gap: null,
		average_entry_duration: 600,
		average_entry_duration_last_month: 600,
		latest_answer_at: 1713517800,
	}
	structuredDiaryMockState = {
		questions,
		createdQuestion: null,
	}

	cy.intercept('GET', '**/structureddiary/api/v1/question-types*', [
		{ id: 'TEXT', value: 'text' },
		{ id: 'BOOLEAN', value: 'boolean' },
		{ id: 'RATING', value: 'rating' },
		{ id: 'NUMBER', value: 'number' },
		{ id: 'INTEGER', value: 'integer' },
		{ id: 'TIME', value: 'time' },
		{ id: 'SELECT', value: 'select' },
		{ id: 'EDITABLE_SELECT', value: 'editable_select' },
	]).as('questionTypes')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries', [diary]).as('diaries')
	cy.intercept('GET', '**/structureddiary/api/v1/diary-shares*', []).as('allShares')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5', diary).as('diaryDetail')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/entries*', entries).as('entries')
	cy.intercept('GET', '**/structureddiary/api/v1/entries/7', entries[0]).as('entryDetail')
	cy.intercept('GET', '**/structureddiary/api/v1/entries/7/answers*', answers).as('answers')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions/active*', questions).as('activeQuestions')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions*', (request) => {
		request.reply(createdQuestion === null ? questions : [...questions, createdQuestion])
	}).as('questions')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/shares*', []).as('shares')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/stats*', stats).as('stats')

	cy.intercept('POST', '**/structureddiary/api/v1/diaries', (request) => {
		request.reply({
			...diary,
			id: 6,
			user_id: request.body.ownerUserId ?? 'alice',
			title: request.body.title,
			description: request.body.description,
			reminder_active: request.body.reminderActive,
			reminder_time: request.body.reminderTime,
			reminder_count: request.body.reminderCount,
			reminder_delay: request.body.reminderDelay,
			reminder_signal_first: request.body.reminderSignalFirst,
			reminder_signal_repeat: request.body.reminderSignalRepeat,
			entry_schedule: request.body.entrySchedule,
		})
	}).as('createDiary')

	cy.intercept('POST', '**/structureddiary/api/v1/diaries/5/questions', (request) => {
		createdQuestion = {
			id: 33,
			chain_id: 33,
			diary_id: 5,
			diary_question_order: 33,
			created_at: 1713520000,
			label: request.body.label,
			display_text: request.body.displayText,
			type: request.body.type,
			minimum: request.body.minimum,
			maximum: request.body.maximum,
			choices: request.body.choices,
			active: request.body.active,
			template_text: request.body.templateText,
			previous_version_id: null,
			next_version_id: null,
		}
		structuredDiaryMockState!.createdQuestion = createdQuestion
		request.reply(createdQuestion)
	}).as('createQuestion')

	cy.intercept('POST', '**/structureddiary/api/v1/diaries/5/entries', (request) => {
		request.reply({ id: 8, diary_id: 5, timestamp: request.body.timestamp, title: request.body.title })
	}).as('createEntry')

	cy.intercept('POST', '**/structureddiary/api/v1/entries/8/answers', (request) => {
		const answer = {
			id: 40,
			diary_id: 5,
			entry_id: 8,
			question_id: request.body.questionId,
			created_at: 1713520200,
			text_content: request.body.textContent ?? null,
			numeric_content: request.body.numericContent ?? null,
			previous_version_id: null,
			next_version_id: null,
		}
		createdEntryAnswers = [...createdEntryAnswers, answer]
		request.reply(answer)
	}).as('createAnswer')

	cy.intercept('GET', '**/structureddiary/api/v1/entries/8/answers*', (request) => {
		request.reply(createdEntryAnswers)
	}).as('newEntryAnswers')
	return cy.intercept('GET', '**/structureddiary/api/v1/entries/7/questions/17/answers/history', answerHistory).as('answerHistory')
})

export {}
