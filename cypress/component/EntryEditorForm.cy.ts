import EntryEditorForm from '@/components/entries/EntryEditorForm.vue'
import type { Answer, Entry, Question } from '@/types/types'

describe('EntryEditorForm', () => {
	it('shows active questions and emits trimmed payload on save', () => {
		const saveSpy = cy.spy().as('saveSpy')
		const entry: Entry = {
			id: 12,
			diary_id: 4,
			timestamp: 1713517200,
			title: 'Existing entry',
		}
		const questions: Question[] = [
			{
				id: 17,
				chain_id: 17,
				diary_id: 4,
				diary_question_order: 17,
				created_at: 1713500000,
				label: 'Mood',
				display_text: 'How do you feel today?',
				type: 'text',
				minimum: null,
				maximum: null,
				choices: null,
				active: true,
				template_text: 'Write a note',
				previous_version_id: null,
				next_version_id: null,
			},
			{
				id: 18,
				chain_id: 18,
				diary_id: 4,
				diary_question_order: 18,
				created_at: 1713600000,
				label: 'Future',
				display_text: 'Should stay hidden',
				type: 'text',
				minimum: null,
				maximum: null,
				choices: null,
				active: true,
				template_text: '',
				previous_version_id: null,
				next_version_id: null,
			},
		]
		const answers: Answer[] = [
			{
				id: 88,
				diary_id: 4,
				entry_id: 12,
				question_id: 17,
				created_at: 1713517300,
				text_content: 'Old value',
				numeric_content: null,
				previous_version_id: null,
				next_version_id: null,
			},
		]

		cy.mount(EntryEditorForm, {
			props: {
				entry,
				questions,
				answers,
				onSave: saveSpy,
			},
		})

		cy.contains('How do you feel today?').should('be.visible')
		cy.contains('Should stay hidden').should('not.exist')
		cy.get('input[placeholder="Entry title"]').clear().type('  Fresh title  ')
		cy.get('.CodeMirror').first().then(($editor) => {
			const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
			editor.setValue('Updated answer')
			editor.save()
		})
		cy.contains('Save').first().click()

		cy.get('@saveSpy').its('firstCall.args.0').should((payload: { title: string | null, answers: Answer[] }) => {
			expect(payload.title).to.equal('Fresh title')
			expect(payload.answers).to.have.length(1)
			expect(payload.answers[0].question_id).to.equal(17)
			expect(payload.answers[0].text_content).to.equal('Updated answer')
		})
	})

	it('does not emit a text answer when the template text stays unchanged', () => {
		const saveSpy = cy.spy().as('saveSpy')
		const questions: Question[] = [
			{
				id: 17,
				chain_id: 17,
				diary_id: 4,
				diary_question_order: 17,
				created_at: 1713500000,
				label: 'Mood',
				display_text: 'How do you feel today?',
				type: 'text',
				minimum: null,
				maximum: null,
				choices: null,
				active: true,
				template_text: 'Write a note',
				previous_version_id: null,
				next_version_id: null,
			},
		]

		cy.mount(EntryEditorForm, {
			props: {
				entry: null,
				questions,
				answers: [],
				onSave: saveSpy,
			},
		})

		cy.contains('Save').first().click()

		cy.get('@saveSpy').its('firstCall.args.0').should((payload: { answers: Answer[] }) => {
			expect(payload.answers).to.have.length(0)
		})
	})
})
