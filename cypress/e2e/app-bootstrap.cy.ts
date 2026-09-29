describe('Structured diary workspace', () => {
	it('renders diary, entry, question, and answer data from Nextcloud', () => {
		cy.seedStructuredDiary().then(({ diary }) => {
			cy.intercept('GET', '**/structureddiary/api/v1/question-types*').as('questionTypes')
			cy.intercept('GET', `**/structureddiary/api/v1/diaries/${diary.id}/entries*`).as('entries')

			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}`)

			cy.wait('@questionTypes')
			cy.wait('@entries')
			cy.contains('Health journal').should('exist')
			cy.contains('Morning check-in').should('be.visible').click()
			cy.contains('How do you feel today?').should('be.visible')
			cy.contains('Feeling stable today.').should('be.visible')
		})
	})
})

export {}
