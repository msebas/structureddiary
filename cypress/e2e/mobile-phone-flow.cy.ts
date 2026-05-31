function setPhoneViewport(): void {
	cy.viewport(390, 740)
	cy.window().then((win) => {
		win.dispatchEvent(new Event('resize'))
	})
	cy.window().its('innerWidth').should('equal', 390)
}

function fixedOverlayByTitle(title: string): Cypress.Chainable<JQuery<HTMLElement>> {
	return cy.contains('h3', title).parents('div').then(($parents) => {
		const overlay = $parents.toArray().find((element): element is HTMLElement =>
			window.getComputedStyle(element).position === 'fixed',
		)

		expect(overlay, `fixed overlay for "${title}"`).to.exist
		return cy.wrap(overlay!)
	})
}

function assertReachable(element: Cypress.Chainable<JQuery<HTMLElement>>, label: string): void {
	element.scrollIntoView({ block: 'center', inline: 'center' }).should('be.visible').then(($element) => {
		const target = $element[0]
		const bounds = target.getBoundingClientRect()
		cy.window().then((win) => {
			expect(bounds.left, `${label} left`).to.be.at.least(0)
			expect(bounds.top, `${label} top`).to.be.at.least(0)
			expect(bounds.right, `${label} right`).to.be.at.most(win.innerWidth)
			expect(bounds.bottom, `${label} bottom`).to.be.at.most(win.innerHeight)

			const centerX = bounds.left + bounds.width / 2
			const centerY = bounds.top + bounds.height / 2
			cy.document().then((document) => {
				const topElement = document.elementFromPoint(centerX, centerY)
				expect(topElement, `${label} top element`).not.to.equal(null)
				expect(
					topElement === target || target.contains(topElement),
					`${label} center is clickable`,
				).to.equal(true)
			})
		})
	})
}

function setMarkdownEditorValue(value: string, index = 0): void {
	cy.get('.CodeMirror').eq(index).then(($editor) => {
		const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
		editor.setValue(value)
		editor.save()
	})
}

describe('Structured diary phone workflows', () => {
	it('creates an entry from a phone viewport with reachable form actions', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.loginToNextcloud()
		cy.visitStructuredDiary('entries/5')
		setPhoneViewport()

		assertReachable(cy.contains('button', 'New entry'), 'mobile create entry button')
		cy.contains('button', 'New entry').click()
		cy.contains('h3', 'Create entry').should('be.visible')
		cy.get('input[placeholder="Entry title"]').type('Phone entry')
		setMarkdownEditorValue('Created from the phone layout')
		assertReachable(cy.contains('#structured-diary-entry-edit-form footer button', 'Cancel'), 'create entry footer cancel')
		assertReachable(cy.contains('#structured-diary-entry-edit-form footer button', 'Save'), 'create entry footer save')
		cy.contains('#structured-diary-entry-edit-form footer button', 'Save').click()

		cy.wait('@createEntry').its('request.body').should('deep.include', {
			title: 'Phone entry',
		})
		cy.wait('@createAnswer').its('request.body').should('deep.include', {
			questionId: 17,
			textContent: 'Created from the phone layout',
		})
	})

	it('creates a question from a phone viewport with reachable form actions', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.loginToNextcloud()
		cy.visitStructuredDiary('questions/5')
		setPhoneViewport()

		assertReachable(cy.contains('button', 'New question'), 'mobile create question button')
		cy.contains('button', 'New question').click()
		cy.contains('h3', 'Create question').should('be.visible')
		cy.contains('Label').parent().find('input').first().type('Phone question')
		assertReachable(cy.contains('#structured-diary-question-edit-form button', 'Cancel'), 'create question cancel')
		assertReachable(cy.contains('#structured-diary-question-edit-form button', 'Save question'), 'create question save')
		cy.contains('#structured-diary-question-edit-form button', 'Save question').click()

		cy.wait('@createQuestion').its('request.body').should('deep.include', {
			label: 'Phone question',
			displayText: 'Phone question',
		})
	})

	it('edits an entry from a phone viewport with reachable lower actions', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.intercept('PUT', '**/structureddiary/api/v1/entries/7', (request) => {
			request.reply({ id: 7, diary_id: 5, timestamp: request.body.timestamp, title: request.body.title })
		}).as('updateEntry')
		cy.intercept('PUT', '**/structureddiary/api/v1/answers/11', (request) => {
			request.reply({
				id: 12,
				diary_id: 5,
				entry_id: 7,
				question_id: request.body.questionId,
				created_at: 1713517900,
				text_content: request.body.textContent,
				numeric_content: request.body.numericContent ?? null,
				previous_version_id: 11,
				next_version_id: null,
			})
		}).as('updateAnswer')

		cy.loginToNextcloud()
		cy.visitStructuredDiary('entries/5')
		setPhoneViewport()
		cy.contains('button', 'Morning check-in').scrollIntoView().click()
		cy.contains('h3', 'Entry').should('be.visible')
		assertReachable(cy.contains('button', 'Edit entry'), 'mobile edit entry button')
		cy.contains('button', 'Edit entry').click()
		cy.wait('@answers')
		cy.contains('h3', 'Edit entry').should('be.visible')
		cy.get('input[placeholder="Entry title"]').clear().type('Edited on phone')
		setMarkdownEditorValue('Edited from the phone layout')
		assertReachable(cy.contains('#structured-diary-entry-edit-form footer button', 'Cancel'), 'edit entry footer cancel')
		assertReachable(cy.contains('#structured-diary-entry-edit-form footer button', 'Save'), 'edit entry footer save')
		cy.contains('#structured-diary-entry-edit-form footer button', 'Save').click()

		cy.wait('@updateEntry').its('request.body').should('deep.include', {
			title: 'Edited on phone',
		})
		cy.wait('@updateAnswer').its('request.body').should('deep.include', {
			questionId: 17,
			textContent: 'Edited from the phone layout',
		})
	})

	it('opens answer versions from a phone viewport and keeps version actions reachable', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.intercept('GET', '**/structureddiary/api/v1/entries/7/answers*', [
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

		cy.loginToNextcloud()
		cy.visitStructuredDiary('entries/5')
		setPhoneViewport()
		cy.contains('button', 'Morning check-in').scrollIntoView().click()
		cy.wait('@answersVersioned')
		assertReachable(cy.contains('button', 'Versions'), 'mobile answer versions button')
		cy.contains('button', 'Versions').click()
		cy.wait('@answerHistory')
		cy.contains('h3', 'Answer versions').should('be.visible')
		fixedOverlayByTitle('Answer versions').within(() => {
			assertReachable(cy.contains('button', 'Close'), 'answer versions close')
			assertReachable(cy.contains('button', 'Delete').first(), 'answer version delete')
		})
	})

	it('deletes an entry from a phone viewport with reachable destructive actions', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.loginToNextcloud()
		cy.visitStructuredDiary('entries/5')
		setPhoneViewport()

		cy.contains('button', 'Morning check-in').scrollIntoView().click()
		cy.contains('h3', 'Entry').should('be.visible')
		assertReachable(cy.contains('button', 'Delete entry'), 'mobile delete entry button')
		cy.contains('button', 'Delete entry').click()
		cy.wait('@answerCount')
		cy.contains('[role="dialog"]', 'Delete this entry?').should('be.visible')
		assertReachable(cy.contains('[role="dialog"] button', 'Cancel'), 'delete entry dialog cancel')
		assertReachable(cy.contains('[role="dialog"] button', 'Delete entry'), 'delete entry dialog confirm')
		cy.contains('[role="dialog"] button', 'Delete entry').click()

		cy.wait('@deleteEntry')
		cy.location('pathname').should('include', '/apps/structureddiary/entries/5')
	})
})
