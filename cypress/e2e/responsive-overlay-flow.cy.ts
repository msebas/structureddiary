function fixedOverlayByTitle(title: string): Cypress.Chainable<JQuery<HTMLElement>> {
	return cy.contains('h3', title).parents('div').then(($parents) => {
		const overlay = $parents.toArray().find((element): element is HTMLElement =>
			window.getComputedStyle(element).position === 'fixed',
		)

		expect(overlay, `fixed overlay for "${title}"`).to.exist
		return cy.wrap(overlay!)
	})
}

function assertOverlayOwnsViewportEdges(title: string): void {
	fixedOverlayByTitle(title).then(($overlay) => {
		const overlay = $overlay[0]

		cy.window().then((win) => {
			cy.document().then((document) => {
				const y = Math.floor(win.innerHeight / 2)
				const points = [
					{ name: 'left navigation edge', x: 12, y },
					{ name: 'right sidebar edge', x: win.innerWidth - 12, y },
					{ name: 'lower left navigation footer', x: 24, y: win.innerHeight - 24 },
					{ name: 'upper right sidebar controls', x: win.innerWidth - 24, y: 96 },
				]

				for (const point of points) {
					const topElement = document.elementFromPoint(point.x, point.y)
					expect(topElement, point.name).not.to.equal(null)
					expect(
						topElement === overlay || overlay.contains(topElement),
						`${point.name} is covered by the ${title} overlay`,
					).to.equal(true)
				}
			})
		})
	})
}

function assertOverlayCloseButtonIsTopmost(title: string): void {
	fixedOverlayByTitle(title).then(($overlay) => {
		const closeButton = Array.from($overlay[0].querySelectorAll('button'))
			.find((button) => button.textContent?.trim() === 'Close')

		expect(closeButton, `${title} close button`).to.exist

		const bounds = closeButton!.getBoundingClientRect()
		const centerX = bounds.left + bounds.width / 2
		const centerY = bounds.top + bounds.height / 2

		cy.document().then((document) => {
			const topElement = document.elementFromPoint(centerX, centerY)
			expect(topElement, `${title} close button top element`).not.to.equal(null)
			expect(
				topElement === closeButton || closeButton!.contains(topElement),
				`${title} close button is not covered`,
			).to.equal(true)
		})
	})
}

describe('Structured diary responsive overlays', () => {
	it('keeps the compact entry overlay above the left navigation and right entry list', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.loginToNextcloud()
		cy.visitStructuredDiary('entries/5')
		cy.viewport(760, 720)
		cy.window().its('innerWidth').should('equal', 760)

		cy.contains('Morning check-in').click()
		cy.contains('h3', 'Entry').should('be.visible')

		assertOverlayOwnsViewportEdges('Entry')
		assertOverlayCloseButtonIsTopmost('Entry')

		fixedOverlayByTitle('Entry').within(() => {
			cy.contains('button', 'Close').click()
		})
		cy.contains('h3', 'Entry').should('not.exist')
		cy.contains('Morning check-in').click()
		cy.contains('h3', 'Entry').should('be.visible')
	})

	it('keeps the nested answer-history overlay above both navigation sides at phone width', () => {
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
		cy.viewport(390, 740)
		cy.window().its('innerWidth').should('equal', 390)

		cy.contains('Morning check-in').click()
		cy.wait('@answersVersioned')
		cy.contains('h3', 'Entry').should('be.visible')
		cy.contains('Versions').click()
		cy.wait('@answerHistory')
		cy.contains('h3', 'Answer versions').should('be.visible')

		assertOverlayOwnsViewportEdges('Answer versions')
		assertOverlayCloseButtonIsTopmost('Answer versions')

		fixedOverlayByTitle('Answer versions').within(() => {
			cy.contains('button', 'Close').click()
		})
		cy.contains('h3', 'Answer versions').should('not.exist')
		cy.contains('h3', 'Entry').should('be.visible')
	})
})
