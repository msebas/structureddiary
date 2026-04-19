describe('Structured diary management flow', () => {
	it('creates a diary from the workspace header', () => {
		cy.loginToNextcloud()
		cy.mockStructuredDiaryBootstrap()
		cy.visit('/')

		cy.contains('New diary').first().click()
		cy.contains('Create diary').should('be.visible')
		cy.contains('Title').parent().find('input').type('Copied journal')
		cy.contains('Description').parent().find('textarea').type('Draft description')
		cy.contains('Owner').parent().find('input').clear().type('alice')
		cy.contains('Save diary').click()

		cy.wait('@createDiary').its('request.body').should('deep.include', {
			title: 'Copied journal',
			description: 'Draft description',
			ownerUserId: 'alice',
		})
		cy.contains('Copied journal').should('be.visible')
	})

	it('creates a question from the diary management panel', () => {
		cy.loginToNextcloud()
		cy.mockStructuredDiaryBootstrap()
		cy.visit('/')

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
