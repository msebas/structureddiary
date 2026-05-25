import { defineComponent, h } from 'vue'
import AnswerEditorField from '@/components/answers/AnswerEditorField.vue'
import type { Answer, Question, QuestionType } from '@/types/types'

function question(type: QuestionType, patch: Partial<Question> = {}): Question {
	return {
		id: 10,
		chain_id: 10,
		diary_id: 5,
		diary_question_order: 10,
		created_at: 1713400000,
		label: 'Question',
		display_text: 'Question display',
		type,
		minimum: null,
		maximum: null,
		choices: null,
		active: true,
		template_text: '',
		previous_version_id: null,
		next_version_id: null,
		...patch,
	}
}

function answer(patch: Partial<Answer> = {}): Answer {
	return {
		id: 0,
		diary_id: 5,
		entry_id: 0,
		question_id: 10,
		created_at: 0,
		text_content: null,
		numeric_content: null,
		previous_version_id: null,
		next_version_id: null,
		...patch,
	}
}

describe('AnswerEditorField', () => {
	it('emits typed values for boolean, number, integer, and time questions', () => {
		const updateSpy = cy.spy().as('updateSpy')

		const Wrapper = defineComponent({
			setup() {
				const items = [
					{ question: question('boolean'), value: answer() },
					{ question: question('number', { display_text: 'Weight', template_text: 'kg' }), value: answer() },
					{ question: question('integer', { id: 11, display_text: 'Steps', template_text: 'steps' }), value: answer({ question_id: 11 }) },
					{ question: question('time', { id: 12, display_text: 'Alarm time' }), value: answer({ question_id: 12 }) },
				]
				return () => h('div', items.map((item) => h(AnswerEditorField, {
					question: item.question,
					modelValue: item.value,
					highlightEmpty: true,
					'onUpdate:modelValue': updateSpy,
				})))
			},
		})
		cy.mount(Wrapper)

		cy.contains('Yes / No').click({ force: true })
		cy.contains('Weight').parent().find('input').clear().type('72.5')
		cy.contains('Steps').parent().find('input').clear().type('1234')
		cy.get('.vue-date-time-picker input').first().clear({ force: true }).type('08:30{enter}', { force: true })

		cy.get('@updateSpy').should('have.been.calledWithMatch', { question_id: 10, numeric_content: 1 })
		cy.get('@updateSpy').should('have.been.calledWithMatch', { question_id: 10, numeric_content: 72.5 })
		cy.get('@updateSpy').should('have.been.calledWithMatch', { question_id: 11, numeric_content: 1234 })
		cy.get('@updateSpy').should('have.been.calledWithMatch', { question_id: 12, text_content: '08:30' })
	})

	it('emits typed values for text, rating, select, and editable select questions', () => {
		const updateSpy = cy.spy().as('updateSpy')

		const Wrapper = defineComponent({
			setup() {
				const items = [
					{ question: question('text', { id: 20, display_text: 'Mood note', template_text: 'Write a note' }), value: answer({ question_id: 20 }) },
					{ question: question('rating', { id: 21, display_text: 'Energy rating', template_text: 'Hidden rating template' }), value: answer({ question_id: 21 }) },
					{ question: question('select', { id: 22, display_text: 'Color choice', choices: ['Blue', 'Green'], template_text: 'Hidden select template' }), value: answer({ question_id: 22 }) },
					{ question: question('editable_select', { id: 23, display_text: 'Custom choice', choices: ['Apple', 'Pear'], template_text: 'Kiwi' }), value: answer({ question_id: 23 }) },
				]
				return () => h('div', items.map((item) => h(AnswerEditorField, {
					question: item.question,
					modelValue: item.value,
					highlightEmpty: true,
					'onUpdate:modelValue': updateSpy,
				})))
			},
		})
		cy.mount(Wrapper)

		cy.get('.CodeMirror').first().then(($editor) => {
			const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
			editor.setValue('Markdown **note**')
			editor.save()
		})
		cy.get('.p-rating-option').eq(6).click()
		cy.get('#answer-select-22').parents('.p-select').click()
		cy.get('.p-select-option').contains('Green').click()
		cy.contains('Hidden rating template').should('not.exist')
		cy.contains('Hidden select template').should('not.exist')
		cy.get('#answer-select-23').should('have.value', 'Kiwi')
		cy.get('#answer-select-23').clear().type('Mango{enter}')

		cy.get('@updateSpy').should('have.been.calledWithMatch', { question_id: 20, text_content: 'Markdown **note**' })
		cy.get('@updateSpy').should('have.been.calledWithMatch', { question_id: 21, numeric_content: 7 })
		cy.get('@updateSpy').should('have.been.calledWithMatch', { question_id: 22, text_content: 'Green' })
		cy.get('@updateSpy').should('have.been.calledWithMatch', { question_id: 23, text_content: 'Mango' })
	})
})
