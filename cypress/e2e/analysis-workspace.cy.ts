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

	it('loads an HTML report through the integrated view within a phone viewport', () => {
		const job = {
			id: 77,
			diary_id: 5,
			created_by: 'alice',
			created_at: 1713520000,
			updated_at: 1713520000,
			data_from: 1713000000,
			data_until: 1713520000,
			started_at: 1713520000,
			finished_at: 1713520300,
			title: 'Analysis report',
			language: 'en-GB',
			analysis_type: 'standard',
			status: 'COMPLETED',
			progress: 100,
			output_types: ['HTML'],
			parameters: { includeTextAnalysis: false, shifting_median_width: 11, plot_std_error: false, show_single_data_points: true },
			storage_url: null,
			status_message: '',
			error_message: null,
			cancel_requested_at: null,
		}

		cy.fixture('analysis-report/report.html').then((report) => {
			cy.fixture('analysis-report/questions/mood.html').then((mood) => {
				const artifacts = [
					{ id: 301, parent_id: null, job_id: 77, artifact_type: 'HTML', mime_type: 'text/html', file_name: 'report.html', file_path: 'report.html', file_id: 1001, size: report.length, checksum: null, created_at: 1713520300 },
					{ id: 302, parent_id: 301, job_id: 77, artifact_type: 'HTML', mime_type: 'text/html', file_name: 'mood.html', file_path: 'questions/mood.html', file_id: 1002, size: mood.length, checksum: null, created_at: 1713520300 },
					{ id: 304, parent_id: 302, job_id: 77, artifact_type: 'PLOT', mime_type: 'image/svg+xml', file_name: 'mood.svg', file_path: 'plots/mood.svg', file_id: 1004, size: 100, checksum: null, created_at: 1713520300 },
				]
				cy.mockStructuredDiaryBootstrap()
				cy.intercept('GET', '**/structureddiary/api/v1/jobs*', [job]).as('analysisJobs')
				cy.intercept('GET', '**/structureddiary/api/v1/jobs/77/artifacts', artifacts).as('artifacts')
				cy.intercept('GET', '**/structureddiary/api/v1/jobs/77/artifacts/301/integrated-view', report).as('report')

				cy.loginToNextcloud()
				cy.visitStructuredDiary('analyses/5/77')
				cy.wait('@analysisJobs')
				cy.wait('@artifacts')
				cy.wait('@report')
				cy.get('iframe').should('have.attr', 'src').and('match', /\/jobs\/77\/artifacts\/301\/integrated-view/)
				cy.viewport(390, 740)
				cy.window().then((win) => win.dispatchEvent(new Event('resize')))
				cy.window().its('innerWidth').should('equal', 390)
				cy.get('iframe').scrollIntoView().should(($frame) => {
					const bounds = $frame[0].getBoundingClientRect()
					expect(bounds.left).to.be.at.least(0)
					expect(bounds.right).to.be.at.most(390)
				})
			})
		})
	})
})
