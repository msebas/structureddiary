import { computed, defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter, RouterView, useRouter } from 'vue-router'
import AnalysisCreateView from '@/components/analysis/AnalysisCreateView.vue'
import AnalysisDetailView from '@/components/analysis/AnalysisDetailView.vue'
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
		cy.mount(Wrapper, { global: { plugins: [router] } })
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
				movingAverageWindow: 11,
				showStandardDeviation: false,
			})
			request.reply({ ...completedJob, id: 44, status: 'READY_QUEUE', title: request.body.title })
		}).as('createAnalysis')

		mountWithRoutes('/analyses/5/new', AnalysisCreateView)
		cy.contains('Create and start').should('not.be.disabled')
		cy.get('input[type="text"]').clear().type('Weekly mood')
		cy.contains('Create and start').click()
		cy.wait('@createAnalysis')
		cy.get('[data-cy="route-name"]').should('contain', 'analysis')
	})

	it('polls changed jobs with an ISO changedSince value', () => {
		cy.clock(new Date('2026-07-03T10:00:00.000Z'))
		let requestCount = 0
		cy.intercept('GET', '**/structureddiary/api/v1/jobs*', (request) => {
			requestCount += 1
			if (requestCount > 1) {
				expect(new URL(request.url).searchParams.get('changedSince')).to.match(/^2026-07-03T10:00:00\.000Z$/)
			}
			request.reply([{ ...completedJob, status: 'RUNNING', progress: requestCount > 1 ? 55 : 40 }])
		}).as('analysisJobs')

		mountWithRoutes('/analyses/5', AnalysisListPanel)
		cy.wait('@analysisJobs')
		cy.contains('Weekly analysis').should('be.visible')
		cy.get('button[aria-label="Polling rate"]').click()
		cy.get('select').select('1')
		cy.tick(1000)
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

	it('previews HTML artifacts through a sandboxed file-id link without rendering filenames as HTML', () => {
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
		cy.get('iframe').should('have.attr', 'src').and('match', /\/f\/1001/)
		cy.get('a[href*="/artifacts/download"]').its('length').should('be.gte', 2)
		cy.contains('<img src=x onerror=alert(1)>report.html').should('exist')
		cy.get('img[src="x"]').should('not.exist')
		cy.get('body').should('not.contain', '/must/not/be/used')
	})
})
