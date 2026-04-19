import QuestionListPanel from '@/components/layout/QuestionListPanel.vue'
import type { Question } from '@/types/types'

describe('QuestionListPanel', () => {
	it('renders version list and emits create/select/toggle actions', () => {
		const createSpy = cy.spy().as('createSpy')
		const selectSpy = cy.spy().as('selectSpy')
		const toggleSpy = cy.spy().as('toggleSpy')
		const questions: Question[] = [
			{
				id: 3,
				diary_id: 1,
				created_at: 1713400000,
				label: 'Energy',
				display_text: 'Energy level',
				type: 'number',
				minimum: 0,
				maximum: 10,
				choices: null,
				active: true,
				template_text: '',
				previous_version_id: null,
				next_version_id: null,
			},
		]

		cy.mount(QuestionListPanel, {
			props: {
				questions,
				selectedQuestionId: 3,
				versionMap: {
					3: [
						questions[0],
						{ ...questions[0], id: 4, created_at: 1713500000, label: 'Energy v2', previous_version_id: 3 },
					],
				},
				expandedQuestionId: 3,
				search: '',
				onCreate: createSpy,
				onSelect: selectSpy,
				onToggleVersions: toggleSpy,
			},
		})

		cy.contains('New question').click()
		cy.contains('Versions').click()
		cy.contains('Open question').click()
		cy.contains('Energy v2').click()

		cy.get('@createSpy').should('have.been.calledOnce')
		cy.get('@toggleSpy').should('have.been.calledWithMatch', { id: 3 })
		cy.get('@selectSpy').should('have.been.calledTwice')
	})
})
