import type { Question, QuestionType } from '@/types/types'

function question(id: number, type: QuestionType, patch: Partial<Question> = {}): Question {
	return {
		id,
		chain_id: id,
		diary_id: 5,
		diary_question_order: id,
		created_at: 1713500000,
		label: patch.display_text ?? `Question ${id}`,
		display_text: patch.display_text ?? `Question ${id}`,
		type,
		minimum: null,
		maximum: null,
		choices: null,
		active: true,
		template_text: '',
		previous_version_id: null,
		next_version_id: null,
		...patch,
	}
}

function answerBodyFor(questionId: number, textContent: string | null, numericContent: number | null) {
	return {
		id: 100 + questionId,
		diary_id: 5,
		entry_id: 80,
		question_id: questionId,
		created_at: 1713520200,
		text_content: textContent,
		numeric_content: numericContent,
		previous_version_id: null,
		next_version_id: null,
	}
}

function setMarkdownEditorValue(value: string, index = 0): void {
	cy.get('.CodeMirror').eq(index).then(($editor) => {
		const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
		editor.setValue(value)
		editor.save()
	})
}

function installPartialSaveMocks(typeQuestion: Question): void {
	const questions = [
		question(21, 'select', { display_text: 'Select value', choices: ['A', 'B'] }),
		typeQuestion,
		question(23, 'boolean', { display_text: 'Boolean value' }),
	]
	const successfulAnswers: Array<ReturnType<typeof answerBodyFor>> = []
	const diary = {
		id: 5,
		user_id: 'alice',
		title: 'Health journal',
		description: 'Daily notes',
		reminder_active: false,
		reminder_time: null,
		reminder_count: 0,
		reminder_delay: 0,
		reminder_signal_first: null,
		reminder_signal_repeat: null,
		entry_schedule: 86400,
		access_level: 15,
		is_owner: true,
	}

	cy.intercept('GET', '**/structureddiary/api/v1/question-types*', [
		{ id: 'TEXT', value: 'text' },
		{ id: 'BOOLEAN', value: 'boolean' },
		{ id: 'RATING', value: 'rating' },
		{ id: 'NUMBER', value: 'number' },
		{ id: 'INTEGER', value: 'integer' },
		{ id: 'TIME', value: 'time' },
		{ id: 'SELECT', value: 'select' },
		{ id: 'EDITABLE_SELECT', value: 'editable_select' },
	]).as('partialQuestionTypes')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries', [diary]).as('partialDiaries')
	cy.intercept('GET', '**/structureddiary/api/v1/diary-shares*', []).as('partialAllShares')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5', diary).as('partialDiaryDetail')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/shares*', []).as('partialShares')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/stats*', {
		question_count: questions.length,
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
	}).as('partialStats')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/entries*', []).as('partialEntries')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions/active*', questions).as('partialActiveQuestions')
	cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/questions*', questions).as('partialQuestions')
	cy.intercept('POST', '**/structureddiary/api/v1/diaries/5/entries', (request) => {
		request.reply({ id: 80, diary_id: 5, timestamp: request.body.timestamp, title: request.body.title })
	}).as('partialCreateEntry')
	cy.intercept('GET', '**/structureddiary/api/v1/entries/80', { id: 80, diary_id: 5, timestamp: 1713520000, title: 'Partial entry' }).as('partialEntryDetail')
	cy.intercept('GET', '**/structureddiary/api/v1/entries/80/answers*', (request) => {
		request.reply(successfulAnswers)
	}).as('partialCreatedAnswers')
	cy.intercept('POST', '**/structureddiary/api/v1/entries/80/answers', (request) => {
		if (request.body.questionId === typeQuestion.id) {
			request.reply({ statusCode: 400, body: { error: 'Invalid answer value.' } })
			return
		}

		const answer = answerBodyFor(
			request.body.questionId,
			request.body.textContent ?? null,
			request.body.numericContent ?? null,
		)
		successfulAnswers.push(answer)
		request.reply(answer)
	}).as('partialCreateAnswer')
}

function openPartialEntryCreate(typeQuestion: Question): void {
	installPartialSaveMocks(typeQuestion)
	cy.loginToNextcloud()
	cy.visitStructuredDiary('entries/5/new')
	cy.wait('@partialQuestions')
	cy.get('input[placeholder="Entry title"]').type('Partial entry')
	cy.get('#answer-select-21').parents('.p-select').click()
	cy.get('.p-select-option').contains('B').click()
}

function saveAndExpectPartialEdit(typeQuestionId: number, typeQuestionLabel: string, assertFailedValue: () => void): void {
	cy.contains('#structured-diary-entry-edit-form footer button', 'Save').click()
	cy.wait('@partialCreateEntry')
	cy.wait('@partialCreateAnswer').its('request.body').should('deep.include', {
		questionId: 21,
		textContent: 'B',
	})
	cy.wait('@partialCreateAnswer').its('request.body').should('deep.include', {
		questionId: typeQuestionId,
	})
	cy.wait('@partialCreateAnswer').its('request.body').should('deep.include', {
		questionId: 23,
		numericContent: 0,
	})
	cy.wait('@partialCreatedAnswers')
	cy.location('pathname').should('include', '/apps/structureddiary/entries/5/80/edit')
	cy.get('#answer-select-21').should('have.text', 'B')
	cy.contains('[data-cy="answer-field"]', typeQuestionLabel)
		.should('have.attr', 'data-invalid', 'true')
	assertFailedValue()
	cy.contains('Invalid answer value.').should('be.visible')
}

describe('Structured diary partial entry save flow', () => {
	it('opens the created entry in edit mode when a limited text answer fails', () => {
		openPartialEntryCreate(question(22, 'text', { display_text: 'Limited text', minimum: 2, maximum: 5 }))
		setMarkdownEditorValue('too long text')
		saveAndExpectPartialEdit(22, 'Limited text', () => {
			cy.contains('[data-cy="answer-field"]', 'Limited text')
				.find('.CodeMirror')
				.then(($editor) => {
					const editor = ($editor[0] as unknown as { CodeMirror: { getValue: () => string } }).CodeMirror
					expect(editor.getValue()).to.equal('too long text')
				})
		})
	})

	it('opens the created entry in edit mode when a limited number answer fails', () => {
		openPartialEntryCreate(question(22, 'number', { display_text: 'Limited number', minimum: 1, maximum: 5 }))
		cy.contains('Limited number').parent().find('input').clear().type('10')
		saveAndExpectPartialEdit(22, 'Limited number', () => {
			cy.contains('[data-cy="answer-field"]', 'Limited number')
				.find('input')
				.should('have.value', '10')
		})
	})

	it('opens the created entry in edit mode when a limited integer answer fails', () => {
		openPartialEntryCreate(question(22, 'integer', { display_text: 'Limited integer', minimum: 1, maximum: 5 }))
		cy.contains('Limited integer').parent().find('input').clear().type('0')
		saveAndExpectPartialEdit(22, 'Limited integer', () => {
			cy.contains('[data-cy="answer-field"]', 'Limited integer')
				.find('input')
				.should('have.value', '0')
		})
	})

	it('opens the created entry in edit mode when a limited editable select answer fails', () => {
		openPartialEntryCreate(question(22, 'editable_select', { display_text: 'Limited editable select', minimum: 2, maximum: 5, choices: ['ok'] }))
		cy.get('#answer-select-22').clear().type('too long{enter}')
		saveAndExpectPartialEdit(22, 'Limited editable select', () => {
			cy.get('#answer-select-22').should('have.value', 'too long')
		})
	})
})
