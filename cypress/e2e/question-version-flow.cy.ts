describe('Structured diary question versions', () => {
	it('expands question versions and opens an older version in the center view', () => {
		cy.seedStructuredDiary({ questionVersions: 1 }).then(({ diary, question }) => {
			cy.intercept('GET', `**/structureddiary/api/v1/questions/${question.id}/versions`).as('questionVersions')

			cy.loginToNextcloud()
			cy.visitStructuredDiary(`diaries/${diary.id}`)
			cy.contains('Versions').click()
			cy.wait('@questionVersions')
			cy.contains(/^Mood$/).should('be.visible').click()
			cy.contains('strong', 'Type:').parent().should('contain.text', 'text')
			cy.contains('strong', 'Template text:').parent().should('contain.text', 'Write a short note')
		})
	})
})

export {}
