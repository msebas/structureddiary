import DiaryDisplayCard from '@/components/diaries/DiaryDisplayCard.vue'
import type { Diary, DiaryShare, DiaryStats } from '@/types/types'

const diary: Diary = {
	id: 5,
	user_id: 'alice',
	title: 'Health journal',
	description: 'Daily **markdown** notes',
	reminder_active: true,
	reminder_time: 32400,
	reminder_count: 3,
	reminder_delay: 2700,
	reminder_signal_first: 'bell',
	reminder_signal_repeat: 'soft-bell',
	entry_schedule: 43200,
	access_level: 15,
	is_owner: true,
}

const shares: DiaryShare[] = [
	{ id: 1, diary_id: 5, shared_with: 'bob', permission: 1 },
	{ id: 2, diary_id: 5, shared_with: 'carol', permission: 3 },
	{ id: 3, diary_id: 5, shared_with: 'dave', permission: 13 },
]

const stats: DiaryStats = {
	question_count: 4,
	entry_count: 8,
	answer_count: 12,
	average_answer_count: 1.5,
	first_entry_at: 1713400000,
	latest_entry_at: 1713500000,
	entry_frequency: { mean: 86400, stddev: 60 },
	entry_frequency_last_month: { mean: 90000, stddev: 120 },
	gap_count_above_ten_target_intervals: 1,
	last_large_gap: { start: 1713000000, end: 1713200000, duration: 200000 },
	longest_gap: { start: 1713000000, end: 1713200000, duration: 200000 },
	average_entry_duration: 600,
	average_entry_duration_last_month: 900,
	latest_answer_at: 1713510000,
}

describe('DiaryDisplayCard', () => {
	it('renders diary schedule, shares, and statistics when available', () => {
		cy.mount(DiaryDisplayCard, {
			props: { diary, shares, stats },
		})

		cy.contains('Health journal').should('be.visible')
		cy.contains('Owner: alice').should('be.visible')
		cy.contains('Daily **markdown** notes').should('be.visible')
		cy.contains('Target cadence').should('be.visible')
		cy.contains('0.5 days').should('be.visible')
		cy.contains('Reminder').should('be.visible')
		cy.contains('Active').should('be.visible')
		cy.contains('Reminder time').should('be.visible')
		cy.contains('09:00').should('be.visible')
		cy.contains('Repeat count').should('be.visible')
		cy.contains('3').should('be.visible')
		cy.contains('bob').should('be.visible')
		cy.contains('read').should('be.visible')
		cy.contains('carol').should('be.visible')
		cy.contains('write').should('be.visible')
		cy.contains('dave').should('be.visible')
		cy.contains('analyze, manage').should('be.visible')
		cy.contains('Questions').should('be.visible')
		cy.contains('4').should('be.visible')
		cy.contains('Avg answers').should('be.visible')
		cy.contains('1.50').should('be.visible')
		cy.contains('Avg duration').should('be.visible')
		cy.contains('10m').should('be.visible')
	})

	it('hides statistics when requested', () => {
		cy.mount(DiaryDisplayCard, {
			props: { diary, shares, stats, hideStats: true },
		})

		cy.contains('Health journal').should('be.visible')
		cy.contains('Statistics').should('not.exist')
		cy.contains('Questions: 4').should('not.exist')
	})
})
