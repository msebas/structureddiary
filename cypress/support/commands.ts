declare global {
	namespace Cypress {
		interface Chainable {
			loginToNextcloud(): Chainable<void>
			mockStructuredDiaryBootstrap(): Chainable<void>
		}
	}
}

Cypress.Commands.add('loginToNextcloud', () => {
	const configuredBaseUrl = String(Cypress.config('baseUrl') ?? 'http://nextcloud.local/index.php/apps/structureddiary/')
	const url = new URL(configuredBaseUrl)
	const origin = `${url.protocol}//${url.host}`
	const username = String(Cypress.env('ncUsername') ?? 'admin')
	const password = String(Cypress.env('ncPassword') ?? 'admin')

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

	cy.visit(configuredBaseUrl)
	cy.location('pathname', { timeout: 20000 }).should('include', '/apps/structureddiary')
	cy.location('pathname').should('not.include', '/login')
})

Cypress.Commands.add('mockStructuredDiaryBootstrap', () => {
	let createdQuestion: Record<string, unknown> | null = null
	let createdEntryAnswers: Record<string, unknown>[] = []

	cy.fixture('question-types.json').then((questionTypes) => {
		cy.fixture('diaries.json').then((diaries) => {
			cy.fixture('diary-detail.json').then((diaryDetail) => {
				cy.fixture('entries.json').then((entries) => {
					cy.fixture('answers-single.json').then((answersSingle) => {
						cy.fixture('questions.json').then((questions) => {
							cy.fixture('diary-stats.json').then((stats) => {
								cy.fixture('answer-history-17.json').then((answerHistory17) => {
									cy.intercept('GET', '**/apps/structureddiary/api/v1/question-types*', questionTypes).as('questionTypes')
									cy.intercept('GET', '**/apps/structureddiary/api/v1/diaries', diaries).as('diaries')
									cy.intercept('GET', '**/apps/structureddiary/api/v1/diaries/5', diaryDetail).as('diaryDetail')
									cy.intercept('GET', '**/apps/structureddiary/api/v1/diaries/5/entries*', entries).as('entries')
									cy.intercept('GET', '**/apps/structureddiary/api/v1/entries/7/answers*', answersSingle).as('answers')
									cy.intercept('GET', '**/apps/structureddiary/api/v1/diaries/5/questions', () =>
										createdQuestion === null ? questions : [...questions, createdQuestion]).as('questions')
									cy.intercept('GET', '**/apps/structureddiary/api/v1/diaries/5/questions/active*', questions).as('activeQuestions')
									cy.intercept('GET', '**/apps/structureddiary/api/v1/diaries/5/shares*', []).as('shares')
									cy.intercept('GET', '**/apps/structureddiary/api/v1/diaries/5/stats*', stats).as('stats')

									cy.intercept('POST', '**/apps/structureddiary/api/v1/diaries', (request) => {
										request.reply({
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
											access_level: 15,
											is_owner: true,
										})
									}).as('createDiary')

									cy.intercept('POST', '**/apps/structureddiary/api/v1/diaries/5/questions', (request) => {
										createdQuestion = {
											id: 33,
											diary_id: 5,
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
										request.reply(createdQuestion)
									}).as('createQuestion')

									cy.intercept('POST', '**/apps/structureddiary/api/v1/diaries/5/entries', (request) => {
										request.reply({
											id: 8,
											diary_id: 5,
											timestamp: request.body.timestamp,
											title: request.body.title,
										})
									}).as('createEntry')

									cy.intercept('POST', '**/apps/structureddiary/api/v1/entries/8/answers', (request) => {
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

									cy.intercept('GET', '**/apps/structureddiary/api/v1/entries/8/answers*', () => createdEntryAnswers).as('newEntryAnswers')
									cy.intercept('GET', '**/apps/structureddiary/api/v1/entries/7/questions/17/answers/history', answerHistory17).as('answerHistory')
								})
							})
						})
					})
				})
			})
		})
	})
})

export {}
