import EntryDisplayCard from '@/components/entries/EntryDisplayCard.vue'
import type { Answer, Entry, Question } from '@/types/types'

describe('EntryDisplayCard', () => {
	it('shows delete for single-version answers and versions for versioned answers', () => {
		const deleteSpy = cy.spy().as('deleteSpy')
		const historySpy = cy.spy().as('historySpy')
		const entry: Entry = {
			id: 5,
			diary_id: 2,
			timestamp: 1713517200,
			title: 'Daily entry',
		}
		const questions: Question[] = [
			{
				id: 10,
				diary_id: 2,
				created_at: 1713400000,
				label: 'Mood',
				display_text: 'Mood question',
				type: 'text',
				minimum: null,
				maximum: null,
				choices: null,
				active: true,
				template_text: '',
				previous_version_id: null,
				next_version_id: null,
			},
			{
				id: 11,
				diary_id: 2,
				created_at: 1713400000,
				label: 'Sleep',
				display_text: 'Sleep question',
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
		const answers: Answer[] = [
			{
				id: 20,
				diary_id: 2,
				entry_id: 5,
				question_id: 10,
				created_at: 1713517300,
				text_content: 'Fine',
				numeric_content: null,
				previous_version_id: null,
				next_version_id: null,
			},
			{
				id: 21,
				diary_id: 2,
				entry_id: 5,
				question_id: 11,
				created_at: 1713517350,
				text_content: null,
				numeric_content: 7,
				previous_version_id: null,
				next_version_id: null,
			},
		]

		cy.mount(EntryDisplayCard, {
			props: {
				entry,
				questions,
				answers,
				answerHistories: {
					'5:10': [answers[0]],
					'5:11': [answers[1], { ...answers[1], id: 22, created_at: 1713517400, numeric_content: 8, previous_version_id: 21 }],
				},
				onDeleteAnswer: deleteSpy,
				onLoadHistory: historySpy,
			},
		})

		cy.contains('Daily entry').should('be.visible')
		cy.contains('1/2').should('not.exist')
		cy.contains('2/2').should('be.visible')
		cy.contains('Mood question').closest('article').contains('Delete').click()
		cy.contains('Sleep question').closest('article').contains('Versions').click()
		cy.get('@deleteSpy').should('have.been.calledWith', 20)
		cy.get('@historySpy').should('have.been.calledWith', 11)
	})
})
