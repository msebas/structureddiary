describe('Structured diary answer deletion', () => {
	it('deletes an older answer version and falls back to a single delete action in the entry card', () => {
		cy.loginToNextcloud()
		cy.mockStructuredDiaryBootstrap()

		cy.fixture('answers-versioned.json').then((versionedAnswers) => {
			cy.intercept('GET', '**/apps/structureddiary/api/v1/entries/7/answers*', versionedAnswers).as('answersVersioned')
		})

		cy.fixture('answer-history-17.json').then((historyFixture) => {
			let history = [...historyFixture]
			let currentAnswers = [history[history.length - 1]]

			cy.intercept('GET', '**/apps/structureddiary/api/v1/entries/7/questions/17/answers/history', () => history).as('answerHistoryLive')
			cy.intercept('DELETE', '**/apps/structureddiary/api/v1/answers/11', () => {
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

				return {
					id: 11,
					diary_id: 5,
					entry_id: 7,
					question_id: 17,
					created_at: 1713517800,
					text_content: 'Feeling stable today.',
					numeric_content: null,
					previous_version_id: null,
					next_version_id: 12,
				}
			}).as('deleteAnswerVersion')
			cy.intercept('GET', '**/apps/structureddiary/api/v1/entries/7/answers*', () => currentAnswers).as('answersAfterDeletion')
		})

		cy.visit('/')
		cy.wait('@answersAfterDeletion')
		cy.contains('Versions').click()
		cy.wait('@answerHistoryLive')
		cy.contains('Answer versions').should('be.visible')
		cy.contains('Feeling stable today.').closest('article').contains('Delete').click()
		cy.wait('@deleteAnswerVersion')
		cy.wait('@answersAfterDeletion')
		cy.contains('Mood question').closest('article').within(() => {
			cy.contains('Delete').should('be.visible')
			cy.contains('Versions').should('not.exist')
		})
	})
})
