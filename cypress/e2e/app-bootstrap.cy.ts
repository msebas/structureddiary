describe('Structured diary workspace', () => {
	it('renders the bootstrapped diary overview with mocked API responses', () => {
		cy.loginToNextcloud()
		cy.mockStructuredDiaryBootstrap()
		cy.visit('/')

		cy.wait('@questionTypes')
		cy.wait('@diaries')
		cy.contains('Health journal').should('be.visible')
		cy.contains('Morning check-in').should('be.visible')
		cy.contains('How do you feel today?').should('be.visible')
		cy.contains('Diary').click()
		cy.contains('Diary overview').should('be.visible')
		cy.contains('Owner: alice').should('be.visible')
	})
})
