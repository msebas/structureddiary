import { computed, defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter, useRouter } from 'vue-router'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'

const routes = [
	{ path: '/entries', name: 'entriesAllDiaries', component: { template: '<div />' } },
	{ path: '/entries/:diaryId', name: 'entries', component: { template: '<div />' } },
	{ path: '/entries/:diaryId/:entryId', name: 'entry', component: { template: '<div />' } },
	{ path: '/diaries', name: 'diaries', component: { template: '<div />' } },
	{ path: '/diaries/:diaryId', name: 'diary', component: { template: '<div />' } },
	{ path: '/questions/:diaryId', name: 'questions', component: { template: '<div />' } },
	{ path: '/questions/:diaryId/:questionId', name: 'question', component: { template: '<div />' } },
]

function mountRouteHarness(initialRoute: Record<string, unknown>) {
	const router = createRouter({
		history: createMemoryHistory(),
		routes,
	})
	const routeReady = router.push(initialRoute)

	const Harness = defineComponent({
		setup() {
			const store = useStructuredDiaryStore()
			const currentRouter = useRouter()
			const fullPath = computed(() => currentRouter.currentRoute.value.fullPath)
			const routeName = computed(() => String(currentRouter.currentRoute.value.name ?? ''))
			const selectedDiaryId = computed(() => String(store.selectedDiaryId ?? ''))
			const selectedEntryId = computed(() => String(store.selectedEntryId ?? ''))
			const selectedQuestionId = computed(() => String(store.selectedQuestionId ?? ''))
			const entryFrom = computed(() => String(store.entryFromTimestamp ?? ''))
			const entryUntil = computed(() => String(store.entryUntilTimestamp ?? ''))
			const effectiveEntryFrom = computed(() => String(store.effectiveEntryFromTimestamp))
			const effectiveEntryUntil = computed(() => String(store.effectiveEntryUntilTimestamp))

			return () => h('div', [
				h('div', { 'data-cy': 'full-path' }, fullPath.value),
				h('div', { 'data-cy': 'route-name' }, routeName.value),
				h('div', { 'data-cy': 'selected-diary-id' }, selectedDiaryId.value),
				h('div', { 'data-cy': 'selected-entry-id' }, selectedEntryId.value),
				h('div', { 'data-cy': 'selected-question-id' }, selectedQuestionId.value),
				h('div', { 'data-cy': 'entry-from' }, entryFrom.value),
				h('div', { 'data-cy': 'entry-until' }, entryUntil.value),
				h('div', { 'data-cy': 'effective-entry-from' }, effectiveEntryFrom.value),
				h('div', { 'data-cy': 'effective-entry-until' }, effectiveEntryUntil.value),
				h('button', { 'data-cy': 'select-entry', onClick: () => { store.selectedEntryId = 7 } }, 'select entry'),
				h('button', { 'data-cy': 'clear-entry', onClick: () => { store.selectedEntryId = null } }, 'clear entry'),
				h('button', { 'data-cy': 'select-question', onClick: () => { store.selectedQuestionId = 17 } }, 'select question'),
				h('button', { 'data-cy': 'clear-question', onClick: () => { store.selectedQuestionId = null } }, 'clear question'),
				h('button', { 'data-cy': 'delete-question', onClick: () => void store.deleteQuestion(17) }, 'delete question'),
				h('button', { 'data-cy': 'set-entry-filters', onClick: () => {
					store.entryFromTimestamp = 111
					store.entryUntilTimestamp = 222
				} }, 'set entry filters'),
				h('button', { 'data-cy': 'go-diary', onClick: () => void store.pushWorkspaceRoute({ name: 'diary', params: { diaryId: 5 } }) }, 'go diary'),
				h('button', { 'data-cy': 'go-entries', onClick: () => void store.pushWorkspaceRoute({ name: 'entries', params: { diaryId: 5 } }) }, 'go entries'),
			])
		},
	})

	return cy.wrap(routeReady).then(() => router.isReady()).then(() => {
		cy.mount(Harness, {
			global: {
				plugins: [router],
			},
		})
	})
}

describe('Structured diary store route synchronization', () => {
	beforeEach(() => {
		cy.intercept('GET', '**/structureddiary/api/v1/entries/7', {
			id: 7,
			diary_id: 5,
			timestamp: 1713517200,
			title: 'Morning check-in',
		}).as('entryDetail')
		cy.intercept('GET', '**/structureddiary/api/v1/entries/7/answers*', []).as('entryAnswers')
		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions/active*', []).as('activeQuestions')
		cy.intercept('GET', '**/structureddiary/api/v1/questions/17', {
			id: 17,
			chain_id: 17,
			diary_id: 5,
			diary_question_order: 1,
			created_at: 1713517200,
			label: 'Mood',
			display_text: 'Mood',
			type: 'text',
			minimum: null,
			maximum: null,
			choices: null,
			active: true,
			template_text: '',
			previous_version_id: null,
			next_version_id: null,
		}).as('questionDetail')
	})

	it('keeps selected diary and entry in sync with the entry route and preserves entry query values', () => {
		mountRouteHarness({ name: 'entries', params: { diaryId: 5 }, query: { from: '100', until: '200' } })

		cy.get('[data-cy="selected-diary-id"]').should('contain', '5')
		cy.get('[data-cy="selected-entry-id"]').should('be.empty')
		cy.get('[data-cy="entry-from"]').should('contain', '100')
		cy.get('[data-cy="entry-until"]').should('contain', '200')

		cy.get('[data-cy="select-entry"]').click()
		cy.wait('@entryDetail')
		cy.wait('@entryAnswers')
		cy.wait('@activeQuestions')
		cy.get('[data-cy="route-name"]').should('contain', 'entry')
		cy.get('[data-cy="full-path"]').should('contain', '/entries/5/7?from=100&until=200')
		cy.get('[data-cy="selected-entry-id"]').should('contain', '7')

		cy.get('[data-cy="clear-entry"]').click()
		cy.get('[data-cy="route-name"]').should('contain', 'entries')
		cy.get('[data-cy="full-path"]').should('contain', '/entries/5?from=100&until=200')
		cy.get('[data-cy="selected-entry-id"]').should('be.empty')
	})

	it('keeps selected question in sync with the question route', () => {
		mountRouteHarness({ name: 'questions', params: { diaryId: 5 } })

		cy.get('[data-cy="selected-diary-id"]').should('contain', '5')
		cy.get('[data-cy="selected-question-id"]').should('be.empty')

		cy.get('[data-cy="select-question"]').click()
		cy.wait('@questionDetail')
		cy.get('[data-cy="route-name"]').should('contain', 'question')
		cy.get('[data-cy="full-path"]').should('contain', '/questions/5/17')
		cy.get('[data-cy="selected-question-id"]').should('contain', '17')

		cy.get('[data-cy="clear-question"]').click()
		cy.get('[data-cy="route-name"]').should('contain', 'questions')
		cy.get('[data-cy="full-path"]').should('contain', '/questions/5')
		cy.get('[data-cy="selected-question-id"]').should('be.empty')
	})

	it('does not reload versions for a deleted question', () => {
		let questionDeleted = false
		let versionsRequestedAfterDelete = false

		cy.intercept('DELETE', '**/structureddiary/api/v1/questions/17', (request) => {
			questionDeleted = true
			request.reply({
				id: 17,
				chain_id: 17,
				diary_id: 5,
				diary_question_order: 1,
				created_at: 1713517200,
				label: 'Mood',
				display_text: 'Mood',
				type: 'text',
				minimum: null,
				maximum: null,
				choices: null,
				active: false,
				template_text: '',
				previous_version_id: null,
				next_version_id: null,
			})
		}).as('deleteQuestion')
		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions*', []).as('questions')
		cy.intercept('GET', '**/structureddiary/api/v1/questions/17/versions', (request) => {
			if (questionDeleted) {
				versionsRequestedAfterDelete = true
			}
			request.reply([])
		}).as('questionVersions')

		mountRouteHarness({ name: 'question', params: { diaryId: 5, questionId: 17 } })

		cy.get('[data-cy="delete-question"]').click()
		cy.wait('@deleteQuestion')
		cy.wait('@questions')
		cy.get('[data-cy="route-name"]').should('contain', 'diary')
		cy.then(() => {
			expect(versionsRequestedAfterDelete).to.equal(false)
		})
	})

	it('uses effective entry date defaults without writing them into the URL', () => {
		mountRouteHarness({ name: 'entries', params: { diaryId: 5 } })

		cy.get('[data-cy="entry-from"]').should('be.empty')
		cy.get('[data-cy="entry-until"]').should('be.empty')
		cy.get('[data-cy="effective-entry-from"]').invoke('text').then(Number).should('be.greaterThan', 0)
		cy.get('[data-cy="effective-entry-until"]').invoke('text').then(Number).should('be.greaterThan', 0)
		cy.get('[data-cy="full-path"]').should('have.text', '/entries/5')
	})

	it('caches explicit entry filters while switching away from and back to entry routes', () => {
		mountRouteHarness({ name: 'entries', params: { diaryId: 5 } })

		cy.get('[data-cy="set-entry-filters"]').click()
		cy.get('[data-cy="full-path"]').should('contain', '/entries/5?from=111&until=222')

		cy.get('[data-cy="go-diary"]').click()
		cy.get('[data-cy="route-name"]').should('contain', 'diary')
		cy.get('[data-cy="full-path"]').should('have.text', '/diaries/5')

		cy.get('[data-cy="go-entries"]').click()
		cy.get('[data-cy="route-name"]').should('contain', 'entries')
		cy.get('[data-cy="full-path"]').should('contain', '/entries/5?from=111&until=222')
	})
})
