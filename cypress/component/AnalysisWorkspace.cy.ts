import { computed, defineComponent, h } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter, RouterView, useRouter } from 'vue-router'
import AnalysisCreateView from '@/components/analysis/AnalysisCreateView.vue'
import AnalysisDetailView from '@/components/analysis/AnalysisDetailView.vue'
import HeaderAnalyses from '@/components/layout/HeaderAnalyses.vue'
import AnalysisListPanel from '@/components/layout/AnalysisListPanel.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { AnalysisJob } from '@/types/types'

const diary = {
	id: 5,
	user_id: 'alice',
	title: 'Health journal',
	description: '',
	reminder_active: false,
	reminder_time: 0,
	reminder_count: 3,
	reminder_delay: 2700,
	reminder_signal_first: '',
	reminder_signal_repeat: '',
	entry_schedule: 86400,
	access_level: 15,
	is_owner: true,
}

const completedJob: AnalysisJob = {
	id: 21,
	diary_id: 5,
	created_by: 'alice',
	created_at: 1713520000,
	updated_at: 1713520300,
	data_from: 1713000000,
	data_until: 1713520000,
	started_at: 1713520010,
	finished_at: 1713520300,
	title: 'Weekly analysis',
	language: 'de-DE',
	analysis_type: 'standard',
	status: 'COMPLETED',
	progress: 100,
	output_types: ['JSON', 'HTML'],
	parameters: {
		includeTextAnalysis: true,
		movingAverageWindow: 11,
		showStandardDeviation: false,
	},
	storage_url: '/index.php/apps/files/?dir=/StructuredDiary/Analyses/weekly',
	status_message: '',
	error_message: null,
	cancel_requested_at: null,
}

function mountWithRoutes(path: string, component: object) {
	const pinia = createPinia()
	setActivePinia(pinia)
	const router = createRouter({
		history: createMemoryHistory(),
		routes: [
			{ path: '/analyses/:diaryId(\\d+)', name: 'analyses', component },
			{ path: '/analyses/:diaryId(\\d+)/new', name: 'analysisCreate', component },
			{ path: '/analyses/:diaryId(\\d+)/:jobId(\\d+)', name: 'analysis', component },
		],
	})
	const ready = router.push(path)

	const Wrapper = defineComponent({
		setup() {
			const store = useStructuredDiaryStore()
			const currentRouter = useRouter()
			store.diaries = { 5: diary }
			store.diaryStatsById = {
				5: {
					question_count: 1,
					entry_count: 1,
					answer_count: 1,
					average_answer_count: 1,
					first_entry_at: 1713000000,
					latest_entry_at: 1713520000,
					entry_frequency: { mean: 86400, stddev: 0 },
					entry_frequency_last_month: { mean: 86400, stddev: 0 },
					gap_count_above_ten_target_intervals: 0,
					last_large_gap: null,
					longest_gap: null,
					average_entry_duration: 600,
					average_entry_duration_last_month: 600,
					latest_answer_at: 1713520000,
				},
			}
			const routeName = computed(() => String(currentRouter.currentRoute.value.name ?? ''))
			return () => h('div', [
				h(RouterView),
				h('div', { 'data-cy': 'route-name' }, routeName.value),
			])
		},
	})

	return cy.wrap(ready).then(() => router.isReady()).then(() => {
		cy.mount(Wrapper, { global: { plugins: [pinia, router] } })
	})
}

describe('Analysis workspace', () => {
	it('creates a started analysis with validated dates and output formats', () => {
		cy.intercept('POST', '**/structureddiary/api/v1/jobs', (request) => {
			expect(request.body.diaryId).to.eq(5)
			expect(request.body.title).to.eq('Weekly mood')
			expect(request.body.start).to.eq(true)
			expect(request.body.outputFormats).to.deep.eq(['JSON', 'HTML'])
			expect(request.body.parameters).to.include({
				includeTextAnalysis: true,
				shifting_median_width: 11,
				plot_std_error: false,
				show_single_data_points: true,
			})
			request.reply({ ...completedJob, id: 44, status: 'SUBMITTED', title: request.body.title })
		}).as('createAnalysis')

		mountWithRoutes('/analyses/5/new', AnalysisCreateView)
		cy.contains('Create and start').should('not.be.disabled')
		cy.get('input[type="text"]').clear().type('Weekly mood')
		cy.contains('Create and start').click()
		cy.wait('@createAnalysis')
		cy.get('[data-cy="route-name"]').should('contain', 'analysis')
	})

	it('copies the selected analysis into the create form and sets Until to today', () => {
		const sourceJob: AnalysisJob = {
			...completedJob,
			title: 'Copied analysis',
			data_from: 1704067200,
			data_until: 1706745599,
			language: 'en-GB',
			analysis_type: 'custom',
			llm_url: 'https://llm.example/v1/chat/completions',
			output_types: ['PDF', 'XLSX'],
			parameters: {
				includeTextAnalysis: false,
				shifting_median_width: 9,
				plot_std_error: true,
				show_single_data_points: false,
			},
		}
		const copySettings = { ...sourceJob, llm_header: '{"Authorization":"Bearer secret"}' }
		cy.intercept('GET', '**/structureddiary/api/v1/jobs/21/copy-settings', copySettings).as('sourceJob')
		cy.intercept('POST', '**/structureddiary/api/v1/jobs', (request) => {
			expect(request.body).to.include({
				diaryId: 5,
				title: sourceJob.title,
				language: sourceJob.language,
				analysisType: sourceJob.analysis_type,
				llmUrl: sourceJob.llm_url,
				llmHeader: copySettings.llm_header,
				start: true,
			})
			expect(request.body.fromTimestamp).to.eq(sourceJob.data_from)
			expect(request.body.untilTimestamp).to.be.greaterThan(sourceJob.data_until)
			expect(request.body.outputFormats).to.deep.eq(sourceJob.output_types)
			expect(request.body.parameters).to.deep.eq(sourceJob.parameters)
			request.reply({ ...sourceJob, id: 44, status: 'SUBMITTED' })
		}).as('copyAnalysis')

		mountWithRoutes('/analyses/5/new?sourceJobId=21', AnalysisCreateView)
		cy.wait('@sourceJob')
		cy.get('input[type="text"]').should('have.value', sourceJob.title)
		cy.get('input[type="date"]').first().should('have.value', '2024-01-01')
		cy.get('input[type="date"]').last().should('have.value', new Date().toISOString().slice(0, 10))
		cy.contains('Create and start').click()
		cy.wait('@copyAnalysis')
	})

	it('replaces Create analysis with Copy analysis only when an analysis is selected', () => {
		cy.intercept('GET', '**/structureddiary/api/v1/jobs*', [completedJob]).as('selectedJob')
		mountWithRoutes('/analyses/5/21', HeaderAnalyses)
		cy.wait('@selectedJob')
		cy.contains('Copy analysis').should('be.visible').click()
		cy.get('[data-cy="route-name"]').should('contain', 'analysisCreate')

		cy.intercept('GET', '**/structureddiary/api/v1/jobs*', []).as('noSelectedJob')
		mountWithRoutes('/analyses/5', HeaderAnalyses)
		cy.contains('Create analysis').should('not.exist')
		cy.contains('Copy analysis').should('not.exist')
	})

	it('refreshes jobs through the analysis jobs API path', () => {
		let requestCount = 0
		cy.intercept('GET', '**/structureddiary/api/v1/jobs*', (request) => {
			requestCount += 1
			expect(new URL(request.url).pathname).to.contain('/api/v1/jobs')
			request.reply([{ ...completedJob, status: 'RUNNING', progress: requestCount > 1 ? 55 : 40 }])
		}).as('analysisJobs')

		mountWithRoutes('/analyses/5', AnalysisListPanel)
		cy.wait('@analysisJobs')
		cy.contains('Weekly analysis').should('be.visible')
		cy.get('button[aria-label="Refresh analyses"]').click()
		cy.wait('@analysisJobs')
		cy.contains('55%').should('be.visible')
	})

	it('refreshes the selected analysis detail when the job list is reloaded', () => {
		const runningJob: AnalysisJob = {
			...completedJob,
			status: 'RUNNING',
			progress: 35,
			finished_at: null,
			storage_url: null,
			status_message: 'Preparing report',
		}

		cy.intercept('GET', '**/structureddiary/api/v1/jobs*', [runningJob]).as('analysisJobs')
		cy.intercept('GET', '**/structureddiary/api/v1/jobs/21/artifacts', []).as('artifacts')

		mountWithRoutes('/analyses/5/21', AnalysisDetailView)
		cy.wait('@analysisJobs')
		cy.contains('Preparing report').should('be.visible')

		cy.document().then((document) => {
			document.dispatchEvent(new CustomEvent('structured-diary-analysis-jobs-refreshed', {
				detail: {
					jobs: [{
						...completedJob,
						status_message: 'Report is ready',
					}],
				},
			}))
		})

		cy.contains('Report is ready').should('be.visible')
		cy.contains('Preparing report').should('not.exist')
		cy.contains('Open in Nextcloud Files').should('be.visible')
		cy.wait('@artifacts')
	})

	it('loads HTML through the backend integrated report view and downloads individual artifacts directly', () => {
		cy.intercept('GET', '**/structureddiary/api/v1/jobs*', [completedJob]).as('analysisJobs')
		cy.intercept('GET', '**/structureddiary/api/v1/jobs/21/artifacts', [
			{
				id: 301,
				parent_id: null,
				job_id: 21,
				artifact_type: 'HTML',
				mime_type: 'text/html',
				file_name: '<img src=x onerror=alert(1)>report.html',
				file_path: '/must/not/be/used/report.html',
				file_id: 1001,
				size: 2048,
				checksum: null,
				created_at: 1713520300,
			},
		]).as('artifacts')
		mountWithRoutes('/analyses/5/21', AnalysisDetailView)
		cy.wait('@analysisJobs')
		cy.wait('@artifacts')
		cy.get('iframe')
			.should('have.attr', 'sandbox')
			.and('contain', 'allow-same-origin')
			.and('not.contain', 'allow-scripts')
		cy.get('iframe').should('have.attr', 'src').and('match', /\/jobs\/21\/artifacts\/301\/integrated-view/)
		cy.get('button[aria-label="Show artifact list"]').click()
		cy.get('a[href*="/artifacts/301/content?download=1"]').should('exist')
		cy.contains('<img src=x onerror=alert(1)>report.html').should('exist')
		cy.get('img[src="x"]').should('not.exist')
		cy.get('body').should('not.contain', '/must/not/be/used')
	})

	it('previews PDF artifacts from a blob URL instead of embedding an OCS response', () => {
		cy.intercept('GET', '**/structureddiary/api/v1/jobs*', [completedJob]).as('analysisJobs')
		cy.intercept('GET', '**/structureddiary/api/v1/jobs/21/artifacts', [
			{
				id: 302,
				parent_id: null,
				job_id: 21,
				artifact_type: 'PDF',
				mime_type: 'application/pdf',
				file_name: 'report.pdf',
				file_path: 'report.pdf',
				file_id: 1002,
				size: 2048,
				checksum: null,
				created_at: 1713520300,
			},
		]).as('artifacts')
		cy.intercept('GET', '**/structureddiary/api/v1/jobs/21/artifacts/302/content', {
			statusCode: 200,
			headers: { 'content-type': 'application/pdf' },
			body: 'pdf-content',
		}).as('pdfPreview')

		mountWithRoutes('/analyses/5/21', AnalysisDetailView)
		cy.wait('@analysisJobs')
		cy.wait('@artifacts')
		cy.wait('@pdfPreview')
		cy.get('object[type="application/pdf"]').should('have.attr', 'data').and('match', /^blob:/)
	})

	it('uses the integrated view URL for a multi-page HTML report', () => {
		const artifacts = [
			{ id: 301, parent_id: null, job_id: 21, artifact_type: 'HTML', mime_type: 'text/html', file_name: 'report.html', file_path: 'report.html', file_id: 1001, size: 100, checksum: null, created_at: 1713520300 },
			{ id: 302, parent_id: 301, job_id: 21, artifact_type: 'HTML', mime_type: 'text/html', file_name: 'mood.html', file_path: 'questions/mood.html', file_id: 1002, size: 100, checksum: null, created_at: 1713520300 },
			{ id: 303, parent_id: 301, job_id: 21, artifact_type: 'HTML', mime_type: 'text/html', file_name: 'energy.html', file_path: 'questions/energy.html', file_id: 1003, size: 100, checksum: null, created_at: 1713520300 },
			{ id: 304, parent_id: 302, job_id: 21, artifact_type: 'PLOT', mime_type: 'image/svg+xml', file_name: 'mood.svg', file_path: 'plots/mood.svg', file_id: 1004, size: 100, checksum: null, created_at: 1713520300 },
			{ id: 305, parent_id: 303, job_id: 21, artifact_type: 'PLOT', mime_type: 'image/svg+xml', file_name: 'energy.svg', file_path: 'plots/energy.svg', file_id: 1005, size: 100, checksum: null, created_at: 1713520300 },
		]
		cy.intercept('GET', '**/structureddiary/api/v1/jobs*', [completedJob]).as('analysisJobs')
		cy.intercept('GET', '**/structureddiary/api/v1/jobs/21/artifacts', artifacts).as('artifacts')

		mountWithRoutes('/analyses/5/21', AnalysisDetailView)
		cy.wait('@analysisJobs')
		cy.wait('@artifacts')
		cy.get('iframe').should('have.attr', 'src').and('match', /\/jobs\/21\/artifacts\/301\/integrated-view/)
	})
})
