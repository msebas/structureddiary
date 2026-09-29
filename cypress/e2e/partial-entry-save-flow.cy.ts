import type { Question, QuestionType } from '@/types/types'

interface PartialCase {
	title: string
	type: QuestionType
	minimum: number
	maximum: number
	enterValue: (questionId: number) => void
	assertValue: (questionId: number) => void
}

function setMarkdownEditorValue(value: string): void {
	cy.get('.CodeMirror').first().then(($editor) => {
		const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
		editor.setValue(value)
		editor.save()
	})
}

const cases: PartialCase[] = [
	{
		title: 'Limited text',
		type: 'text',
		minimum: 2,
		maximum: 5,
		enterValue: () => setMarkdownEditorValue('too long text'),
		assertValue: () => cy.get('.CodeMirror').first().then(($editor) => {
			const editor = ($editor[0] as unknown as { CodeMirror: { getValue: () => string } }).CodeMirror
			expect(editor.getValue()).to.equal('too long text')
		}),
	},
	{
		title: 'Limited number',
		type: 'number',
		minimum: 1,
		maximum: 5,
		enterValue: () => cy.contains('[data-cy="answer-field"]', 'How do you feel today?').find('input').clear().type('10'),
		assertValue: () => cy.contains('[data-cy="answer-field"]', 'How do you feel today?').find('input').should('have.value', '10'),
	},
	{
		title: 'Limited integer',
		type: 'integer',
		minimum: 1,
		maximum: 5,
		enterValue: () => cy.contains('[data-cy="answer-field"]', 'How do you feel today?').find('input').clear().type('0'),
		assertValue: () => cy.contains('[data-cy="answer-field"]', 'How do you feel today?').find('input').should('have.value', '0'),
	},
	{
		title: 'Limited editable select',
		type: 'editable_select',
		minimum: 2,
		maximum: 5,
		enterValue: (questionId) => cy.get(`#answer-select-${questionId}`).clear().type('too long{enter}'),
		assertValue: (questionId) => cy.get(`#answer-select-${questionId}`).should('have.value', 'too long'),
	},
]

describe('Structured diary partial entry save flow', () => {
	for (const testCase of cases) {
		it(`opens the created entry in edit mode when a limited ${testCase.type} answer fails`, () => {
			cy.seedStructuredDiary({
				questionType: testCase.type,
				questionMinimum: testCase.minimum,
				questionMaximum: testCase.maximum,
				questionChoices: testCase.type === 'editable_select' ? ['ok'] : null,
			}).then(({ diary, question }) => {
				cy.apiRequest<Question>('POST', `diaries/${diary.id}/questions`, {
					label: 'Select value',
					displayText: 'Select value',
					type: 'select',
					choices: ['A', 'B'],
					active: true,
				}).then((selectQuestion: Question) => {
					cy.apiRequest('POST', `diaries/${diary.id}/questions`, {
						label: 'Boolean value',
						displayText: 'Boolean value',
						type: 'boolean',
						active: true,
					})
					cy.intercept('POST', `**/structureddiary/api/v1/entries/*/answers`).as('createAnswer')
					cy.loginToNextcloud()
					cy.visitStructuredDiary(`entries/${diary.id}/new`)
					cy.get('input[placeholder="Entry title"]').type('Partial entry')
					cy.get(`#answer-select-${selectQuestion.id}`).parents('.p-select').click()
					cy.get('.p-select-option').contains('B').click()
					testCase.enterValue(question.id)
					cy.contains('#structured-diary-entry-edit-form footer button', 'Save').click()
					cy.wait('@createAnswer')
					cy.wait('@createAnswer')
					cy.wait('@createAnswer')
					cy.location('pathname').should('match', new RegExp(`/apps/structureddiary/entries/${diary.id}/\\d+/edit$`))
					cy.contains('[data-cy="answer-field"]', 'How do you feel today?')
						.should('have.attr', 'data-invalid', 'true')
					testCase.assertValue(question.id)
					cy.contains(/answer is|selection answer|text answer/i).should('be.visible')
				})
			})
		})
	}
})
