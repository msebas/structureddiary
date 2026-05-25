import { defineComponent, h } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import DiaryEditView from '@/views/DiaryEditView.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { Diary, DiaryStats } from '@/types/types'

const diary: Diary = {
	id: 5,
	user_id: 'alice',
	title: 'Wellness',
	description: 'Initial description',
	reminder_active: false,
	reminder_time: 32400,
	reminder_count: 3,
	reminder_delay: 2700,
	reminder_signal_first: 'bell',
	reminder_signal_repeat: 'soft-bell',
	entry_schedule: 86400,
	access_level: 15,
	is_owner: true,
}

const stats: DiaryStats = {
	question_count: 2,
	entry_count: 2,
	answer_count: 4,
	average_answer_count: 2,
	first_entry_at: 1713400000,
	latest_entry_at: 1713500000,
	entry_frequency: { mean: null, stddev: null },
	entry_frequency_last_month: { mean: null, stddev: null },
	gap_count_above_ten_target_intervals: 0,
	last_large_gap: null,
	longest_gap: null,
	average_entry_duration: null,
	average_entry_duration_last_month: null,
	latest_answer_at: null,
}

function mountDiaryEditView() {
	const pinia = createPinia()
	setActivePinia(pinia)

	cy.intercept('PUT', '**/structureddiary/api/v1/diaries/5', (request) => {
		request.reply({
			...diary,
			title: request.body.title,
			description: request.body.description,
			reminder_active: request.body.reminderActive,
			reminder_time: request.body.reminderTime,
			reminder_count: request.body.reminderCount,
			reminder_delay: request.body.reminderDelay,
			reminder_signal_first: request.body.reminderSignalFirst,
			reminder_signal_repeat: request.body.reminderSignalRepeat,
			entry_schedule: request.body.entrySchedule,
		})
	}).as('updateDiary')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5', diary).as('diaryDetail')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries', [diary]).as('diaries')
	cy.intercept('GET', '**/structureddiary/api/v1/diary-shares*', []).as('allShares')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions*', []).as('questions')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/stats*', stats).as('stats')
	cy.intercept('GET', '**/structureddiary/api/v1/alarm-sounds*', [
		{ id: 1, name: 'Bell', path: 'bell', created_at: 1713500000, last_seen_at: 1713500000, is_default: true, os_affinity: ['ios:17', 'android:15'] },
		{ id: 2, name: 'Soft Bell', path: 'soft-bell', created_at: 1713500000, last_seen_at: 1713500000, is_default: false, os_affinity: ['android:15'] },
	]).as('alarmSounds')
	cy.intercept('GET', '**/apps/files_sharing/api/v1/sharees*', {
		ocs: { data: { exact: { users: [] }, users: [] } },
	}).as('sharees')

	const router = createRouter({
		history: createMemoryHistory(),
		routes: [
			{ path: '/diaries/:diaryId/edit', name: 'diaryEdit', component: DiaryEditView },
			{ path: '/diaries/:diaryId', name: 'diary', component: { template: '<div />' } },
		],
	})
	void router.push({ name: 'diaryEdit', params: { diaryId: 5 } })

	const Wrapper = defineComponent({
		setup() {
			const store = useStructuredDiaryStore()
			store.diaries = { 5: diary }
			store.diaryShares = { 5: {} }
			store.diaryStatsById = { 5: stats }
			return () => h(DiaryEditView)
		},
	})

	cy.mount(Wrapper, {
		global: {
			plugins: [pinia, router],
		},
	})
}

describe('DiaryEditView', () => {
	it('disables owner changes when entries exist and saves reminder settings', () => {
		mountDiaryEditView()

		cy.contains('Edit diary').should('be.visible')
		cy.get('input[placeholder="Select readers"]').should('exist')
		cy.get('input[placeholder="Select writers"]').should('exist')
		cy.get('input[placeholder="Select users allowed to analyze"]').should('exist')
		cy.get('input[placeholder="Select managers"]').should('exist')
		cy.contains('Owner').scrollIntoView().parent().find('.vs--disabled').should('exist')
		cy.contains('Title').parent().find('input').clear().type('Wellness updated')
		cy.contains('Description').parent().find('textarea').clear().type('Updated **markdown** description')
		cy.contains('Reminder active').scrollIntoView()
		cy.contains('Disabled').click()
		cy.wait('@alarmSounds')
		cy.contains('option', 'Bell (ios:17, android:15)').should('exist')
		cy.get('input[type="time"]').clear().type('15:00')
		cy.contains('Entry cadence in days').parent().find('select').select('0.5')
		cy.get('input[type="time"]').should('have.value', '09:00')
		cy.get('input[type="time"]').clear().type('08:30')
		cy.get('input[type="number"]').eq(0).then(($input) => {
			cy.wrap($input).invoke('val', '4').trigger('input')
		})
		cy.get('input[type="number"]').eq(1).then(($input) => {
			cy.wrap($input).invoke('val', '1800').trigger('input')
		})
		cy.contains('Save diary').scrollIntoView().click()

		cy.wait('@updateDiary').its('request.body').should('deep.include', {
			title: 'Wellness updated',
			description: 'Updated **markdown** description',
			reminderActive: true,
			reminderTime: 30600,
			reminderCount: 4,
			reminderDelay: 1800,
			entrySchedule: 43200,
		})
	})
})
