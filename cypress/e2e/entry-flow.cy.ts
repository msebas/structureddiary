const clientMutationIdPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i

function setMarkdownEditorValue(value: string): void {
	cy.get('.CodeMirror').first().then(($editor) => {
		const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
		editor.setValue(value)
		editor.save()
	})
}

function assertReachable<T extends HTMLElement>(element: Cypress.Chainable<JQuery<T>>, label: string): void {
	element.scrollIntoView().should('be.visible').then(($element) => {
		const bounds = $element[0].getBoundingClientRect()
		cy.window().then((win) => {
			expect(bounds.left, `${label} left`).to.be.at.least(0)
			expect(bounds.top, `${label} top`).to.be.at.least(0)
			expect(bounds.right, `${label} right`).to.be.at.most(win.innerWidth)
			expect(bounds.bottom, `${label} bottom`).to.be.at.most(win.innerHeight)
		})
	})
}

describe('Structured diary entry flow', () => {
	it('creates an entry and its answer through the complete UI and API flow', () => {
		cy.seedStructuredDiary().then(({ diary, question }) => {
			cy.intercept('POST', `**/structureddiary/api/v1/diaries/${diary.id}/entries`).as('createEntry')
			cy.intercept('POST', '**/structureddiary/api/v1/entries/*/answers').as('createAnswer')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}`)

			cy.get('[aria-label="Create new entry"]').first().click()
			cy.get('input[placeholder="Entry title"]').type('Evening reflection')
			setMarkdownEditorValue('Productive day')
			assertReachable(cy.contains('#structured-diary-entry-edit-form footer button', 'Cancel'), 'create entry cancel')
			assertReachable(cy.contains('#structured-diary-entry-edit-form footer button', 'Save'), 'create entry save')
			cy.contains('#structured-diary-entry-edit-form footer button', 'Save').click()

			let entryMutationId = ''
			cy.wait('@createEntry').its('request.body').should('deep.include', {
				title: 'Evening reflection',
			}).then((body) => {
				expect(body.clientMutationId).to.match(clientMutationIdPattern)
				entryMutationId = body.clientMutationId
			})
			cy.wait('@createAnswer').its('request.body').should('deep.include', {
				questionId: question.id,
				textContent: 'Productive day',
			}).then((body) => {
				expect(body.clientMutationId).to.match(clientMutationIdPattern)
				expect(body.clientMutationId).not.to.equal(entryMutationId)
			})
			cy.contains('Evening reflection').should('be.visible')
		})
	})

	it('opens the answer history overlay for versioned answers', () => {
		cy.seedStructuredDiary({ answerVersions: 1 }).then(({ diary, entry, question }) => {
			cy.intercept('GET', `**/structureddiary/api/v1/entries/${entry.id}/questions/${question.id}/answers/history`).as('answerHistory')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}/${entry.id}`)
			cy.contains('Versions').click()
			cy.wait('@answerHistory')
			cy.contains('Answer versions').should('be.visible')
			cy.contains('Feeling stable today.').should('exist')
			cy.contains('Feeling better now.').should('exist')
		})
	})

	it('renders numeric answer versions with their real question definition', () => {
		cy.seedStructuredDiary({
			answerVersions: 1,
			questionType: 'rating',
			questionMinimum: 0,
			questionMaximum: 10,
		}).then(({ diary, entry }) => {
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}/${entry.id}`)
			cy.contains('Versions').click()
			cy.contains('h3', 'Answer versions').parents('div').then(($parents) => {
				const overlay = $parents.toArray().find((element) =>
					window.getComputedStyle(element).position === 'fixed',
				)

				expect(overlay, 'answer versions overlay').to.exist
				cy.wrap(overlay!).find('span').then(($spans) => {
					const stars = Array.from($spans).filter((element) => element.textContent?.trim() === '★')
					expect(stars.length).to.be.greaterThan(0)
				})
			})
		})
	})

	it('creates multiple answer versions when an existing answer changes repeatedly', () => {
		cy.seedStructuredDiary().then(({ diary, entry, question }) => {
			cy.intercept('PUT', '**/structureddiary/api/v1/answers/*').as('updateAnswer')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}/${entry.id}/edit`)
			setMarkdownEditorValue('Feeling better now.')
			cy.contains('#structured-diary-entry-edit-form footer button', 'Save').click()
			let firstMutationId = ''
			cy.wait('@updateAnswer').its('request.body').should('deep.include', {
				questionId: question.id,
				textContent: 'Feeling better now.',
				revisionCount: 2,
			}).then((body) => {
				expect(body.clientMutationId).to.match(clientMutationIdPattern)
				firstMutationId = body.clientMutationId
			})

			cy.visitStructuredDiary(`entries/${diary.id}/${entry.id}/edit`)
			setMarkdownEditorValue('Feeling excellent.')
			cy.contains('#structured-diary-entry-edit-form footer button', 'Save').click()
			cy.wait('@updateAnswer').its('request.body').should('deep.include', {
				questionId: question.id,
				textContent: 'Feeling excellent.',
				revisionCount: 3,
			}).then((body) => {
				expect(body.clientMutationId).to.match(clientMutationIdPattern)
				expect(body.clientMutationId).not.to.equal(firstMutationId)
			})

			cy.visitStructuredDiary(`entries/${diary.id}/${entry.id}`)
			cy.contains('Versions').click()
			cy.contains('Feeling stable today.').should('exist')
			cy.contains('Feeling better now.').should('exist')
			cy.contains('Feeling excellent.').should('exist')
		})
	})

	it('confirms that deleting an entry also deletes its answers', () => {
		cy.seedStructuredDiary().then(({ diary, entry }) => {
			cy.intercept('DELETE', `**/structureddiary/api/v1/entries/${entry.id}`).as('deleteEntry')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}/${entry.id}`)
			cy.contains('button', 'Delete entry').click()
			cy.contains('[role="dialog"]', 'Delete this entry?').should('be.visible')
			cy.contains('[role="dialog"]', '1 answer').should('be.visible')
			cy.contains('[role="dialog"] button', 'Delete entry').click()
			cy.wait('@deleteEntry')
			cy.apiRequest<unknown[]>('GET', `diaries/${diary.id}/entries`).should('have.length', 0)
		})
	})

	it('does not create a new answer version when the edited answer is unchanged', () => {
		cy.seedStructuredDiary().then(({ diary, entry, question }) => {
			let entryUpdates = 0
			let answerUpdates = 0
			cy.intercept('PUT', `**/structureddiary/api/v1/entries/${entry.id}`, (request) => {
				entryUpdates += 1
				request.continue()
			})
			cy.intercept('PUT', '**/structureddiary/api/v1/answers/*', (request) => {
				answerUpdates += 1
				request.continue()
			})
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}/${entry.id}/edit`)
			cy.contains('#structured-diary-entry-edit-form footer button', 'Save').click()
			cy.location('pathname').should('include', `/entries/${diary.id}/${entry.id}`)
			cy.then(() => {
				expect(entryUpdates).to.equal(0)
				expect(answerUpdates).to.equal(0)
			})
			cy.apiRequest<unknown[]>('GET', `entries/${entry.id}/questions/${question.id}/answers/history`)
				.should('have.length', 1)
		})
	})
})

export {}
