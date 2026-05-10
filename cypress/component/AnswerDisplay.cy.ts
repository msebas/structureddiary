import { defineComponent, h } from 'vue'
import AnswerDisplay from '@/components/answers/AnswerDisplay.vue'
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

function answer(patch: Partial<Answer>): Answer {
	return {
		id: 20,
		diary_id: 5,
		entry_id: 7,
		question_id: 10,
		created_at: 1713500000,
		text_content: null,
		numeric_content: null,
		previous_version_id: null,
		next_version_id: null,
		...patch,
	}
}

describe('AnswerDisplay', () => {
	it('renders boolean, rating, number, integer, time, select, editable select, and markdown text answers', () => {
		const Wrapper = defineComponent({
			setup() {
				const items = [
					{ question: question('boolean'), answer: answer({ numeric_content: 1 }) },
					{ question: question('rating'), answer: answer({ numeric_content: 7 }) },
					{ question: question('number', { template_text: 'kg' }), answer: answer({ numeric_content: 72.5 }) },
					{ question: question('integer', { template_text: 'steps' }), answer: answer({ numeric_content: 1234 }) },
					{ question: question('time'), answer: answer({ text_content: '08:30' }) },
					{ question: question('select'), answer: answer({ text_content: '**Blue**' }) },
					{ question: question('editable_select'), answer: answer({ text_content: '**Custom** choice' }) },
					{ question: question('text'), answer: answer({ text_content: '**Good** day' }) },
				]
				return () => h('div', items.map((item) => h(AnswerDisplay, item)))
			},
		})
		cy.mount(Wrapper)

		cy.contains('Yes').should('be.visible')
		cy.contains('72.50 kg').should('be.visible')
		cy.contains('1234 steps').should('be.visible')
		cy.contains('08:30').should('be.visible')
		cy.contains('Blue').should('be.visible')
		cy.contains('Custom').should('be.visible')
		cy.contains('Good').should('be.visible')
		cy.get('span').then(($spans) => {
			const stars = Array.from($spans).filter((element) => element.textContent?.trim() === '★')
			expect(stars).to.have.length(10)
		})
	})
})
