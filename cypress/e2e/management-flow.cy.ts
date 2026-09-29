import type { Diary, DiaryShare, Question } from '@/types/types'

const mutationIdPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
const bob = 'e2e-bob'
const carol = 'e2e-carol'

function selectUser(placeholder: string, query: string, label: string): void {
	cy.get(`input[placeholder="${placeholder}"]`).type(query)
	cy.wait('@shareesSearch')
	cy.contains('.vs__dropdown-option', label).click()
}

function selectUserFromField(fieldLabel: string, query: string, label: string): void {
	cy.contains('label', fieldLabel).parent().find('input').first().click({ force: true }).type(query, { force: true })
	cy.wait('@shareesSearch')
	cy.contains('.vs__dropdown-option', label).click()
}

function removeSelectedUserFromField(fieldLabel: string, userId: string): void {
	cy.contains('label', fieldLabel)
		.parent()
		.contains('.vs__selected', userId)
		.find('.vs__deselect')
		.click({ force: true })
}

function setMarkdownEditorValue(value: string, index = 0): void {
	cy.get('.CodeMirror').eq(index).then(($editor) => {
		const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
		editor.setValue(value)
		editor.save()
	})
}

describe('Structured diary management flow', () => {
	beforeEach(() => {
		Cypress.env('ncUsername', 'admin')
		Cypress.env('ncPassword', 'admin')
	})

	it('creates a diary from the workspace header', () => {
		cy.seedStructuredDiary().then(({ diary }) => {
			cy.ensureNextcloudUser(bob, 'Bob E2E')
			cy.intercept('GET', '**/apps/files_sharing/api/v1/sharees*').as('shareesSearch')
			cy.intercept('POST', '**/structureddiary/api/v1/diaries').as('createDiary')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`diaries/${diary.id}`)
			cy.get('[aria-label="Create new diary"]').first().click()
			cy.contains('Title').parent().find('input').type('Copied journal')
			cy.contains('Description').parent().find('textarea').type('Draft description')
			selectUserFromField('Owner', bob, 'Bob E2E')
			cy.contains('button', 'Create diary').click()
			cy.wait('@createDiary').its('request.body').should('deep.include', {
				title: 'Copied journal',
				description: 'Draft description',
				ownerUserId: bob,
			}).then((body) => expect(body.clientMutationId).to.match(mutationIdPattern))
			cy.contains('Copied journal').should('be.visible')
		})
	})

	it('creates a question from the diary management panel', () => {
		cy.seedStructuredDiary().then(({ diary }) => {
			cy.intercept('POST', `**/structureddiary/api/v1/diaries/${diary.id}/questions`).as('createQuestion')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`diaries/${diary.id}`)
			cy.contains('New question').click()
			cy.contains('Label').parent().find('input').first().type('Energy')
			cy.contains('Sync with label').click()
			setMarkdownEditorValue('How much energy did you have?')
			setMarkdownEditorValue('0 to 10', 1)
			cy.contains('#structured-diary-question-edit-form button', 'Save question').click()
			cy.wait('@createQuestion').its('request.body').should('deep.include', {
				label: 'Energy',
				displayText: 'How much energy did you have?',
				type: 'text',
				templateText: '0 to 10',
			}).then((body) => expect(body.clientMutationId).to.match(mutationIdPattern))
		})
	})

	it('cancels question creation back to the selected diary overview', () => {
		cy.seedStructuredDiary().then(({ diary }) => {
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`questions/${diary.id}/new`)
			cy.contains('#structured-diary-question-edit-form button', 'Cancel').click()
			cy.location('pathname').should('include', `/apps/structureddiary/diaries/${diary.id}`)
			cy.contains('Statistics').should('be.visible')
		})
	})

	it('deletes a selected unanswered question from the question header', () => {
		cy.seedStructuredDiary().then(({ diary }) => {
			cy.apiRequest<Question>('POST', `diaries/${diary.id}/questions`, {
				label: 'Temporary question',
				displayText: 'Temporary question',
				type: 'text',
				active: true,
			}).then((question) => {
				cy.intercept('DELETE', `**/structureddiary/api/v1/questions/${question.id}`).as('deleteQuestion')
				cy.loginToNextcloud()
				cy.visitStructuredDiary(`questions/${diary.id}/${question.id}`)
				cy.contains('button', 'Delete question').click()
				cy.wait('@deleteQuestion')
				cy.location('pathname').should('include', `/apps/structureddiary/diaries/${diary.id}`)
				cy.apiRequest<Question[]>('GET', `diaries/${diary.id}/questions`)
					.then((questions) => expect(questions.some(({ id }) => id === question.id)).to.equal(false))
			})
		})
	})

	it('hides question deletion when a single-version question already has answers', () => {
		cy.seedStructuredDiary().then(({ diary, question }) => {
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`questions/${diary.id}/${question.id}`)
			cy.contains('button', 'Delete question').should('not.exist')
			cy.contains('button', 'Edit question').should('be.visible')
		})
	})

	it('creates multiple question versions when an answered question changes repeatedly', () => {
		cy.seedStructuredDiary().then(({ diary, question }) => {
			cy.intercept('PUT', '**/structureddiary/api/v1/questions/*').as('updateQuestion')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`questions/${diary.id}/${question.id}/edit`)
			cy.contains('Label').parent().find('input').first().clear().type('Mood v2')
			setMarkdownEditorValue('How is your mood now?')
			setMarkdownEditorValue('Write a longer note', 1)
			cy.contains('#structured-diary-question-edit-form button', 'Save question').click()
			let firstMutationId = ''
			cy.wait('@updateQuestion').its('request.body').should('deep.include', {
				label: 'Mood v2',
				displayText: 'How is your mood now?',
				revisionCount: 2,
			}).then((body) => {
				expect(body.clientMutationId).to.match(mutationIdPattern)
				firstMutationId = body.clientMutationId
			})

			cy.contains('button', 'Edit question').click()
			cy.contains('Label').parent().find('input').first().clear().type('Mood v3')
			setMarkdownEditorValue('How is your mood this evening?')
			cy.contains('#structured-diary-question-edit-form button', 'Save question').click()
			cy.wait('@updateQuestion').its('request.body').should('deep.include', {
				label: 'Mood v3',
				displayText: 'How is your mood this evening?',
				revisionCount: 3,
			}).then((body) => {
				expect(body.clientMutationId).to.match(mutationIdPattern)
				expect(body.clientMutationId).not.to.equal(firstMutationId)
			})
			cy.contains('Mood v3').should('be.visible')
			cy.contains('Mood v2').should('be.visible')
			cy.contains('Mood').should('be.visible')
		})
	})

	it('asks before deleting a diary and removes it from Nextcloud', () => {
		cy.seedStructuredDiary().then(({ diary }) => {
			cy.intercept('DELETE', `**/structureddiary/api/v1/diaries/${diary.id}`).as('deleteDiary')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`diaries/${diary.id}/edit`)
			cy.contains('button', /^Delete diary/).click()
			cy.contains('[role="dialog"]', '1 entry').should('be.visible')
			cy.contains('[role="dialog"] button', 'Delete diary').click()
			cy.wait('@deleteDiary')
			cy.location('pathname').should('match', /\/apps\/structureddiary\/diaries\/?$/)
			cy.apiRequest<Array<{ id: number }>>('GET', 'diaries')
				.then((diaries) => expect(diaries.some(({ id }) => id === diary.id)).to.equal(false))
		})
	})

	it('keeps the creator as manager when ownership moves to another user', () => {
		cy.apiRequest<Diary>('POST', 'diaries', {
			title: 'Transfer journal',
			description: 'Ownership transfer',
			entrySchedule: 86400,
		}).then((diary) => {
			cy.ensureNextcloudUser(bob, 'Bob E2E')
			cy.ensureNextcloudUser(carol, 'Carol E2E')
			cy.intercept('GET', '**/apps/files_sharing/api/v1/sharees*').as('shareesSearch')
			cy.intercept('PUT', `**/structureddiary/api/v1/diaries/${diary.id}`).as('transferDiary')
			cy.intercept('POST', `**/structureddiary/api/v1/diaries/${diary.id}/shares`).as('createShare')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`diaries/${diary.id}/edit`)
			selectUserFromField('Owner', bob, 'Bob E2E')
			selectUser('Select readers', carol, 'Carol E2E')
			cy.contains('button', 'Save diary').click()
			cy.wait('@transferDiary').its('request.body').should('deep.include', { ownerUserId: bob, revisionCount: 2 })
			cy.wait('@createShare')
			cy.apiRequest<DiaryShare[]>('GET', `diaries/${diary.id}/shares`).then((shares) => {
				expect(shares).to.deep.include.members([
					{ id: shares.find(({ shared_with }) => shared_with === carol)!.id, diary_id: diary.id, shared_with: carol, permission: 1 },
					{ id: shares.find(({ shared_with }) => shared_with === 'admin')!.id, diary_id: diary.id, shared_with: 'admin', permission: 9 },
				])
			})
			cy.location('pathname').should('include', `/apps/structureddiary/diaries/${diary.id}`)
		})
	})

	it('creates analyze shares with read and analyze permissions', () => {
		cy.seedStructuredDiary().then(({ diary }) => {
			cy.ensureNextcloudUser(carol, 'Carol E2E')
			cy.intercept('GET', '**/apps/files_sharing/api/v1/sharees*').as('shareesSearch')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`diaries/${diary.id}/edit`)
			selectUser('Select users allowed to analyze', carol, 'Carol E2E')
			cy.contains('button', 'Save diary').click()
			cy.apiRequest<DiaryShare[]>('GET', `diaries/${diary.id}/shares`)
				.then((shares) => expect(shares.find(({ shared_with }) => shared_with === carol)?.permission).to.equal(5))
		})
	})

	it('does not request or render statistics without analyze permission', () => {
		cy.seedStructuredDiary().then(({ diary }) => {
			cy.ensureNextcloudUser(carol, 'Carol E2E')
			cy.apiRequest('POST', `diaries/${diary.id}/shares`, { sharedWith: carol, permission: 1 })
			Cypress.env('ncUsername', carol)
			Cypress.env('ncPassword', 'admin')
			cy.clearCookies()
			let statsCalls = 0
			cy.intercept('GET', `**/structureddiary/api/v1/diaries/${diary.id}/stats*`, (request) => {
				statsCalls += 1
				request.continue()
			})
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`diaries/${diary.id}`)
			cy.contains('Health journal').should('be.visible')
			cy.contains('Statistics').should('not.exist')
			cy.then(() => expect(statsCalls).to.equal(0))
		})
	})

	it('removes another user share without leaving the current diary', () => {
		cy.seedStructuredDiary().then(({ diary }) => {
			cy.ensureNextcloudUser(bob, 'Bob E2E')
			cy.apiRequest<DiaryShare>('POST', `diaries/${diary.id}/shares`, { sharedWith: bob, permission: 3 }).then((share) => {
				cy.intercept('DELETE', `**/structureddiary/api/v1/diaries/${diary.id}/shares/${share.id}`).as('deleteShare')
				cy.loginToNextcloud()
				cy.visitStructuredDiary(`diaries/${diary.id}/edit`)
				removeSelectedUserFromField('Writers', bob)
				removeSelectedUserFromField('Readers', bob)
				cy.contains('button', 'Save diary').click()
				cy.wait('@deleteShare')
				cy.location('pathname').should('include', `/apps/structureddiary/diaries/${diary.id}`)
				cy.apiRequest<DiaryShare[]>('GET', `diaries/${diary.id}/shares`)
					.then((shares) => expect(shares.some(({ shared_with }) => shared_with === bob)).to.equal(false))
			})
		})
	})
})
