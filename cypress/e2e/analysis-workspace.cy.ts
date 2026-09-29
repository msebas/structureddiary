import type { AnalysisArtifact, AnalysisJob } from '@/types/types'

interface ServiceJob extends AnalysisJob {
	uuid: string
	job_token: string
}

function serviceRequest<T>(method: 'GET' | 'POST' | 'PATCH', path: string, job?: ServiceJob, body?: unknown, apiToken = ''): Cypress.Chainable<T> {
	const origin = new URL(String(Cypress.config('baseUrl'))).origin
	return cy.request({
		method,
		url: `${origin}/ocs/v2.php/apps/structureddiary/api/v1/service/jobs/${path}`,
		headers: {
			Accept: 'application/json',
			'Content-Type': 'application/json',
			'OCS-APIRequest': 'true',
			...(job ? {
				'x-structureddiary-job-token': job.job_token,
				'x-structureddiary-job-uuid': job.uuid,
			} : { 'x-structureddiary-nextcloud-api-token': apiToken }),
		},
		body: body as Cypress.RequestBody,
	}).then(({ body: responseBody }) => responseBody.ocs?.data ?? responseBody)
}

describe('Analysis workspace flow', () => {
	it('creates a started job through the real Nextcloud API', () => {
		cy.seedStructuredDiary().then(({ diary }) => {
			cy.intercept('POST', '**/structureddiary/api/v1/jobs').as('createAnalysis')
			cy.loginToNextcloud()
			cy.visitStructuredDiary(`analyses/${diary.id}`)
			cy.contains('No analyses yet.').should('be.visible')
			cy.contains('New analysis').click()
			cy.contains('Create and start').click()
			cy.wait('@createAnalysis').its('request.body').should('deep.include', {
				start: true,
				outputFormats: ['JSON', 'HTML'],
			})
			cy.contains('Submitted').should('be.visible')
			cy.apiRequest<AnalysisJob[]>('GET', 'jobs').then((jobs) => {
				expect(jobs).to.have.length(1)
				expect(jobs[0].status).to.equal('SUBMITTED')
			})
		})
	})

	it('loads an API-created HTML report through the integrated phone view', () => {
		cy.seedStructuredDiary().then(({ diary }) => {
			const origin = new URL(String(Cypress.config('baseUrl'))).origin
			const username = String(Cypress.env('ncUsername') ?? 'admin')
			const password = String(Cypress.env('ncPassword') ?? 'admin')
			cy.request({
				method: 'POST',
				url: `${origin}/ocs/v2.php/apps/structureddiary/api/v1/admin/analysis-settings`,
				auth: { username, password },
				headers: { 'OCS-APIRequest': 'true', 'Content-Type': 'application/json' },
				body: {
					serviceUrl: 'http://127.0.0.1:9',
					serviceSecret: 'e2e-service-secret',
					outputBaseFolder: '/StructuredDiary/Analyses',
				},
				failOnStatusCode: false,
			})
			cy.task<{ stdout: string }>('runExec', {
				command: ['php', 'occ', 'config:app:get', 'structureddiary', 'analysis.nextcloud_api_token'],
			}).then(({ stdout }) => {
				const apiToken = stdout.trim()
				expect(apiToken, 'generated analysis API token').not.to.equal('')
				const now = Math.floor(Date.now() / 1000)
				cy.apiRequest<AnalysisJob>('POST', 'jobs', {
					diaryId: diary.id,
					fromTimestamp: now - 86400,
					untilTimestamp: now,
					title: 'Analysis report',
					language: 'en-GB',
					start: true,
					outputFormats: ['HTML'],
					parameters: { includeTextAnalysis: false, shifting_median_width: 11, plot_std_error: false },
				}).then((createdJob) => {
					serviceRequest<ServiceJob[]>('GET', 'poll', undefined, undefined, apiToken).then((jobs) => {
						const job = jobs.find(({ id }) => id === createdJob.id)
						expect(job, 'analysis service job').to.exist
						serviceRequest<AnalysisJob>('PATCH', `${job!.uuid}/status`, job, {
							status: 'WORKER_UPLOAD',
							progress: 90,
						}).then(() => {
							cy.fixture('analysis-report/report.html').then((report: string) => {
								serviceRequest<Array<{ id: number }>>('POST', `${job!.uuid}/artifacts/create`, job, {
									artifacts: [{
										id: 301,
										artifact_type: 'HTML',
										mime_type: 'text/html',
										file_name: 'report.html',
										file_path: 'report.html',
										size: report.length,
									}],
								}).then((artifacts) => {
									cy.request({
										method: 'POST',
										url: `${origin}/ocs/v2.php/apps/structureddiary/api/v1/service/jobs/${job!.uuid}/artifacts/upload/${artifacts[0].id}`,
										headers: {
											'Content-Type': 'text/html',
											'OCS-APIRequest': 'true',
											'x-structureddiary-job-token': job!.job_token,
											'x-structureddiary-job-uuid': job!.uuid,
										},
										body: report,
									})
									serviceRequest('PATCH', `${job!.uuid}/status`, job, { status: 'JOB_COMPLETED', progress: 100 })
									serviceRequest('POST', `${job!.uuid}/finalize`, job)
								})
							})
						})
					})

					cy.intercept('GET', `**/structureddiary/api/v1/jobs/${createdJob.id}/artifacts`).as('artifacts')
					cy.intercept('GET', `**/structureddiary/api/v1/jobs/${createdJob.id}/artifacts/*/integrated-view`).as('report')
					cy.loginToNextcloud()
					cy.visitStructuredDiary(`analyses/${diary.id}/${createdJob.id}`)
					cy.wait('@artifacts')
					cy.wait('@report')
					cy.get('iframe').should('have.attr', 'src').and('include', `/jobs/${createdJob.id}/artifacts/`)
					cy.viewport(390, 740)
					cy.window().then((win) => win.dispatchEvent(new Event('resize')))
					cy.get('iframe').scrollIntoView().should(($frame) => {
						const bounds = $frame[0].getBoundingClientRect()
						expect(bounds.left).to.be.at.least(0)
						expect(bounds.right).to.be.at.most(390)
					})
					cy.apiRequest<AnalysisArtifact[]>('GET', `jobs/${createdJob.id}/artifacts`).should('have.length', 1)
				})
			})
		})
	})
})
