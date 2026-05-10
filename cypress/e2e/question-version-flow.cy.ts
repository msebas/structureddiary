describe('Structured diary question versions', () => {
	it('expands question versions and opens an older version in the center view', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.fixture('question-versions-17.json').then((versions) => {
			cy.intercept('GET', '**/structureddiary/api/v1/questions/17/versions', versions).as('questionVersions')
			cy.intercept('GET', '**/structureddiary/api/v1/questions/18/versions', versions).as('selectedQuestionVersions')
			cy.intercept('GET', '**/structureddiary/api/v1/questions/18', versions[1]).as('selectedQuestionVersion')
		})

		cy.loginToNextcloud()
		cy.visitStructuredDiary('diaries/5')
		cy.contains('Versions').click()
		cy.wait('@questionVersions')
		cy.contains('Mood check-in').should('be.visible').click()
		cy.wait('@selectedQuestionVersion')
		cy.contains('Type: text').should('be.visible')
		cy.contains('Template text: Write a longer note').should('be.visible')
	})
})
