describe('Structured diary question versions', () => {
	it('expands question versions and opens an older version in the center view', () => {
		cy.loginToNextcloud()
		cy.mockStructuredDiaryBootstrap()
		cy.fixture('question-versions-17.json').then((versions) => {
			cy.intercept('GET', '**/apps/structureddiary/api/v1/questions/17/versions', versions).as('questionVersions')
		})

		cy.visit('/')
		cy.contains('Health journal').click()
		cy.contains('Versions').click()
		cy.wait('@questionVersions')
		cy.contains('Mood check-in').should('be.visible').click()
		cy.contains('Type: text').should('be.visible')
		cy.contains('Template text: Write a longer note').should('be.visible')
	})
})
