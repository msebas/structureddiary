import { computed, defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter, useRouter } from 'vue-router'
import StructuredDiaryNavigation from '@/components/layout/StructuredDiaryNavigation.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import { Permissions, type Diary } from '@/types/types'

function diary(id: number, title: string, owner: string, accessLevel: number, isOwner = false): Diary {
	return {
		id,
		user_id: owner,
		title,
		description: '',
		reminder_active: false,
		reminder_time: 0,
		reminder_count: 3,
		reminder_delay: 2700,
		reminder_signal_first: '',
		reminder_signal_repeat: '',
		entry_schedule: 86400,
		access_level: accessLevel,
		is_owner: isOwner,
	}
}

function mountNavigation(diaries: Diary[]) {
	const router = createRouter({
		history: createMemoryHistory(),
		routes: [
			{ path: '/entries', name: 'entriesAllDiaries', component: { template: '<div />' } },
			{ path: '/entries/:diaryId', name: 'entries', component: { template: '<div />' } },
			{ path: '/diaries', name: 'diaries', component: { template: '<div />' } },
			{ path: '/diaries/:diaryId', name: 'diary', component: { template: '<div />' } },
			{ path: '/questions/:diaryId', name: 'questions', component: { template: '<div />' } },
		],
	})

	void router.push({ name: 'entriesAllDiaries' })

	const Wrapper = defineComponent({
		setup() {
			const store = useStructuredDiaryStore()
			const currentRouter = useRouter()
			store.diaries = Object.fromEntries(diaries.map((item) => [item.id, item]))
			store.diaryShares = {
				2: { '': { id: 20, diary_id: 2, shared_with: '', permission: Permissions.MANAGE | Permissions.READ } },
				3: { '': { id: 30, diary_id: 3, shared_with: '', permission: Permissions.WRITE | Permissions.READ } },
				4: { '': { id: 40, diary_id: 4, shared_with: '', permission: Permissions.READ } },
			}
			const routeName = computed(() => String(currentRouter.currentRoute.value.name ?? ''))
			const query = computed(() => String(currentRouter.currentRoute.value.query.diarySearch ?? ''))
			return () => h('div', [
				h(StructuredDiaryNavigation),
				h('div', { 'data-cy': 'route-name' }, routeName.value),
				h('div', { 'data-cy': 'query' }, query.value),
			])
		},
	})

	cy.mount(Wrapper, {
		global: {
			plugins: [router],
		},
	})
}

describe('StructuredDiaryNavigation', () => {
	it('groups, labels, searches, and toggles visible diaries', () => {
		mountNavigation([
			diary(1, 'Zulu owned', 'alice', 15, true),
			diary(2, 'Alpha managed', 'bob', Permissions.MANAGE | Permissions.READ),
			diary(3, 'Beta writable', 'carol', Permissions.WRITE | Permissions.READ),
			diary(4, 'Gamma readable', 'dave', Permissions.READ),
		])

		cy.contains('Owned diaries').should('be.visible')
		cy.contains('Shared with full access').should('be.visible')
		cy.contains('Shared with write access').should('be.visible')
		cy.contains('Shared with read access').should('be.visible')
		cy.contains('Zulu owned').should('be.visible')
		cy.contains('Alpha managed (bob)').should('be.visible')
		cy.contains('Beta writable (carol)').should('be.visible')
		cy.contains('Gamma readable (dave)').should('be.visible')

		cy.contains('Management').click({ force: true })
		cy.get('[data-cy="route-name"]').should('contain', 'diaries')
		cy.contains('Entries').click({ force: true })
		cy.get('[data-cy="route-name"]').should('contain', 'entriesAllDiaries')

		cy.get('input[type="search"], input[placeholder="Search diaries"]').first().type('carol', { force: true })
		cy.contains('Beta writable (carol)').should('be.visible')
		cy.contains('Zulu owned').should('not.exist')
		cy.contains('Owned diaries').should('not.exist')

		cy.get('input[type="search"], input[placeholder="Search diaries"]').first().clear({ force: true })
		cy.get('[data-cy="query"]').should('be.empty')
		cy.contains('Zulu owned').should('be.visible')
		cy.contains('Alpha managed (bob)').should('be.visible')
	})
})
