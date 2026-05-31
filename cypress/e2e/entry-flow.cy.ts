function assertReachable(element: Cypress.Chainable<JQuery<HTMLElement>>, label: string): void {
	element.scrollIntoView({ block: 'center', inline: 'center' }).should('be.visible').then(($element) => {
		const bounds = $element[0].getBoundingClientRect()
		cy.window().then((win) => {
			expect(bounds.left, `${label} left`).to.be.at.least(0)
			expect(bounds.top, `${label} top`).to.be.at.least(0)
			expect(bounds.right, `${label} right`).to.be.at.most(win.innerWidth)
			expect(bounds.bottom, `${label} bottom`).to.be.at.most(win.innerHeight)
		})
	})
}

describe('Structured diary entry flow', () => {
	it('creates an entry and its answer from the workspace editor', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.loginToNextcloud()
		cy.visitStructuredDiary('entries/5')

		cy.get('[aria-label="Create new entry"]').first().click()
		cy.get('input[placeholder="Entry title"]').type('Evening reflection')
		cy.get('.CodeMirror').first().then(($editor) => {
			const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
			editor.setValue('Productive day')
			editor.save()
		})
		assertReachable(cy.contains('#structured-diary-entry-edit-form footer button', 'Cancel'), 'create entry lower cancel')
		assertReachable(cy.contains('#structured-diary-entry-edit-form footer button', 'Save'), 'create entry lower save')
		cy.contains('#structured-diary-entry-edit-form footer button', 'Save').click()

		cy.wait('@createEntry').its('request.body').should('deep.include', {
			title: 'Evening reflection',
		})
		cy.wait('@createAnswer').its('request.body').should('deep.include', {
			questionId: 17,
			textContent: 'Productive day',
		})
	})

	it('opens the answer history overlay for versioned answers', () => {
		cy.mockStructuredDiaryBootstrap()

		cy.intercept('GET', '**/structureddiary/api/v1/entries/7/answers*', [
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
		]).as('answersVersioned')

		cy.loginToNextcloud()
		cy.visitStructuredDiary('entries/5/7')
		cy.wait('@answersVersioned')
		cy.contains('Versions').click()
		cy.wait('@answerHistory')
		cy.contains('Answer versions').should('be.visible')
		cy.contains('Feeling stable today.').should('exist')
		cy.contains('Feeling better now.').should('exist')
	})

	it('renders each answer version with the matching question version', () => {
		cy.mockStructuredDiaryBootstrap()

		const questionText = {
			id: 17,
			chain_id: 17,
			diary_id: 5,
			diary_question_order: 17,
			created_at: 1713510000,
			label: 'Mood note',
			display_text: 'Mood note',
			type: 'text',
			minimum: null,
			maximum: null,
			choices: null,
			active: true,
			template_text: '',
			previous_version_id: null,
			next_version_id: 18,
		}
		const questionRating = {
			...questionText,
			id: 18,
			label: 'Mood score',
			display_text: 'Mood score',
			type: 'rating',
			minimum: 0,
			maximum: 10,
			previous_version_id: 17,
			next_version_id: null,
		}
		const currentAnswer = {
			id: 12,
			diary_id: 5,
			entry_id: 7,
			question_id: 17,
			created_at: 1713517900,
			text_content: 'Written after the question changed back to text.',
			numeric_content: null,
			previous_version_id: 11,
			next_version_id: null,
		}
		const oldAnswer = {
			id: 11,
			diary_id: 5,
			entry_id: 7,
			question_id: 18,
			created_at: 1713517800,
			text_content: null,
			numeric_content: 7,
			previous_version_id: null,
			next_version_id: 12,
		}

		cy.intercept('GET', '**/structureddiary/api/v1/entries/7/answers*', [currentAnswer]).as('answersWithQuestionVersion')
		cy.intercept('GET', '**/structureddiary/api/v1/questions/17/versions', [questionText, questionRating]).as('answerQuestionVersions')
		cy.intercept('GET', '**/structureddiary/api/v1/entries/7/questions/17/answers/history', [oldAnswer, currentAnswer]).as('answerHistoryWithQuestionVersions')

		cy.loginToNextcloud()
		cy.visitStructuredDiary('entries/5/7')
		cy.wait('@answersWithQuestionVersion')
		cy.contains('Versions').click()
		cy.wait('@answerQuestionVersions')
		cy.wait('@answerHistoryWithQuestionVersions')
		cy.contains('Answer versions').should('be.visible')
		cy.contains('Written after the question changed back to text.').should('exist')
		cy.contains('h3', 'Answer versions').parents('div').then(($parents) => {
			const overlay = $parents.toArray().find((element): element is HTMLElement =>
				window.getComputedStyle(element).position === 'fixed',
			)

			expect(overlay, 'answer versions overlay').to.exist
			cy.wrap(overlay!).find('span').then(($spans) => {
				const stars = Array.from($spans).filter((element) => element.textContent?.trim() === '★')
				expect(stars).to.have.length(10)
			})
		})
	})

	it('creates multiple answer versions when an existing answer changes repeatedly', () => {
		cy.mockStructuredDiaryBootstrap()

		const entry = { id: 7, diary_id: 5, timestamp: 1713517200, title: 'Morning check-in' }
		const firstAnswer = {
			id: 11,
			diary_id: 5,
			entry_id: 7,
			question_id: 17,
			created_at: 1713517800,
			text_content: 'Feeling stable today.',
			numeric_content: null,
			previous_version_id: null,
			next_version_id: null,
		}
		let currentAnswer = firstAnswer
		const history = [firstAnswer]

		cy.intercept('PUT', '**/structureddiary/api/v1/entries/7', (request) => {
			request.reply({ ...entry, title: request.body.title, timestamp: request.body.timestamp })
		}).as('updateEntry')
		cy.intercept('GET', '**/structureddiary/api/v1/entries/7/answers*', (request) => {
			request.reply([currentAnswer])
		}).as('currentAnswersForEdit')
		cy.intercept('PUT', '**/structureddiary/api/v1/answers/11', (request) => {
			history[0] = { ...history[0], next_version_id: 12 }
			currentAnswer = {
				...firstAnswer,
				id: 12,
				created_at: 1713517900,
				text_content: request.body.textContent,
				previous_version_id: 11,
				next_version_id: null,
			}
			history.push(currentAnswer)
			request.reply(currentAnswer)
		}).as('updateAnswerFirst')
		cy.intercept('PUT', '**/structureddiary/api/v1/answers/12', (request) => {
			history[1] = { ...history[1], next_version_id: 13 }
			currentAnswer = {
				...firstAnswer,
				id: 13,
				created_at: 1713518000,
				text_content: request.body.textContent,
				previous_version_id: 12,
				next_version_id: null,
			}
			history.push(currentAnswer)
			request.reply(currentAnswer)
		}).as('updateAnswerSecond')
		cy.intercept('GET', '**/structureddiary/api/v1/entries/7/questions/17/answers/history', (request) => {
			request.reply(history)
		}).as('answerHistoryLive')

		cy.loginToNextcloud()
		cy.visitStructuredDiary('entries/5/7/edit')
		cy.wait('@currentAnswersForEdit')
		cy.get('[aria-label="Create new entry"]').should('not.exist')
		cy.get('[aria-label="Save entry"]').should('be.visible')
		cy.get('.CodeMirror').first().then(($editor) => {
			const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
			editor.setValue('Feeling better now.')
			editor.save()
		})
		assertReachable(cy.contains('#structured-diary-entry-edit-form footer button', 'Cancel'), 'edit entry lower cancel')
		assertReachable(cy.contains('#structured-diary-entry-edit-form footer button', 'Save'), 'edit entry lower save')
		cy.contains('#structured-diary-entry-edit-form footer button', 'Save').click()
		cy.wait('@updateAnswerFirst').its('request.body').should('deep.include', {
			questionId: 17,
			textContent: 'Feeling better now.',
		})

		cy.visitStructuredDiary('entries/5/7/edit')
		cy.wait('@currentAnswersForEdit')
		cy.get('.CodeMirror').first().then(($editor) => {
			const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
			editor.setValue('Feeling excellent.')
			editor.save()
		})
		assertReachable(cy.contains('#structured-diary-entry-edit-form footer button', 'Save'), 'second edit entry lower save')
		cy.contains('#structured-diary-entry-edit-form footer button', 'Save').click()
		cy.wait('@updateAnswerSecond').its('request.body').should('deep.include', {
			questionId: 17,
			textContent: 'Feeling excellent.',
		})

		cy.visitStructuredDiary('entries/5/7')
		cy.wait('@currentAnswersForEdit')
		cy.contains('Versions').click()
		cy.wait('@answerHistoryLive')
		cy.contains('Feeling stable today.').should('exist')
		cy.contains('Feeling better now.').should('exist')
		cy.contains('Feeling excellent.').should('exist')
	})

	it('confirms that deleting an entry also deletes its answers', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.loginToNextcloud()
		cy.visitStructuredDiary('entries/5/7')

		cy.contains('button', 'Delete entry').click()
		cy.wait('@answerCount')
		cy.contains('[role="dialog"]', 'Delete this entry?').should('be.visible')
		cy.contains('[role="dialog"]', '1 answer').should('be.visible')
		cy.contains('[role="dialog"] button', 'Delete entry').click()

		cy.wait('@deleteEntry')
		cy.location('pathname').should('include', '/apps/structureddiary/entries/5')
	})

	it('does not create a new answer version when the edited answer is unchanged', () => {
		cy.mockStructuredDiaryBootstrap()

		let entryUpdateCalls = 0
		let answerUpdateCalls = 0
		cy.intercept('PUT', '**/structureddiary/api/v1/entries/7', (request) => {
			entryUpdateCalls += 1
			request.reply({ id: 7, diary_id: 5, timestamp: request.body.timestamp, title: request.body.title })
		})
		cy.intercept('PUT', '**/structureddiary/api/v1/answers/*', (request) => {
			answerUpdateCalls += 1
			request.reply({ statusCode: 500, body: { error: 'No answer update expected' } })
		})

		cy.loginToNextcloud()
		cy.visitStructuredDiary('entries/5/7/edit')
		cy.wait('@answers')
		assertReachable(cy.contains('#structured-diary-entry-edit-form footer button', 'Save'), 'unchanged edit entry lower save')
		cy.contains('#structured-diary-entry-edit-form footer button', 'Save').click()
		cy.wait(100)
		cy.then(() => {
			expect(entryUpdateCalls).to.equal(0)
			expect(answerUpdateCalls).to.equal(0)
		})
	})
})
