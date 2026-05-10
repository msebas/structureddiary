describe('Structured diary answer deletion', () => {
	it('deletes an older answer version and falls back to a single delete action in the entry card', () => {
		cy.mockStructuredDiaryBootstrap()

		cy.fixture('answers-versioned.json').then((versionedAnswers) => {
			cy.intercept('GET', '**/structureddiary/api/v1/entries/7/answers*', versionedAnswers).as('answersVersioned')
		})

		cy.fixture('answer-history-17.json').then((historyFixture) => {
			let history = [...historyFixture]
			let currentAnswers = [history[history.length - 1]]

			cy.intercept('GET', '**/structureddiary/api/v1/entries/7/questions/17/answers/history', (request) => {
				request.reply(history)
			}).as('answerHistoryLive')
			cy.intercept('DELETE', '**/structureddiary/api/v1/answers/11', (request) => {
				history = [
					{
						...history[1],
						previous_version_id: null,
					},
				]
				currentAnswers = [
					{
						...currentAnswers[0],
						previous_version_id: null,
					},
				]

				request.reply({
					id: 11,
					diary_id: 5,
					entry_id: 7,
					question_id: 17,
					created_at: 1713517800,
					text_content: 'Feeling stable today.',
					numeric_content: null,
					previous_version_id: null,
					next_version_id: 12,
				})
			}).as('deleteAnswerVersion')
			cy.intercept('GET', '**/structureddiary/api/v1/entries/7/answers*', (request) => {
				request.reply(currentAnswers)
			}).as('answersAfterDeletion')
		})

		cy.loginToNextcloud()
		cy.visitStructuredDiary('entries/5')
		cy.contains('Morning check-in').click()
		cy.wait('@answersAfterDeletion')
		cy.contains('Versions').click()
		cy.wait('@answerHistoryLive')
		cy.contains('Answer versions').should('be.visible')
		cy.contains('Feeling stable today.').closest('article').contains('Delete').click()
		cy.wait('@deleteAnswerVersion')
		cy.wait('@answersAfterDeletion')
		cy.contains('button', 'Close').click()
		cy.contains('How do you feel today?').closest('article').within(() => {
			cy.contains('Delete').should('be.visible')
			cy.contains('Versions').should('not.exist')
		})
	})
})
