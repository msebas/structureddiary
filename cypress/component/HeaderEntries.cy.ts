import { defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import HeaderEntries from '@/components/layout/HeaderEntries.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import { Permissions } from '@/types/types'

describe('HeaderEntries', () => {
	it('hides entry write actions without diary write permission', () => {
		const router = createRouter({
			history: createMemoryHistory(),
			routes: [
				{ path: '/entries/:diaryId', name: 'entries', component: { template: '<div />' } },
			],
		})
		const routeReady = router.push({ name: 'entries', params: { diaryId: 5 } })

		const Wrapper = defineComponent({
			setup() {
				const store = useStructuredDiaryStore()
				store.diaries = {
					5: {
						id: 5,
						user_id: 'alice',
						title: 'Read only diary',
						description: '',
						reminder_active: false,
						reminder_time: 32400,
						reminder_count: 3,
						reminder_delay: 2700,
						reminder_signal_first: '',
						reminder_signal_repeat: '',
						entry_schedule: 86400,
						access_level: Permissions.READ,
						is_owner: false,
					},
				}
				return () => h(HeaderEntries)
			},
		})

		cy.wrap(routeReady).then(() => router.isReady()).then(() => {
			cy.mount(Wrapper, {
				global: {
					plugins: [router],
				},
			})
		})

		cy.contains('Read only diary').should('be.visible')
		cy.contains('button', 'Add entry').should('not.exist')
	})
})
