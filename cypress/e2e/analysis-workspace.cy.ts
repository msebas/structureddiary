describe('Analysis workspace flow', () => {
	it('creates a started job through the Nextcloud API', () => {
		let createdJob: Record<string, unknown> | null = null
		const job = {
			id: 77,
			diary_id: 5,
			created_by: 'alice',
			created_at: 1713520000,
			updated_at: 1713520000,
			data_from: 1713000000,
			data_until: 1713520000,
			started_at: null,
			finished_at: null,
			title: 'Analysis',
			language: 'de-DE',
			analysis_type: 'standard',
			status: 'DRAFT',
			progress: 0,
			output_types: ['JSON', 'HTML'],
			parameters: {
				includeTextAnalysis: true,
				movingAverageWindow: 11,
				showStandardDeviation: false,
			},
			storage_url: null,
			status_message: '',
			error_message: null,
			cancel_requested_at: null,
		}

		cy.mockStructuredDiaryBootstrap()
		cy.intercept('GET', '**/structureddiary/api/v1/jobs*', (request) => {
			request.reply(createdJob === null ? [] : [createdJob])
		}).as('analysisJobs')
		cy.intercept('POST', '**/structureddiary/api/v1/jobs', (request) => {
			expect(request.body.start).to.eq(true)
			expect(request.body.outputFormats).to.deep.eq(['JSON', 'HTML'])
			createdJob = { ...job, ...request.body, id: 78, status: 'SUBMITTED' }
			request.reply(createdJob)
		}).as('createAnalysis')
		cy.loginToNextcloud()
		cy.visitStructuredDiary('analyses/5')
		cy.wait('@analysisJobs')
		cy.contains('No analyses yet.').should('be.visible')
		cy.contains('New analysis').click()
		cy.contains('Create and start').click()
		cy.wait('@createAnalysis')
		cy.contains('Submitted').should('be.visible')
	})
})
