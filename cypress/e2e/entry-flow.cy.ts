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
		cy.contains('Save').first().click()

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
		cy.visitStructuredDiary('entries/5')
		cy.contains('Morning check-in').click()
		cy.wait('@answersVersioned')
		cy.contains('Versions').click()
		cy.wait('@answerHistory')
		cy.contains('Answer versions').should('be.visible')
		cy.contains('Feeling stable today.').should('exist')
		cy.contains('Feeling better now.').should('exist')
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
		cy.get('.CodeMirror').first().then(($editor) => {
			const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
			editor.setValue('Feeling better now.')
			editor.save()
		})
		cy.contains('Save').first().click()
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
		cy.contains('Save').first().click()
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

	it('does not create a new answer version when the edited answer is unchanged', () => {
		cy.mockStructuredDiaryBootstrap()

		let answerUpdateCalls = 0
		cy.intercept('PUT', '**/structureddiary/api/v1/entries/7', (request) => {
			request.reply({ id: 7, diary_id: 5, timestamp: request.body.timestamp, title: request.body.title })
		}).as('updateEntryOnly')
		cy.intercept('PUT', '**/structureddiary/api/v1/answers/*', (request) => {
			answerUpdateCalls += 1
			request.reply({ statusCode: 500, body: { error: 'No answer update expected' } })
		})

		cy.loginToNextcloud()
		cy.visitStructuredDiary('entries/5/7/edit')
		cy.wait('@answers')
		cy.contains('Save').first().click()
		cy.wait('@updateEntryOnly')
		cy.then(() => {
			expect(answerUpdateCalls).to.equal(0)
		})
	})
})
