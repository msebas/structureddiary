import { computed, defineComponent } from 'vue'
import { createMemoryHistory, createRouter, useRouter } from 'vue-router'
import StructuredDiaryNavigation from '@/components/layout/StructuredDiaryNavigation.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { Diary } from '@/types/types'

describe('StructuredDiaryNavigation', () => {
	it('renders grouped diaries and toggles between management and entries', () => {
		const diaries: Diary[] = [
			{
				id: 1,
				user_id: 'alice',
				title: 'Alpha diary',
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
			{
				id: 2,
				user_id: 'bob',
				title: 'Beta diary',
				description: '',
				reminder_active: false,
				reminder_time: 0,
				reminder_count: 3,
				reminder_delay: 2700,
				reminder_signal_first: '',
				reminder_signal_repeat: '',
				entry_schedule: 86400,
				access_level: 9,
				is_owner: false,
			},
		]

		const router = createRouter({
			history: createMemoryHistory(),
			routes: [
				{ path: '/entries', name: 'entries', component: { template: '<div />' } },
				{ path: '/diaries', name: 'diaries', component: { template: '<div />' } },
				{ path: '/questions', name: 'questions', component: { template: '<div />' } },
			],
		})

		void router.push({ name: 'entries' })

		const Wrapper = defineComponent({
			components: { StructuredDiaryNavigation },
			setup() {
				const store = useStructuredDiaryStore()
				const currentRouter = useRouter()
				store.diaries = diaries
				store.selectedDiaryId = 1
				const routeName = computed(() => String(currentRouter.currentRoute.value.name ?? ''))
				return { routeName }
			},
			template: `
				<div>
					<StructuredDiaryNavigation />
					<div data-cy="route-name">{{ routeName }}</div>
				</div>
			`,
		})

		cy.mount(Wrapper, {
			global: {
				plugins: [router],
			},
		})

		cy.contains('Owned diaries').should('be.visible')
		cy.contains('Shared with full access').should('be.visible')
		cy.contains('Alpha diary').should('be.visible')
		cy.contains('Beta diary (bob)').should('be.visible')
		cy.contains('Management').click()
		cy.get('[data-cy="route-name"]').should('contain', 'diaries')
		cy.contains('Entries').click()
		cy.get('[data-cy="route-name"]').should('contain', 'entries')
	})
})
