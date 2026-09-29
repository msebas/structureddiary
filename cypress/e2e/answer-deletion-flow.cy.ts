describe('Structured diary answer deletion', () => {
	it('deletes an older answer version and falls back to a single delete action in the entry card', () => {
		cy.seedStructuredDiary({ answerVersions: 1 }).then(({ diary, entry, question, answerVersions }) => {
			const olderAnswer = answerVersions[0]
			cy.intercept('GET', `**/structureddiary/api/v1/entries/${entry.id}/questions/${question.id}/answers/history`).as('answerHistory')
			cy.intercept('DELETE', `**/structureddiary/api/v1/answers/${olderAnswer.id}`).as('deleteAnswerVersion')

			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}/${entry.id}`)
			cy.contains('Versions').click()
			cy.wait('@answerHistory')
			cy.contains('Answer versions').should('be.visible')
			cy.contains('Feeling stable today.').closest('article').contains('Delete').click()
			cy.wait('@deleteAnswerVersion')
			cy.contains('button', 'Close').click()
			cy.contains('How do you feel today?').closest('article').within(() => {
				cy.contains('Delete').should('be.visible')
				cy.contains('Versions').should('not.exist')
			})
			cy.apiRequest<unknown[]>('GET', `entries/${entry.id}/questions/${question.id}/answers/history`)
				.should('have.length', 1)
		})
	})
})

export {}
