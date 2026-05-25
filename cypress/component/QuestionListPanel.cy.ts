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

	it('only allows saving question reordering in diary mode', () => {
		const sleep = { ...question(5, 'Sleep', 10), chain_id: 5 }
		const energy = question(3, 'Energy', 20)
		let reordered = false

		cy.intercept('POST', '**/structureddiary/api/v1/questions/3/order', (request) => {
			reordered = true
			expect(request.body).to.deep.equal({ diaryQuestionOrder: 1 })
			request.reply({ ...energy, diary_question_order: 1 })
		}).as('reorderEnergy')
		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions*', (request) => {
			request.reply(reordered ? [
				{ ...energy, diary_question_order: 1 },
				{ ...sleep, diary_question_order: 2 },
			] : [sleep, energy])
		}).as('questions')

		const router = createRouter({
			history: createMemoryHistory(),
			routes: [
				{ path: '/diaries/:diaryId', name: 'diary', component: { template: '<div />' } },
				{ path: '/questions/:diaryId', name: 'questions', component: { template: '<div />' } },
			],
		})

		const Wrapper = defineComponent({
			setup() {
				const store = useStructuredDiaryStore()
				const currentRouter = useRouter()
				store.questionSearch = ''
				void store.loadQuestions(5)
				return () => h('div', [
					h(QuestionListPanel),
					h('div', { 'data-cy': 'route-name' }, String(currentRouter.currentRoute.value.name ?? '')),
					h('div', { 'data-cy': 'errors' }, store.errors.map((error) => error.message).join('\n')),
				])
			},
		})

		cy.mount(Wrapper, {
			global: {
				plugins: [router],
			},
		}).then(() => {
			return cy.wrap(router.push({ name: 'diary', params: { diaryId: 5 } }))
		})

		cy.wait('@questions')
		cy.get('[data-cy="route-name"]').should('contain', 'diary')
		cy.contains('Reorder questions').click()
		cy.contains('Question reorder mode is active.').should('be.visible')
		cy.contains('Energy').parents('[class*="questionWrap"]').find('[aria-label="Move question up"]').click()
		cy.contains('Question order changed.').should('be.visible')
		cy.contains('Energy').parents('[class*="questionWrap"]').should('contain.text', 'Moved')
		cy.contains('Save question order').click()
		cy.wait('@reorderEnergy')
		cy.wait('@questions')
		cy.contains('Question reorder mode is active.').should('not.exist')
	})

	it('resets the displayed question IDs when a saved edit creates a new current version', () => {
		const oldQuestion = question(3, 'Mood', 20)
		const newQuestion = {
			...oldQuestion,
			id: 6,
			label: 'Focus',
			previous_version_id: 3,
		}
		oldQuestion.next_version_id = 6
		let reloaded = false

		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions*', (request) => {
			request.reply(reloaded ? [newQuestion] : [oldQuestion])
		}).as('questions')

		const router = createRouter({
			history: createMemoryHistory(),
			routes: [
				{ path: '/questions/:diaryId', name: 'questions', component: { template: '<div />' } },
			],
		})

		const Wrapper = defineComponent({
			setup() {
				const store = useStructuredDiaryStore()
				void store.loadQuestions(5)
				return () => h('div', [
					h(QuestionListPanel),
					h('button', {
						'data-cy': 'reload-current-version',
						onClick: () => {
							reloaded = true
							void store.loadQuestions(5)
						},
					}, 'Reload questions'),
				])
			},
		})

		cy.mount(Wrapper, {
			global: {
				plugins: [router],
			},
		}).then(() => {
			return cy.wrap(router.push({ name: 'questions', params: { diaryId: 5 } }))
		})

		cy.wait('@questions')
		cy.contains('Mood').should('be.visible')
		cy.get('[data-cy="reload-current-version"]').click()
		cy.wait('@questions')
		cy.contains('Focus').should('be.visible')
		cy.contains('Mood').should('not.exist')
	})

	it('warns once when trying to reorder outside diary mode', () => {
		const sleep = { ...question(5, 'Sleep', 10), chain_id: 5 }
		const energy = question(3, 'Energy', 20)

		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions*', [sleep, energy]).as('questions')

		const router = createRouter({
			history: createMemoryHistory(),
			routes: [
				{ path: '/questions/:diaryId', name: 'questions', component: { template: '<div />' } },
			],
		})

		const Wrapper = defineComponent({
			setup() {
				const store = useStructuredDiaryStore()
				const currentRouter = useRouter()
				store.questionSearch = ''
				void store.loadQuestions(5)
				return () => h('div', [
					h(QuestionListPanel),
					h('div', { 'data-cy': 'route-name' }, String(currentRouter.currentRoute.value.name ?? '')),
					h('div', { 'data-cy': 'errors' }, store.errors.map((error) => error.message).join('\n')),
				])
			},
		})

		cy.mount(Wrapper, {
			global: {
				plugins: [router],
			},
		}).then(() => {
			return cy.wrap(router.push({ name: 'questions', params: { diaryId: 5 } }))
		})

		cy.wait('@questions')
		cy.get('[data-cy="route-name"]').should('contain', 'questions')
		cy.contains('Reorder questions').click()
		cy.get('[data-cy="errors"]').should('contain', 'Questions can only be reordered in diary mode.')
		cy.contains('Reorder questions').click()
		cy.get('[data-cy="errors"]').invoke('text').then((text) => {
			expect(text.match(/Questions can only be reordered in diary mode\./g)).to.have.length(1)
		})
	})
})
