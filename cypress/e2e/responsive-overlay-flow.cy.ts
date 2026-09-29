function fixedOverlayByTitle(title: string): Cypress.Chainable<JQuery<HTMLDivElement>> {
	return cy.contains('h3', title).parents('div').then(($parents) => {
		const overlay = $parents.toArray().find((element) =>
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
				for (const point of [
					{ name: 'left edge', x: 12, y },
					{ name: 'right edge', x: win.innerWidth - 12, y },
					{ name: 'lower left', x: 24, y: win.innerHeight - 24 },
					{ name: 'upper right', x: win.innerWidth - 24, y: 96 },
				]) {
					const topElement = document.elementFromPoint(point.x, point.y)
					expect(topElement, point.name).not.to.equal(null)
					expect(topElement === overlay || overlay.contains(topElement!), point.name).to.equal(true)
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
		cy.document().then((document) => {
			const topElement = document.elementFromPoint(bounds.left + bounds.width / 2, bounds.top + bounds.height / 2)
			expect(topElement === closeButton || closeButton!.contains(topElement!), `${title} close button`).to.equal(true)
		})
	})
}

function setResponsiveViewport(width: number, height: number): void {
	cy.viewport(width, height)
	cy.window().then((win) => win.dispatchEvent(new Event('resize')))
	cy.window().its('innerWidth').should('equal', width)
}

describe('Structured diary responsive overlays', () => {
	it('opens the current diary overview from the compact sidebar', () => {
		cy.seedStructuredDiary().then(({ diary }) => {
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}`)
			setResponsiveViewport(760, 720)
			cy.get('[aria-label="Open diary"]').should('be.visible').click()
			fixedOverlayByTitle('Diary').within(() => {
				cy.contains('Health journal').should('be.visible')
				cy.contains('button', 'Edit diary').should('be.visible')
			})
			assertOverlayOwnsViewportEdges('Diary')
			assertOverlayCloseButtonIsTopmost('Diary')

			fixedOverlayByTitle('Diary').within(() => cy.contains('button', 'Close').click())
			cy.contains('h3', 'Diary').should('not.exist')
			cy.visitStructuredDiary(`entries/${diary.id}`)
			setResponsiveViewport(760, 720)
			cy.contains('button', 'Morning check-in').scrollIntoView().click()
			cy.contains('h3', 'Entry').should('be.visible')
			cy.get('[aria-label="Open diary"]').should('not.exist')
		})
	})

	it('keeps the compact entry overlay above the navigation and entry list', () => {
		cy.seedStructuredDiary().then(({ diary, entry }) => {
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}/${entry.id}`)
			setResponsiveViewport(760, 720)
			cy.contains('h3', 'Entry').should('be.visible')
			assertOverlayOwnsViewportEdges('Entry')
			assertOverlayCloseButtonIsTopmost('Entry')
		})
	})

	it('keeps the nested answer-history overlay above both navigation sides at phone width', () => {
		cy.seedStructuredDiary({ answerVersions: 1 }).then(({ diary, entry }) => {
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`entries/${diary.id}/${entry.id}`)
			setResponsiveViewport(390, 740)
			cy.contains('h3', 'Entry').should('be.visible')
			cy.contains('Versions').click()
			cy.contains('h3', 'Answer versions').should('be.visible')
			assertOverlayOwnsViewportEdges('Answer versions')
			assertOverlayCloseButtonIsTopmost('Answer versions')
			fixedOverlayByTitle('Answer versions').within(() => cy.contains('button', 'Close').click())
			cy.contains('h3', 'Entry').should('be.visible')
		})
	})
})

export {}
