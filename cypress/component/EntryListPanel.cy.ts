import { computed, defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter, useRouter } from 'vue-router'
import EntryListPanel from '@/components/layout/EntryListPanel.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'

function dateInputValue(date: Date): string {
	return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function timestampFromDateInput(value: string, endOfDay: boolean): number {
	return Math.floor(new Date(`${value}T${endOfDay ? '23:59:59' : '00:00:00'}`).getTime() / 1000)
}

describe('EntryListPanel', () => {
	it('defaults date filters, shows time for same-day entries, and applies filters through the store', () => {
		const router = createRouter({
			history: createMemoryHistory(),
			routes: [
				{ path: '/entries/:diaryId', name: 'entries', component: { template: '<div />' } },
				{ path: '/entries/:diaryId/:entryId', name: 'entry', component: { template: '<div />' } },
				{ path: '/entries/:diaryId/new', name: 'entryCreate', component: { template: '<div />' } },
			],
		})
		const routeReady = router.push({ name: 'entries', params: { diaryId: 5 } })
		const loadEntries = cy.stub().as('loadEntries').resolves()
		const openCenter = cy.stub().as('openCenter')
		cy.intercept('GET', '**/structureddiary/api/v1/entries/1', {
			id: 1,
			diary_id: 5,
			timestamp: 1713517200,
			title: 'Morning check-in',
		}).as('entryDetail')
		cy.intercept('GET', '**/structureddiary/api/v1/entries/1/answers*', []).as('entryAnswers')
		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions/active*', []).as('activeQuestions')

		const today = new Date()
		const sevenDaysAgo = new Date()
		sevenDaysAgo.setDate(today.getDate() - 7)

		const Wrapper = defineComponent({
			setup() {
				const store = useStructuredDiaryStore()
				const currentRouter = useRouter()
				store.entriesByDiary = {
					5: {
						1: { id: 1, diary_id: 5, timestamp: 1713517200, title: 'Morning check-in' },
						2: { id: 2, diary_id: 5, timestamp: 1713546000, title: 'Evening check-in' },
					},
				}
				store.loadEntries = loadEntries
				const routeName = computed(() => String(currentRouter.currentRoute.value.name ?? ''))
				const entryId = computed(() => String(currentRouter.currentRoute.value.params.entryId ?? ''))
				return () => h('div', [
					h(EntryListPanel, { onOpenCenter: openCenter }),
					h('div', { 'data-cy': 'route-name' }, routeName.value),
					h('div', { 'data-cy': 'entry-id' }, entryId.value),
				])
			},
		})

		cy.wrap(routeReady).then(() => router.isReady()).then(() => {
			cy.mount(Wrapper, {
				global: {
					plugins: [router],
				},
			})
		})

		cy.get('input[type="date"]').eq(0).should('have.value', dateInputValue(sevenDaysAgo))
		cy.get('input[type="date"]').eq(1).should('have.value', dateInputValue(today))
		cy.contains('Morning check-in').parent().should('contain.text', '2024')
		cy.contains('Evening check-in').parent().should('contain.text', '2024')

		cy.get('input[type="date"]').eq(0).clear().type('2024-04-19')
		cy.get('input[type="date"]').eq(1).clear().type('2024-04-20')
		cy.get('[aria-label="Apply filter"]').click()
		cy.get('@loadEntries').should('have.been.calledWith', 5, timestampFromDateInput('2024-04-19', false), timestampFromDateInput('2024-04-20', true))

		cy.contains('Morning check-in').click()
		cy.wait('@entryDetail')
		cy.wait('@entryAnswers')
		cy.wait('@activeQuestions')
		cy.get('[data-cy="route-name"]').should('contain', 'entry')
		cy.get('[data-cy="entry-id"]').should('contain', '1')
		cy.get('@openCenter').should('have.been.calledOnce')
	})
})
