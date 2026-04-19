import DiarySidebar from '@/components/layout/DiarySidebar.vue'
import type { DiaryGroupSet } from '@/types/types'

describe('DiarySidebar', () => {
	it('renders grouped diaries and exposes actions', () => {
		const groups: DiaryGroupSet = {
			owned: [
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
			],
			managed: [
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
					access_level: 15,
					is_owner: false,
				},
			],
			writable: [],
			readable: [],
		}

		cy.mount(DiarySidebar, {
			props: {
				groups,
				search: '',
				selectedDiaryId: 1,
			},
		})

		cy.contains('Owned diaries').should('be.visible')
		cy.contains('Shared with full access').should('be.visible')
		cy.contains('Alpha diary').should('be.visible')
		cy.contains('Beta diary').should('be.visible')
		cy.contains('Shared').should('be.visible')
		cy.contains('New diary').click()
	})
})
