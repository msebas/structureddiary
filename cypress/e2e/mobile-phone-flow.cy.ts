function setPhoneViewport(): void {
	cy.viewport(390, 740)
	cy.window().then((win) => win.dispatchEvent(new Event('resize')))
	cy.window().its('innerWidth').should('equal', 390)
}

function fixedOverlayByTitle(title: string): Cypress.Chainable<JQuery<HTMLDivElement>> {
	return cy.contains('h3', title).parents('div').then(($parents) => {
		const overlay = $parents.toArray().find((element) =>
			window.getComputedStyle(element).position === 'fixed',
		)
		expect(overlay, `fixed overlay for "${title}"`).to.exist
		return cy.wrap(overlay!)
	})
}

function assertReachable<T extends HTMLElement>(element: Cypress.Chainable<JQuery<T>>, label: string): void {
	element.scrollIntoView()
	element.should(($element) => {
		const target = $element[0]
		const bounds = target.getBoundingClientRect()
		expect(Cypress.dom.isVisible(target), `${label} visible`).to.equal(true)
		expect(bounds.left, `${label} left`).to.be.at.least(0)
		expect(bounds.top, `${label} top`).to.be.at.least(0)
		expect(bounds.right, `${label} right`).to.be.at.most(390)
		expect(bounds.bottom, `${label} bottom`).to.be.at.most(740)
	})
}

function setMarkdownEditorValue(value: string): void {
	cy.get('.CodeMirror').first().then(($editor) => {
		const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
		editor.setValue(value)
		editor.save()
	})
}

describe('Structured diary phone workflows', () => {
	it('creates an entry from a phone viewport with reachable form actions', () => {
		cy.seedStructuredDiary().then(({ diary, question }) => {
			cy.intercept('POST', `**/structureddiary/api/v1/diaries/${diary.id}/entries`).as('createEntry')
			cy.intercept('POST', '**/structureddiary/api/v1/entries/*/answers').as('createAnswer')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}`)
			setPhoneViewport()

			assertReachable(cy.get('button[aria-label="Create new entry"]:visible'), 'new entry')
			cy.get('button[aria-label="Create new entry"]:visible').click()
			fixedOverlayByTitle('Create entry').within(() => {
				cy.contains('[data-cy="answer-field"]', 'How do you feel today?').should('be.visible')
				cy.get('input[placeholder="Entry title"]').type('Phone entry')
				setMarkdownEditorValue('Created from the phone layout')
				assertReachable(cy.contains('#structured-diary-entry-edit-form footer button', 'Save'), 'entry save')
				cy.contains('#structured-diary-entry-edit-form footer button', 'Save').click()
			})
			cy.wait('@createEntry').its('request.body').should('deep.include', { title: 'Phone entry' })
			cy.wait('@createAnswer').its('request.body').should('deep.include', {
				questionId: question.id,
				textContent: 'Created from the phone layout',
			})
		})
	})

	it('creates a question from a phone viewport with reachable form actions', () => {
		cy.seedStructuredDiary().then(({ diary }) => {
			cy.intercept('POST', `**/structureddiary/api/v1/diaries/${diary.id}/questions`).as('createQuestion')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`questions/${diary.id}`)
			setPhoneViewport()

			assertReachable(cy.get('button[aria-label="Create new question"]:visible'), 'new question')
			cy.get('button[aria-label="Create new question"]:visible').click()
			fixedOverlayByTitle('Create question').within(() => {
				cy.contains('Label').parent().find('input').first().should('be.enabled').type('Phone question')
				cy.get('[data-cy="synced-display-text"]').should('contain.text', 'Phone question')
				assertReachable(cy.contains('#structured-diary-question-edit-form button', 'Save question'), 'question save')
				cy.contains('#structured-diary-question-edit-form button', 'Save question').click()
			})
			cy.wait('@createQuestion').its('request.body').should('deep.include', {
				label: 'Phone question',
				displayText: 'Phone question',
			})
			cy.apiRequest<Array<{ label: string }>>('GET', `diaries/${diary.id}/questions`)
				.then((questions) => expect(questions.some((question) => question.label === 'Phone question')).to.equal(true))
		})
	})

	it('edits an entry from a phone viewport with reachable lower actions', () => {
		cy.seedStructuredDiary().then(({ diary, entry, question }) => {
			cy.intercept('PUT', `**/structureddiary/api/v1/entries/${entry.id}`).as('updateEntry')
			cy.intercept('PUT', '**/structureddiary/api/v1/answers/*').as('updateAnswer')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}`)
			setPhoneViewport()
			cy.contains('button', 'Morning check-in').scrollIntoView().click()
			fixedOverlayByTitle('Entry').within(() => {
				assertReachable(cy.get('button[aria-label="Edit entry"]'), 'edit entry')
				cy.get('button[aria-label="Edit entry"]').click()
			})
			fixedOverlayByTitle('Edit entry').within(() => {
				cy.get('input[placeholder="Entry title"]').clear().type('Edited on phone')
				setMarkdownEditorValue('Edited from the phone layout')
				assertReachable(cy.contains('#structured-diary-entry-edit-form footer button', 'Save'), 'edit save')
				cy.contains('#structured-diary-entry-edit-form footer button', 'Save').click()
			})
			cy.wait('@updateEntry').its('request.body').should('deep.include', { title: 'Edited on phone', revisionCount: 2 })
			cy.wait('@updateAnswer').its('request.body').should('deep.include', {
				questionId: question.id,
				textContent: 'Edited from the phone layout',
				revisionCount: 2,
			})
		})
	})

	it('opens answer versions from a phone viewport and keeps version actions reachable', () => {
		cy.seedStructuredDiary({ answerVersions: 1 }).then(({ diary, entry }) => {
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}/${entry.id}`)
			setPhoneViewport()
			fixedOverlayByTitle('Entry').within(() => {
				assertReachable(cy.get('button[aria-label="Versions"]'), 'answer versions')
				cy.get('button[aria-label="Versions"]').click()
			})
			fixedOverlayByTitle('Answer versions').within(() => {
				assertReachable(cy.contains('button', 'Close'), 'answer versions close')
				assertReachable(cy.contains('button', 'Delete').first(), 'answer version delete')
			})
		})
	})

	it('deletes an entry from a phone viewport with reachable destructive actions', () => {
		cy.seedStructuredDiary().then(({ diary, entry }) => {
			cy.intercept('DELETE', `**/structureddiary/api/v1/entries/${entry.id}`).as('deleteEntry')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}/${entry.id}`)
			setPhoneViewport()
			fixedOverlayByTitle('Entry').within(() => {
				assertReachable(cy.get('button[aria-label="Delete entry"]'), 'delete entry')
				cy.get('button[aria-label="Delete entry"]').click()
			})
			cy.get('[role="dialog"]:visible').last().within(() => {
				assertReachable(cy.contains('button', 'Delete entry'), 'confirm delete entry')
				cy.contains('button', 'Delete entry').click()
			})
			cy.wait('@deleteEntry')
			cy.location('pathname').should('include', `/apps/structureddiary/entries/${diary.id}`)
		})
	})
})

export {}
