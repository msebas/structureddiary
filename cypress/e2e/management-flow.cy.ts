describe('Structured diary management flow', () => {
	it('creates a diary from the workspace header', () => {
		cy.mockStructuredDiaryBootstrap()
		cy.loginToNextcloud()
		cy.visitStructuredDiary('diaries/5')
		cy.get('[aria-label="Create new diary"]').first().click()
		cy.contains('Create diary').should('be.visible')
		cy.contains('Title').parent().find('input').type('Copied journal')
		cy.contains('Description').parent().find('textarea').type('Draft description')
		cy.contains('button', 'Create diary').click()

		cy.wait('@createDiary').its('request.body').should('deep.include', {
			title: 'Copied journal',
			description: 'Draft description',
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
		cy.get('.CodeMirror').first().then(($editor) => {
			const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
			editor.setValue('How much energy did you have?')
			editor.save()
		})
		cy.get('textarea').last().type('0 to 10')
		cy.contains('Save question').click()

		cy.wait('@createQuestion').its('request.body').should('deep.include', {
			label: 'Energy',
			displayText: 'How much energy did you have?',
			type: 'text',
			active: true,
			templateText: '0 to 10',
		})
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
		cy.contains('Label').parent().find('input').first().clear().type('Mood v2')
		cy.get('.CodeMirror').first().then(($editor) => {
			const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
			editor.setValue('How is your mood now?')
			editor.save()
		})
		cy.get('textarea').last().clear().type('Write a longer note')
		cy.contains('button', 'Save question').click()
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
		cy.get('.CodeMirror').first().then(($editor) => {
			const editor = ($editor[0] as unknown as { CodeMirror: { setValue: (value: string) => void, save: () => void } }).CodeMirror
			editor.setValue('How is your mood this evening?')
			editor.save()
		})
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
})
