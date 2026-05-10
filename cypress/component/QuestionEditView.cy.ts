import { defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import QuestionEditView from '@/components/questions/QuestionEditView.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { Question } from '@/types/types'

function mountQuestionCreateView() {
	let createCalls = 0
	cy.intercept('POST', '**/structureddiary/api/v1/diaries/5/questions', (request) => {
		createCalls += 1
		request.reply({
			id: 33,
			chain_id: 33,
			diary_id: 5,
			diary_question_order: 33,
			created_at: 1713520000,
			label: request.body.label,
			display_text: request.body.displayText,
			type: request.body.type,
			minimum: request.body.minimum,
			maximum: request.body.maximum,
			choices: request.body.choices,
			active: request.body.active,
			template_text: request.body.templateText,
			previous_version_id: null,
			next_version_id: null,
		})
	}).as('createQuestion')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions*', []).as('questions')
	cy.intercept('GET', '**/structureddiary/api/v1/questions/33/versions', []).as('questionVersions')

	const router = createRouter({
		history: createMemoryHistory(),
		routes: [
			{ path: '/questions/:diaryId/new', name: 'questionCreate', component: QuestionEditView },
			{ path: '/questions/:diaryId/:questionId', name: 'question', component: { template: '<div />' } },
		],
	})
	void router.push({ name: 'questionCreate', params: { diaryId: 5 } })

	const Wrapper = defineComponent({
		setup() {
			const store = useStructuredDiaryStore()
			store.questionTypes = [
				{ id: 'TEXT', value: 'text' },
				{ id: 'NUMBER', value: 'number' },
				{ id: 'INTEGER', value: 'integer' },
				{ id: 'SELECT', value: 'select' },
				{ id: 'EDITABLE_SELECT', value: 'editable_select' },
			]
			return () => h(QuestionEditView)
		},
	})

	cy.mount(Wrapper, {
		global: {
			plugins: [router],
		},
	})

	return cy.wrap(null).then(() => ({
		createCalls: () => createCalls,
	}))
}

function mountQuestionEditView(question: Question) {
	const diary = {
		id: 5,
		user_id: 'alice',
		title: 'Health journal',
		description: '',
		reminder_active: false,
		reminder_time: 32400,
		reminder_count: 3,
		reminder_delay: 2700,
		reminder_signal_first: '',
		reminder_signal_repeat: '',
		entry_schedule: 86400,
		access_level: 15,
		is_owner: true,
	}
	cy.intercept('GET', '**/structureddiary/api/v1/diaries', [diary]).as('diaries')
	cy.intercept('GET', '**/structureddiary/api/v1/diary-shares*', []).as('allShares')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5', diary).as('diary')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/shares*', []).as('shares')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/entries*', []).as('entries')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/stats*', {
		question_count: 1,
		entry_count: 0,
		answer_count: 0,
		average_answer_count: 0,
		first_entry_at: null,
		latest_entry_at: null,
		entry_frequency: { mean: null, stddev: null },
		entry_frequency_last_month: { mean: null, stddev: null },
		gap_count_above_ten_target_intervals: 0,
		last_large_gap: null,
		longest_gap: null,
		average_entry_duration: null,
		average_entry_duration_last_month: null,
		latest_answer_at: null,
	}).as('stats')
	cy.intercept('GET', '**/structureddiary/api/v1/questions/17', question).as('question')
	cy.intercept('GET', '**/structureddiary/api/v1/questions/18', {
		...question,
		id: 18,
		previous_version_id: 17,
	}).as('newQuestion')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions*', [question]).as('questions')
	cy.intercept('GET', '**/structureddiary/api/v1/questions/18/versions', []).as('questionVersions')
	cy.intercept('PUT', '**/structureddiary/api/v1/questions/17', (request) => {
		request.reply({
			...question,
			id: 18,
			created_at: 1713530000,
			label: request.body.label,
			display_text: request.body.displayText,
			type: request.body.type,
			minimum: request.body.minimum,
			maximum: request.body.maximum,
			choices: request.body.choices,
			active: request.body.active,
			template_text: request.body.templateText,
			previous_version_id: 17,
			next_version_id: null,
		})
	}).as('updateQuestion')

	const router = createRouter({
		history: createMemoryHistory(),
		routes: [
			{ path: '/questions/:diaryId/:questionId/edit', name: 'questionEdit', component: QuestionEditView },
			{ path: '/questions/:diaryId/:questionId', name: 'question', component: { template: '<div />' } },
		],
	})
	void router.push({ name: 'questionEdit', params: { diaryId: 5, questionId: 17 } })

	const Wrapper = defineComponent({
		setup() {
			const store = useStructuredDiaryStore()
			store.questionTypes = [
				{ id: 'TEXT', value: 'text' },
				{ id: 'SELECT', value: 'select' },
				{ id: 'EDITABLE_SELECT', value: 'editable_select' },
			]
			void store.initialize()
			return () => h(QuestionEditView)
		},
	})

	cy.mount(Wrapper, {
		global: {
			plugins: [router],
		},
	})
}

describe('QuestionEditView', () => {
	it('blocks saving invalid ranges without calling the create endpoint', () => {
		mountQuestionCreateView().then(({ createCalls }) => {
			cy.contains('Label').parent().find('input').first().type('Invalid range question')
			cy.contains('Minimum').parent().find('input').clear().type('10')
			cy.contains('Maximum').parent().find('input').clear().type('5')
			cy.contains('button', 'Save question').click()

			cy.contains('Minimum must be smaller than or equal to maximum.').should('be.visible')
			cy.then(() => {
				expect(createCalls()).to.equal(0)
			})
		})
	})

	it('parses comma-separated choices for selection questions', () => {
		mountQuestionEditView({
			id: 17,
			chain_id: 17,
			diary_id: 5,
			diary_question_order: 17,
			created_at: 1713500000,
			label: 'Color',
			display_text: 'Color',
			type: 'select',
			minimum: null,
			maximum: null,
			choices: ['Blue'],
			active: true,
			template_text: '',
			previous_version_id: null,
			next_version_id: null,
		})
		cy.wait('@question')
		cy.contains('Choices').parent().find('textarea').clear().type('Blue, Green, , Red')
		cy.contains('button', 'Save question').click()

		cy.wait('@updateQuestion').its('request.body').should('deep.include', {
			questionId: 17,
			chainId: 17,
			label: 'Color',
			type: 'select',
			choices: ['Blue', 'Green', 'Red'],
		})
	})
})
