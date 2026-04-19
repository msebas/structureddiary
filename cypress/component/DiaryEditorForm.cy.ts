import DiaryEditorForm from '@/components/diaries/DiaryEditorForm.vue'
import type { Diary } from '@/types/types'

describe('DiaryEditorForm', () => {
	it('emits normalized reminder and schedule values', () => {
		const saveSpy = cy.spy().as('saveSpy')
		const diary: Diary = {
			id: 9,
			user_id: 'alice',
			title: 'Wellness',
			description: 'Initial description',
			reminder_active: true,
			reminder_time: 32400,
			reminder_count: 3,
			reminder_delay: 2700,
			reminder_signal_first: 'bell',
			reminder_signal_repeat: 'soft-bell',
			entry_schedule: 86400,
			access_level: 15,
			is_owner: true,
		}

		cy.mount(DiaryEditorForm, {
			props: {
				diary,
				canChangeOwner: false,
				onSave: saveSpy,
			},
		})

		cy.contains('Edit diary').should('be.visible')
		cy.get('input[disabled]').should('have.value', 'alice')
		cy.contains('Reminder active').parent().find('input[type="checkbox"]').uncheck({ force: true })
		cy.contains('Reminder active').parent().find('input[type="checkbox"]').check({ force: true })
		cy.get('input[type="time"]').clear().type('08:30')
		cy.get('input[type="number"]').eq(0).clear().type('0.5')
		cy.get('input[type="number"]').eq(1).clear().type('4')
		cy.get('input[type="number"]').eq(2).clear().type('1800')
		cy.contains('Save diary').click()

		cy.get('@saveSpy').should('have.been.calledOnce')
		cy.get('@saveSpy').its('firstCall.args.0').should('deep.include', {
			title: 'Wellness',
			description: 'Initial description',
			ownerUserId: 'alice',
			entrySchedule: 43200,
			reminderActive: true,
			reminderTime: 30600,
			reminderCount: 4,
			reminderDelay: 1800,
			reminderSignalFirst: 'bell',
			reminderSignalRepeat: 'soft-bell',
		})
	})
})
