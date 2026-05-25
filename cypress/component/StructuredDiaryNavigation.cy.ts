import { computed, defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter, useRouter } from 'vue-router'
import StructuredDiaryNavigation from '@/components/layout/StructuredDiaryNavigation.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'

describe('StructuredDiaryNavigation', () => {
	it('returns from a selected question to the diary and emits the mobile-close selection event', () => {
		const router = createRouter({
			history: createMemoryHistory(),
			routes: [
				{ path: '/diaries/:diaryId', name: 'diary', component: { template: '<div />' } },
				{ path: '/questions/:diaryId/:questionId', name: 'question', component: { template: '<div />' } },
			],
		})
		const routeReady = router.push({ name: 'question', params: { diaryId: 5, questionId: 17 } })
		const diarySelected = cy.stub().as('diarySelected')

		const Wrapper = defineComponent({
			setup() {
				const store = useStructuredDiaryStore()
				const currentRouter = useRouter()
				store.diaries = {
					5: {
						id: 5,
						user_id: 'alice',
						title: 'Health journal',
						description: '',
						reminder_active: false,
						reminder_time: 0,
						reminder_count: 3,
						reminder_delay: 2700,
						reminder_signal_first: '',
						reminder_signal_repeat: '',
						entry_schedule: 86400,
						access_level: 15,
						is_owner: true,
					},
				}
				const fullPath = computed(() => currentRouter.currentRoute.value.fullPath)
				const routeName = computed(() => String(currentRouter.currentRoute.value.name ?? ''))

				return () => h('div', [
					h(StructuredDiaryNavigation, { onDiarySelected: diarySelected }),
					h('div', { 'data-cy': 'route-name' }, routeName.value),
					h('div', { 'data-cy': 'full-path' }, fullPath.value),
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

		cy.get('[data-cy="route-name"]').should('contain', 'question')
		cy.contains('Health journal').click({ force: true })
		cy.get('[data-cy="route-name"]').should('contain', 'diary')
		cy.get('[data-cy="full-path"]').should('contain', '/diaries/5')
		cy.get('@diarySelected').should('have.been.calledOnce')
	})
})
