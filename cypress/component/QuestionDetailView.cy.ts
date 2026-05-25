import { defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import QuestionDetailView from '@/components/questions/QuestionDetailView.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { Question } from '@/types/types'

function question(patch: Partial<Question> = {}): Question {
	return {
		id: 17,
		chain_id: 17,
		diary_id: 5,
		diary_question_order: 17,
		created_at: 1713500000,
		label: 'Mood',
		display_text: 'Mood',
		type: 'text',
		minimum: 0,
		maximum: 10,
		choices: null,
		active: true,
		template_text: 'First line\n**Second line**',
		previous_version_id: null,
		next_version_id: null,
		...patch,
	}
}

function mountQuestionDetail(selectedQuestion: Question): void {
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions*', [selectedQuestion]).as('questions')
	cy.intercept('GET', '**/structureddiary/api/v1/questions/17/versions', [selectedQuestion]).as('questionVersions')

	const router = createRouter({
		history: createMemoryHistory(),
		routes: [
			{ path: '/questions/:diaryId/:questionId', name: 'question', component: QuestionDetailView },
		],
	})
	void router.push({ name: 'question', params: { diaryId: 5, questionId: 17 } })

	const Wrapper = defineComponent({
		setup() {
			const store = useStructuredDiaryStore()
			void store.loadQuestions(5)
			return () => h(QuestionDetailView)
		},
	})

	cy.mount(Wrapper, {
		global: {
			plugins: [router],
		},
	})
}

describe('QuestionDetailView', () => {
	it('hides redundant display text and renders applicable template markdown last', () => {
		mountQuestionDetail(question())

		cy.wait('@questions')
		cy.contains('Display text:').should('not.exist')
		cy.contains('Minimum:').should('be.visible')
		cy.contains('Maximum:').should('be.visible')
		cy.contains('Template text:').should('be.visible')
		cy.contains('strong', 'Second line').should('be.visible')
	})

	it('hides range, choices, and template rows for boolean questions', () => {
		mountQuestionDetail(question({
			type: 'boolean',
			display_text: 'Enabled?',
			minimum: null,
			maximum: null,
			template_text: 'Should not render',
		}))

		cy.wait('@questions')
		cy.contains('Display text:').should('be.visible')
		cy.contains('Minimum:').should('not.exist')
		cy.contains('Maximum:').should('not.exist')
		cy.contains('Choices:').should('not.exist')
		cy.contains('Should not render').should('not.exist')
	})
})
