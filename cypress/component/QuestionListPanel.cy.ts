import { computed, defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter, useRouter } from 'vue-router'
import QuestionListPanel from '@/components/layout/QuestionListPanel.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { Question } from '@/types/types'

function question(id: number, label: string, order = id): Question {
	return {
		id,
		chain_id: 3,
		diary_id: 5,
		diary_question_order: order,
		created_at: 1713400000 + id,
		label,
		display_text: `${label} display`,
		type: 'number',
		minimum: 0,
		maximum: 10,
		choices: null,
		active: true,
		template_text: '',
		previous_version_id: null,
		next_version_id: null,
	}
}

describe('QuestionListPanel', () => {
	it('uses store state for search, ordered questions, version expansion, and create navigation', () => {
		const current = question(3, 'Energy', 20)
		const older = { ...current, id: 4, label: 'Energy v1', created_at: 1713300000, next_version_id: 3 }
		const sleep = { ...question(5, 'Sleep', 10), chain_id: 5 }
		current.previous_version_id = 4
		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions*', [current, sleep]).as('questions')
		cy.intercept('GET', '**/structureddiary/api/v1/questions/3/versions', [current, older]).as('questionVersions')
		cy.intercept('GET', '**/structureddiary/api/v1/questions/4', older).as('olderQuestion')

		const router = createRouter({
			history: createMemoryHistory(),
			routes: [
				{ path: '/questions/:diaryId', name: 'questions', component: { template: '<div />' } },
				{ path: '/questions/:diaryId/new', name: 'questionCreate', component: { template: '<div />' } },
				{ path: '/questions/:diaryId/:questionId', name: 'question', component: { template: '<div />' } },
			],
		})
		void router.push({ name: 'questions', params: { diaryId: 5 } })
		const openCenter = cy.stub().as('openCenter')

		const Wrapper = defineComponent({
			setup() {
				const store = useStructuredDiaryStore()
				const currentRouter = useRouter()
				void store.loadQuestions(5)
				const routeName = computed(() => String(currentRouter.currentRoute.value.name ?? ''))
				const questionId = computed(() => String(currentRouter.currentRoute.value.params.questionId ?? ''))
				return () => h('div', [
					h(QuestionListPanel, { onOpenCenter: openCenter }),
					h('div', { 'data-cy': 'route-name' }, routeName.value),
					h('div', { 'data-cy': 'question-id' }, questionId.value),
				])
			},
		})

		cy.mount(Wrapper, {
			global: {
				plugins: [router],
			},
		})

		cy.wait('@questions')
		cy.contains('Sleep').then(($sleep) => {
			cy.contains('Energy').then(($energy) => {
				const sleepElement = $sleep.get(0)
				const energyElement = $energy.get(0)
				expect(sleepElement).to.not.equal(undefined)
				expect(energyElement).to.not.equal(undefined)
				expect(Boolean(sleepElement!.compareDocumentPosition(energyElement!) & Node.DOCUMENT_POSITION_FOLLOWING)).to.equal(true)
			})
		})
		cy.contains('Versions').click()
		cy.wait('@questionVersions')
		cy.contains('Energy v1').should('be.visible')
		cy.contains('Versions').click()
		cy.contains('Energy v1').should('not.exist')
		cy.contains('Versions').click()
		cy.contains('Energy v1').click()
		cy.wait('@olderQuestion')
		cy.get('[data-cy="route-name"]').should('contain', 'question')
		cy.get('[data-cy="question-id"]').should('contain', '4')
		cy.get('@openCenter').should('have.been.calledOnce')

		cy.get('input[placeholder="Search questions"]').clear().type('sleep')
		cy.contains('Sleep').should('be.visible')
		cy.contains('Energy').should('not.exist')

		cy.contains('New question').click()
		cy.get('[data-cy="route-name"]').should('contain', 'questionCreate')
		cy.get('@openCenter').should('have.been.calledTwice')
	})
})
