const currentUser = String(Cypress.env('ncUsername') ?? 'admin')

const diary = {
	id: 5,
	user_id: currentUser,
	title: 'Health journal',
	description: 'Daily notes',
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

const emptyStats = {
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
}

function selectUser(placeholder: string, query: string, label: string): void {
	cy.get(`input[placeholder="${placeholder}"]`).type(query)
	cy.wait('@shareesSearch')
	cy.contains('.vs__dropdown-option', label).click()
}

function selectUserFromField(fieldLabel: string, query: string, label: string): void {
	cy.contains('label', fieldLabel)
		.parent()
		.find('input')
		.first()
		.click({ force: true })
		.type(query, { force: true })
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
	it('creates a diary from the workspace header', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.intercept('GET', '**/apps/files_sharing/api/v1/sharees*', {
			ocs: {
				data: {
					exact: { users: [] },
					users: [{ label: 'Bob', value: { shareWith: 'bob' } }],
				},
			},
		}).as('shareesSearch')
		cy.loginToNextcloud()
		cy.visitStructuredDiary('diaries/5')
		cy.get('[aria-label="Create new diary"]').first().click()
		cy.contains('Create diary').should('be.visible')
		cy.contains('Title').parent().find('input').type('Copied journal')
		cy.contains('Description').parent().find('textarea').type('Draft description')
		selectUserFromField('Owner', 'bob', 'Bob')
		cy.contains('button', 'Create diary').click()

		cy.wait('@createDiary').its('request.body').should('deep.include', {
			title: 'Copied journal',
			description: 'Draft description',
			ownerUserId: 'bob',
		})
		cy.contains('Copied journal').should('be.visible')
	})

	it('creates a question from the diary management panel', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.loginToNextcloud()
		cy.visitStructuredDiary('diaries/5')
		cy.contains('New question').click()
		cy.contains('Create question').should('be.visible')
		cy.contains('Label').parent().find('input').first().type('Energy')
		cy.contains('Sync with label').click()
		setMarkdownEditorValue('How much energy did you have?')
		setMarkdownEditorValue('0 to 10', 1)
		cy.contains('Save question').click()

		cy.wait('@createQuestion').its('request.body').should('deep.include', {
			label: 'Energy',
			displayText: 'How much energy did you have?',
			type: 'text',
			active: true,
			templateText: '0 to 10',
		})
	})

	it('cancels question creation back to the selected diary overview', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.loginToNextcloud()
		cy.visitStructuredDiary('questions/5/new')
		cy.contains('Create question').should('be.visible')
		cy.contains('button', 'Cancel').click()
		cy.location('pathname').should('include', '/apps/structureddiary/diaries/5')
		cy.contains('Statistics').should('be.visible')
	})

	it('deletes a selected question from the question header', () => {
		cy.mockStructuredDiaryBootstrap()
		let questionDeleted = false
		let versionsRequestedAfterDelete = false
		cy.intercept('GET', '**/structureddiary/api/v1/questions/17/answer-count', { count: 0 }).as('questionAnswerCountEmpty')
		cy.intercept('GET', '**/structureddiary/api/v1/questions/17/versions', (request) => {
			if (questionDeleted) {
				versionsRequestedAfterDelete = true
			}
			request.reply([])
		}).as('questionVersions')
		cy.intercept('DELETE', '**/structureddiary/api/v1/questions/17', (request) => {
			questionDeleted = true
			request.reply({
				id: 17,
				chain_id: 17,
				diary_id: 5,
				diary_question_order: 17,
				created_at: 1713500000,
				label: 'Mood',
				display_text: 'How do you feel today?',
				type: 'text',
				minimum: null,
				maximum: null,
				choices: null,
				active: false,
				template_text: 'Write a short note',
				previous_version_id: null,
				next_version_id: 18,
			})
		}).as('deleteQuestion')
		cy.loginToNextcloud()
		cy.visitStructuredDiary('questions/5/17')
		cy.contains('Mood').should('be.visible')
		cy.wait('@questionAnswerCountEmpty')
		cy.contains('button', 'Delete question').click()
		cy.wait('@deleteQuestion')
		cy.location('pathname').should('include', '/apps/structureddiary/diaries/5')
		cy.then(() => {
			expect(versionsRequestedAfterDelete).to.equal(false)
		})
	})

	it('hides question deletion when a single-version question already has answers', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.intercept('GET', '**/structureddiary/api/v1/questions/17/answer-count', { count: 1 }).as('questionAnswerCountPresent')
		cy.intercept('GET', '**/structureddiary/api/v1/questions/17/versions', [{
			id: 17,
			chain_id: 17,
			diary_id: 5,
			diary_question_order: 17,
			created_at: 1713500000,
			label: 'Mood',
			display_text: 'How do you feel today?',
			type: 'text',
			minimum: null,
			maximum: null,
			choices: null,
			active: true,
			template_text: 'Write a short note',
			previous_version_id: null,
			next_version_id: null,
		}]).as('singleQuestionVersion')
		cy.loginToNextcloud()
		cy.visitStructuredDiary('questions/5/17')
		cy.wait('@questionAnswerCountPresent')
		cy.wait('@singleQuestionVersion')
		cy.contains('button', 'Delete question').should('not.exist')
		cy.contains('button', 'Edit question').should('be.visible')
	})

	it('creates multiple question versions when an existing question changes repeatedly', () => {
		cy.mockStructuredDiaryBootstrap()

		const firstQuestion = {
			id: 17,
			chain_id: 17,
			diary_id: 5,
			diary_question_order: 17,
			created_at: 1713500000,
			label: 'Mood',
			display_text: 'How do you feel today?',
			type: 'text',
			minimum: null,
			maximum: null,
			choices: null,
			active: true,
			template_text: 'Write a short note',
			previous_version_id: null,
			next_version_id: null,
		}
		let currentQuestion = firstQuestion
		const versions = [firstQuestion]

		cy.intercept('GET', '**/structureddiary/api/v1/questions/17', (request) => {
			request.reply(firstQuestion)
		}).as('question17')
		cy.intercept('GET', '**/structureddiary/api/v1/questions/18', (request) => {
			request.reply(currentQuestion)
		}).as('question18')
		cy.intercept('GET', '**/structureddiary/api/v1/questions/19', (request) => {
			request.reply(currentQuestion)
		}).as('question19')
		cy.intercept('PUT', '**/structureddiary/api/v1/questions/17', (request) => {
			versions[0] = { ...versions[0], next_version_id: 18 }
			currentQuestion = {
				...firstQuestion,
				id: 18,
				created_at: 1713520000,
				label: request.body.label,
				display_text: request.body.displayText,
				template_text: request.body.templateText,
				previous_version_id: 17,
				next_version_id: null,
			}
			versions.push(currentQuestion)
			request.reply(currentQuestion)
		}).as('updateQuestionFirst')
		cy.intercept('PUT', '**/structureddiary/api/v1/questions/18', (request) => {
			versions[1] = { ...versions[1], next_version_id: 19 }
			currentQuestion = {
				...firstQuestion,
				id: 19,
				created_at: 1713530000,
				label: request.body.label,
				display_text: request.body.displayText,
				template_text: request.body.templateText,
				previous_version_id: 18,
				next_version_id: null,
			}
			versions.push(currentQuestion)
			request.reply(currentQuestion)
		}).as('updateQuestionSecond')
		cy.intercept('GET', '**/structureddiary/api/v1/questions/*/versions', (request) => {
			request.reply(versions)
		}).as('questionVersionsLive')

		cy.loginToNextcloud()
		cy.visitStructuredDiary('questions/5/17/edit')
		cy.get('[aria-label="Create new question"]').should('not.exist')
		cy.get('[aria-label="Save question"]').should('be.visible')
		cy.contains('Label').parent().find('input').first().clear().type('Mood v2')
		setMarkdownEditorValue('How is your mood now?')
		setMarkdownEditorValue('Write a longer note', 1)
		cy.get('[aria-label="Save question"]').first().click()
		cy.wait('@updateQuestionFirst').its('request.body').should('deep.include', {
			questionId: 17,
			chainId: 17,
			label: 'Mood v2',
			displayText: 'How is your mood now?',
			templateText: 'Write a longer note',
		})
		cy.wait('@questionVersionsLive')

		cy.contains('button', 'Edit question').click()
		cy.contains('Label').parent().find('input').first().clear().type('Mood v3')
		setMarkdownEditorValue('How is your mood this evening?')
		cy.contains('button', 'Save question').click()
		cy.wait('@updateQuestionSecond').its('request.body').should('deep.include', {
			questionId: 18,
			chainId: 17,
			label: 'Mood v3',
			displayText: 'How is your mood this evening?',
		})
		cy.wait('@questionVersionsLive')

		cy.contains('Mood v3').should('be.visible')
		cy.contains('Mood v2').should('be.visible')
		cy.contains('Mood').should('be.visible')
	})

	it('asks before deleting a diary', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.loginToNextcloud()
		cy.visitStructuredDiary('diaries/5/edit')
		cy.wait('@stats')

		cy.contains('button', /^Delete diary/).click()
		cy.contains('[role="dialog"]', 'Delete this diary').should('be.visible')
		cy.contains('[role="dialog"]', '1 entry').should('be.visible')
		cy.contains('[role="dialog"] button', 'Delete diary').click()

		cy.wait('@deleteDiary')
		cy.location('pathname').should('include', '/apps/structureddiary/diaries')
	})

	it('keeps the diary open when ownership moves away but the backend keeps the creator as manager', () => {
		cy.mockStructuredDiaryBootstrap()

		const transferredDiary = {
			...diary,
			user_id: 'bob',
			access_level: 9,
			is_owner: false,
		}
		let saved = false
		const createdShares: Array<{ sharedWith: string, permission: number }> = []

		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5', (request) => {
			request.reply(saved ? transferredDiary : diary)
		}).as('diaryDetailOwned')
		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/stats*', emptyStats).as('emptyStats')
		cy.intercept('GET', '**/structureddiary/api/v1/diaries', (request) => {
			request.reply(saved ? [transferredDiary] : [diary])
		}).as('diariesAfterOwnershipChange')
		cy.intercept('GET', '**/structureddiary/api/v1/diary-shares*', (request) => {
			request.reply(saved ? [{ id: 41, diary_id: 5, shared_with: currentUser, permission: 9 }] : [])
		}).as('allSharesAfterOwnershipChange')
		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/shares*', (request) => {
			request.reply(saved ? [{ id: 41, diary_id: 5, shared_with: currentUser, permission: 9 }] : [])
		}).as('sharesAfterOwnershipChange')
		cy.intercept('GET', '**/apps/files_sharing/api/v1/sharees*', {
			ocs: {
				data: {
					exact: { users: [] },
					users: [
						{ label: 'Bob', value: { shareWith: 'bob' } },
						{ label: 'Carol', value: { shareWith: 'carol' } },
					],
				},
			},
		}).as('shareesSearch')
		cy.intercept('POST', '**/structureddiary/api/v1/diaries/5/shares', (request) => {
			createdShares.push({
				sharedWith: request.body.sharedWith,
				permission: request.body.permission,
			})
			request.reply({
				id: 42,
				diary_id: 5,
				shared_with: request.body.sharedWith,
				permission: request.body.permission,
			})
		}).as('createShareAfterOwnershipChange')
		cy.intercept('PUT', '**/structureddiary/api/v1/diaries/5', (request) => {
			saved = true
			request.reply({
				...transferredDiary,
				title: request.body.title,
				description: request.body.description,
			})
		}).as('transferDiary')

		cy.loginToNextcloud()
		cy.visitStructuredDiary('diaries/5/edit')
		cy.wait('@emptyStats')
		selectUserFromField('Owner', 'bob', 'Bob')
		selectUser('Select readers', 'carol', 'Carol')
		cy.contains('button', 'Save diary').click()

		cy.wait('@transferDiary').its('request.body').should('deep.include', {
			ownerUserId: 'bob',
		})
		cy.wait('@createShareAfterOwnershipChange')
		cy.wait('@createShareAfterOwnershipChange')
		cy.then(() => {
			expect(createdShares).to.deep.include({
				sharedWith: 'carol',
				permission: 1,
			})
			expect(createdShares).to.deep.include({
				sharedWith: currentUser,
				permission: 9,
			})
		})
		cy.location('pathname').should('include', '/apps/structureddiary/diaries/5')
		cy.location('pathname').should('not.include', '/edit')
	})

	it('returns to the diary overview when the backend reports the edited diary is no longer visible', () => {
		cy.mockStructuredDiaryBootstrap()

		let saved = false
		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/stats*', emptyStats).as('emptyStats')
		cy.intercept('GET', '**/structureddiary/api/v1/diaries', (request) => {
			request.reply(saved ? [] : [diary])
		}).as('diariesAfterVisibilityLoss')
		cy.intercept('GET', '**/structureddiary/api/v1/diary-shares*', []).as('allSharesAfterVisibilityLoss')
		cy.intercept('GET', '**/apps/files_sharing/api/v1/sharees*', {
			ocs: {
				data: {
					exact: { users: [] },
					users: [{ label: 'Bob', value: { shareWith: 'bob' } }],
				},
			},
		}).as('shareesSearch')
		cy.intercept('PUT', '**/structureddiary/api/v1/diaries/5', (request) => {
			saved = true
			request.reply({
				...diary,
				user_id: 'bob',
				is_owner: false,
				access_level: 0,
				title: request.body.title,
				description: request.body.description,
			})
		}).as('saveInvisibleDiary')

		cy.loginToNextcloud()
		cy.visitStructuredDiary('diaries/5/edit')
		cy.wait('@emptyStats')
		selectUserFromField('Owner', 'bob', 'Bob')
		cy.contains('button', 'Save diary').click()

		cy.wait('@saveInvisibleDiary')
		cy.location('pathname').should('match', /\/apps\/structureddiary\/diaries\/?$/)
	})

	it('creates analyze shares with read and analyze permissions', () => {
		cy.mockStructuredDiaryBootstrap()

		const createdShares: Array<{ sharedWith: string, permission: number }> = []
		cy.intercept('GET', '**/apps/files_sharing/api/v1/sharees*', {
			ocs: {
				data: {
					exact: { users: [] },
					users: [{ label: 'Carol', value: { shareWith: 'carol' } }],
				},
			},
		}).as('shareesSearch')
		cy.intercept('POST', '**/structureddiary/api/v1/diaries/5/shares', (request) => {
			createdShares.push({
				sharedWith: request.body.sharedWith,
				permission: request.body.permission,
			})
			request.reply({
				id: 71,
				diary_id: 5,
				shared_with: request.body.sharedWith,
				permission: request.body.permission,
			})
		}).as('createAnalyzeShare')

		cy.loginToNextcloud()
		cy.visitStructuredDiary('diaries/5/edit')
		selectUser('Select users allowed to analyze', 'carol', 'Carol')
		cy.contains('button', 'Save diary').click()

		cy.wait('@createAnalyzeShare')
		cy.then(() => {
			expect(createdShares).to.deep.include({
				sharedWith: 'carol',
				permission: 5,
			})
		})
	})

	it('does not request or render statistics without analyze permission', () => {
		cy.mockStructuredDiaryBootstrap()

		let statsCalls = 0
		const readableDiary = {
			...diary,
			user_id: 'alice',
			access_level: 1,
			is_owner: false,
		}
		cy.intercept('GET', '**/structureddiary/api/v1/diaries', [readableDiary]).as('readableDiaries')
		cy.intercept('GET', '**/structureddiary/api/v1/diary-shares*', [
			{ id: 81, diary_id: 5, shared_with: currentUser, permission: 1 },
		]).as('readableShares')
		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5', readableDiary).as('readableDiaryDetail')
		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/stats*', (request) => {
			statsCalls += 1
			request.reply({ statusCode: 403, body: { error: 'Diary not analyzable' } })
		}).as('forbiddenStats')

		cy.loginToNextcloud()
		cy.visitStructuredDiary('diaries/5')
		cy.wait('@readableDiaryDetail')
		cy.wait(100)

		cy.contains('Statistics').should('not.exist')
		cy.then(() => {
			expect(statsCalls).to.equal(0)
		})
	})

	it('removes another user share without leaving the current diary', () => {
		cy.mockStructuredDiaryBootstrap()

		let saved = false
		const shares = [
			{ id: 51, diary_id: 5, shared_with: currentUser, permission: 9 },
			{ id: 52, diary_id: 5, shared_with: 'bob', permission: 3 },
		]

		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5', diary).as('diaryDetailOwned')
		cy.intercept('GET', '**/structureddiary/api/v1/diaries', [diary]).as('diariesAfterShareRemoval')
		cy.intercept('GET', '**/structureddiary/api/v1/diary-shares*', (request) => {
			request.reply(saved ? [shares[0]] : shares)
		}).as('allSharesAfterShareRemoval')
		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/shares*', (request) => {
			request.reply(saved ? [shares[0]] : shares)
		}).as('sharesAfterShareRemoval')
		cy.intercept('PUT', '**/structureddiary/api/v1/diaries/5/shares/51', shares[0]).as('keepCurrentManager')
		cy.intercept('DELETE', '**/structureddiary/api/v1/diaries/5/shares/52', (request) => {
			saved = true
			request.reply(shares[1])
		}).as('deleteOtherShare')

		cy.loginToNextcloud()
		cy.visitStructuredDiary('diaries/5/edit')
		removeSelectedUserFromField('Writers', 'bob')
		removeSelectedUserFromField('Readers', 'bob')
		cy.contains('button', 'Save diary').click()

		cy.wait('@deleteOtherShare')
		cy.location('pathname').should('include', '/apps/structureddiary/diaries/5')
		cy.location('pathname').should('not.include', '/edit')
	})

	it('keeps the current user manager share even if it is removed in the form', () => {
		cy.mockStructuredDiaryBootstrap()

		let ownManagerDeleteCalls = 0
		const currentManagerShare = { id: 61, diary_id: 5, shared_with: currentUser, permission: 9 }

		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5', { ...diary, user_id: 'alice', is_owner: false, access_level: 9 }).as('sharedDiaryDetail')
		cy.intercept('GET', '**/structureddiary/api/v1/diaries', [{ ...diary, user_id: 'alice', is_owner: false, access_level: 9 }]).as('sharedDiaries')
		cy.intercept('GET', '**/structureddiary/api/v1/diary-shares*', [currentManagerShare]).as('sharedAllShares')
		cy.intercept('GET', '**/structureddiary/api/v1/diaries/5/shares*', [currentManagerShare]).as('sharedDiaryShares')
		cy.intercept('PUT', '**/structureddiary/api/v1/diaries/5/shares/61', currentManagerShare).as('restoreCurrentManager')
		cy.intercept('DELETE', '**/structureddiary/api/v1/diaries/5/shares/61', (request) => {
			ownManagerDeleteCalls += 1
			request.reply(currentManagerShare)
		}).as('deleteCurrentManager')

		cy.loginToNextcloud()
		cy.visitStructuredDiary('diaries/5/edit')
		removeSelectedUserFromField('Managers', currentUser)
		cy.contains('button', 'Save diary').click()

		cy.wait('@restoreCurrentManager')
		cy.then(() => {
			expect(ownManagerDeleteCalls).to.equal(0)
		})
		cy.location('pathname').should('include', '/apps/structureddiary/diaries/5')
		cy.location('pathname').should('not.include', '/edit')
	})
})
