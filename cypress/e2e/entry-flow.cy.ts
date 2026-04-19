describe('Structured diary entry flow', () => {
	it('creates an entry and its answer from the workspace editor', () => {
		cy.loginToNextcloud()
		cy.mockStructuredDiaryBootstrap()
		cy.visit('/')

		cy.contains('New entry').click()
		cy.get('input[placeholder="Entry title"]').type('Evening reflection')
		cy.contains('How do you feel today?').parent().find('textarea').clear().type('Productive day')
		cy.contains('Save').first().click()

		cy.wait('@createEntry').its('request.body').should('deep.include', {
			title: 'Evening reflection',
		})
		cy.wait('@createAnswer').its('request.body').should('deep.include', {
			questionId: 17,
			textContent: 'Productive day',
		})
	})

	it('opens the answer history overlay for versioned answers', () => {
		cy.loginToNextcloud()
		cy.mockStructuredDiaryBootstrap()
		cy.visit('/')

		cy.intercept('GET', '**/apps/structureddiary/api/v1/entries/7/answers*', [
			{
				id: 12,
				diary_id: 5,
				entry_id: 7,
				question_id: 17,
				created_at: 1713517900,
				text_content: 'Feeling better now.',
				numeric_content: null,
				previous_version_id: 11,
				next_version_id: null,
			},
		]).as('answersVersioned')

		cy.reload()
		cy.wait('@answersVersioned')
		cy.contains('Versions').click()
		cy.wait('@answerHistory')
		cy.contains('Answer versions').should('be.visible')
		cy.contains('Feeling stable today.').should('be.visible')
		cy.contains('Feeling better now.').should('be.visible')
	})
})
