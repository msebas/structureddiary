describe('Structured diary workspace', () => {
	it('renders the bootstrapped diary overview with mocked API responses', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.loginToNextcloud()
		cy.visitStructuredDiary('entries/5')

		cy.wait('@questionTypes')
		cy.wait('@diaries')
		cy.wait('@entries')
		cy.contains('Health journal').should('exist')
		cy.contains('Morning check-in').should('be.visible')
		cy.contains('Morning check-in').click()
		cy.wait('@entryDetail')
		cy.wait('@answers')
		cy.contains('How do you feel today?').should('be.visible')
		cy.contains('Feeling stable today.').should('be.visible')
	})
})
