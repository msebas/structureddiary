describe('Structured diary management flow', () => {
	it('creates a diary from the workspace header', () => {
		cy.loginToNextcloud()
		cy.mockStructuredDiaryBootstrap()
		cy.visit('/')

		cy.contains('Management').click()
		cy.get('[aria-label="Create new diary"]').first().click()
		cy.contains('Create diary').should('be.visible')
		cy.get('input[aria-label="Title"]').type('Copied journal')
		cy.get('textarea[aria-label="Description"]').type('Draft description')
		cy.contains('Save diary').click()

		cy.wait('@createDiary').its('request.body').should('deep.include', {
			title: 'Copied journal',
			description: 'Draft description',
		})
		cy.contains('Copied journal').should('be.visible')
	})

	it('creates a question from the diary management panel', () => {
		cy.loginToNextcloud()
		cy.mockStructuredDiaryBootstrap()
		cy.visit('/')

		cy.contains('Management').click()
		cy.contains('Health journal').click()
		cy.contains('New question').click()
		cy.contains('Create question').should('be.visible')
		cy.contains('Label').parent().find('input').type('Energy')
		cy.contains('Display text').parent().find('textarea').type('How much energy did you have?')
		cy.contains('Template text').parent().find('textarea').type('0 to 10')
		cy.contains('Save question').click()

		cy.wait('@createQuestion').its('request.body').should('deep.include', {
			label: 'Energy',
			displayText: 'How much energy did you have?',
			templateText: '0 to 10',
		})
	})
})
