<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiDownload, mdiFileTreeOutline, mdiFolderOpenOutline, mdiOpenInNew } from '@mdi/js'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { analysisService } from '@/services'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { AnalysisArtifact, AnalysisArtifactType, AnalysisJob } from '@/types/types'
import {
	analysisStatusLabel,
	analysisStatusTone,
	artifactDisplayType,
	artifactFileUrl,
	canCancelAnalysis,
	canDeleteAnalysis,
	canStartAnalysis,
	formatBytes,
} from '@/utils/analysis'
import { formatDate, formatDateTime } from '@/utils/format'
import { t } from '@nextcloud/l10n'

interface AnalysisJobsRefreshedEventDetail {
	jobs: AnalysisJob[]
}

const store = useStructuredDiaryStore()
const route = useRoute()
const router = useRouter()
const job = ref<AnalysisJob | null>(null)
const artifacts = ref<AnalysisArtifact[]>([])
const loading = ref(false)
const artifactsLoading = ref(false)
const artifactError = ref<string | null>(null)
const showArtifactList = ref(false)
const selectedType = ref<AnalysisArtifactType | null>(null)
const cancelDialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const previewFailed = ref(false)

const selectedJobId = computed(() => {
	const value = route.params.jobId
	return typeof value === 'string' ? Number.parseInt(value, 10) : null
})
const metadataOpen = computed(() => route.query.metadata === 'open')
const progressPercent = computed(() => Math.max(0, Math.min(100, Math.round(job.value?.progress ?? 0))))
const selectableArtifactTypes = computed(() => Array.from(new Set(artifacts.value
	.filter((artifact) => ['JSON', 'HTML', 'PDF', 'XLSX', 'MANIFEST', 'LOG', 'MARKDOWN', 'ERROR_LOG', 'ERROR_MARKDOWN'].includes(artifactDisplayType(artifact)))
	.map(artifactDisplayType))))
const selectedArtifacts = computed(() => artifacts.value.filter((artifact) => selectedType.value === null || artifactDisplayType(artifact) === selectedType.value))
const sortedArtifacts = computed(() => orderArtifactsForTree(artifacts.value))
const hasDownloadedArtifacts = computed(() => artifacts.value.some((artifact) => artifact.file_id !== null))
const selectedDownloadUrl = computed(() => job.value === null || selectedType.value === null || !selectedArtifacts.value.some((artifact) => artifact.file_id !== null) ? null : analysisService.artifactDownloadUrl(job.value.id, selectedType.value))
const allDownloadUrl = computed(() => job.value === null || !hasDownloadedArtifacts.value ? null : analysisService.artifactDownloadUrl(job.value.id))
const previewArtifact = computed(() => {
	const preferred = selectedArtifacts.value.find((artifact) => artifact.file_id !== null && ['report.html', 'report.pdf'].includes(artifact.file_name))
	return preferred ?? selectedArtifacts.value.find((artifact) => artifact.file_id !== null) ?? null
})
const previewUrl = computed(() => job.value === null || previewArtifact.value === null ? null : analysisService.artifactContentUrl(job.value.id, previewArtifact.value.id))
const failureArtifacts = computed(() => artifacts.value.filter((artifact) => ['LOG', 'MARKDOWN', 'ERROR_LOG', 'ERROR_MARKDOWN', 'MANIFEST'].includes(artifact.artifact_type)))

function fileUrl(artifact: AnalysisArtifact | null, download = false): string | null {
	return artifact === null ? null : artifactFileUrl(artifact, download)
}

function isTerminalStatus(status: AnalysisJob['status']): boolean {
	return status === 'JOB_COMPLETED' || status === 'JOB_FAILED' || status === 'COMPLETED' || status === 'FAILED'
}

function orderArtifactsForTree(items: AnalysisArtifact[]): AnalysisArtifact[] {
	const byParent = new Map<number | null, AnalysisArtifact[]>()
	for (const item of items) {
		const siblings = byParent.get(item.parent_id) ?? []
		siblings.push(item)
		byParent.set(item.parent_id, siblings)
	}
	for (const siblings of byParent.values()) {
		siblings.sort((a, b) => a.file_path?.localeCompare(b.file_path ?? '') ?? a.file_name.localeCompare(b.file_name))
	}
	const ordered: AnalysisArtifact[] = []
	const append = (parentId: number | null): void => {
		for (const item of byParent.get(parentId) ?? []) {
			ordered.push(item)
			append(item.id)
		}
	}
	append(null)
	for (const item of items) {
		if (!ordered.some((orderedItem) => orderedItem.id === item.id)) {
			ordered.push(item)
		}
	}
	return ordered
}

async function loadJob(): Promise<void> {
	if (selectedJobId.value === null) {
		job.value = null
		artifacts.value = []
		return
	}
	loading.value = true
	try {
		const jobs = await analysisService.list()
		job.value = jobs.find((item) => item.id === selectedJobId.value) ?? null
		await loadArtifacts()
	} finally {
		loading.value = false
	}
}

async function loadArtifacts(): Promise<void> {
	if (selectedJobId.value === null || !isTerminalStatus(job.value?.status ?? 'DRAFT')) {
		artifacts.value = []
		return
	}
	artifactsLoading.value = true
	artifactError.value = null
	try {
		artifacts.value = await analysisService.artifacts(selectedJobId.value)
		const errorType = artifacts.value.find((artifact) => ['ERROR_LOG', 'ERROR_MARKDOWN'].includes(artifact.artifact_type))
		selectedType.value = errorType?.artifact_type ?? selectableArtifactTypes.value.find((type) => ['HTML', 'PDF'].includes(type)) ?? selectableArtifactTypes.value[0] ?? null
	} catch (error) {
		artifactError.value = error instanceof Error ? error.message : t('structureddiary', 'Results could not be loaded.')
	} finally {
		artifactsLoading.value = false
	}
}

async function startJob(): Promise<void> {
	if (!canStartAnalysis(job.value)) return
	job.value = await analysisService.update(job.value.id, { status: 'SUBMITTED' })
	document.dispatchEvent(new CustomEvent('structured-diary-analysis-job-changed', { detail: { job: job.value } }))
}

async function cancelJob(): Promise<void> {
	if (!canCancelAnalysis(job.value)) return
	job.value = await analysisService.update(job.value.id, { status: 'CANCEL_REQUESTED' })
	document.dispatchEvent(new CustomEvent('structured-diary-analysis-job-changed', { detail: { job: job.value } }))
	cancelDialogOpen.value = false
}

async function deleteJob(): Promise<void> {
	if (!canDeleteAnalysis(job.value)) return
	const removed = await analysisService.remove(job.value.id)
	document.dispatchEvent(new CustomEvent('structured-diary-analysis-job-changed', { detail: { job: removed, removed: true } }))
	deleteDialogOpen.value = false
	await store.pushWorkspaceRoute({ name: 'analyses', params: { diaryId: removed.diary_id } })
}

async function createDraftFromJob(): Promise<void> {
	if (job.value === null) return
	await store.pushWorkspaceRoute({ name: 'analysisCreate', params: { diaryId: job.value.diary_id }, query: { sourceJobId: String(job.value.id) } })
}

function handleHeaderAction(event: Event): void {
	const action = (event as CustomEvent<string>).detail
	if (action === 'start') void startJob()
	if (action === 'cancel' && canCancelAnalysis(job.value)) cancelDialogOpen.value = true
	if (action === 'delete' && canDeleteAnalysis(job.value)) deleteDialogOpen.value = true
	if (action === 'draft') void createDraftFromJob()
}

function handleJobsRefreshed(event: Event): void {
	const refreshedJobs = (event as CustomEvent<AnalysisJobsRefreshedEventDetail>).detail?.jobs ?? []
	const refreshedJob = refreshedJobs.find((item) => item.id === selectedJobId.value)
	if (refreshedJob === undefined) return

	const wasTerminal = job.value !== null && isTerminalStatus(job.value.status)
	job.value = refreshedJob
	if (isTerminalStatus(refreshedJob.status) && (!wasTerminal || artifacts.value.length === 0)) {
		void loadArtifacts()
	}
}

function setMetadataOpen(open: boolean): void {
	const query = { ...route.query }
	if (open) {
		query.metadata = 'open'
	} else {
		delete query.metadata
	}
	void router.replace({ name: route.name ?? undefined, params: route.params, query })
}

watch(selectedJobId, () => {
	void loadJob()
}, { immediate: true })

onMounted(() => {
	document.addEventListener('structured-diary-analysis-action', handleHeaderAction)
	document.addEventListener('structured-diary-analysis-jobs-refreshed', handleJobsRefreshed)
})

onBeforeUnmount(() => {
	document.removeEventListener('structured-diary-analysis-action', handleHeaderAction)
	document.removeEventListener('structured-diary-analysis-jobs-refreshed', handleJobsRefreshed)
})
</script>

<template>
	<div :class="$style.wrap">
		<div v-if="store.selectedDiaryId === null" :class="$style.empty">
			{{ t('structureddiary', 'Select a diary before opening analyses.') }}
		</div>
		<div v-else-if="selectedJobId === null" :class="$style.empty">
			<p>{{ t('structureddiary', 'Select an analysis from the list or create a new one.') }}</p>
			<NcButton v-if="store.selectedDiaryCanAnalyze" @click="store.pushWorkspaceRoute({ name: 'analysisCreate', params: { diaryId: store.selectedDiaryId } })">
				{{ t('structureddiary', 'New analysis') }}
			</NcButton>
		</div>
		<div v-else-if="loading" :class="$style.empty">{{ t('structureddiary', 'Loading analysis...') }}</div>
		<section v-else-if="job !== null" :class="$style.detail">
			<header :class="$style.header">
				<div :class="$style.titleRow">
					<h2>{{ job.title }}</h2>
					<span :class="[$style.badge, $style[`tone_${analysisStatusTone(job.status)}`]]">{{ analysisStatusLabel(job.status) }}</span>
				</div>
				<div v-if="job.status === 'RUNNING'" :class="$style.progressRow">
					<progress :value="progressPercent" max="100" />
					<strong>{{ progressPercent }}%</strong>
				</div>
			</header>

			<div :class="$style.meta">
				<p v-if="job.status === 'COMPLETED' && job.storage_url !== null">
					<strong>{{ t('structureddiary', 'Output path') }}</strong>
					<a :href="job.storage_url">{{ t('structureddiary', 'Open in Nextcloud Files') }}</a>
				</p>
				<p v-else-if="job.status === 'JOB_COMPLETED'">{{ t('structureddiary', 'Results are being collected.') }}</p>
				<p v-if="job.status === 'JOB_FAILED'">{{ t('structureddiary', 'Failure details are being collected.') }}</p>
				<p v-if="job.status_message">{{ job.status_message }}</p>
				<div v-if="job.error_message" :class="$style.errorBox">{{ job.error_message }}</div>

				<details :open="metadataOpen" :class="$style.details" @toggle="setMetadataOpen(($event.target as HTMLDetailsElement).open)">
					<summary>{{ t('structureddiary', 'Metadata') }}</summary>
					<dl :class="$style.metaGrid">
						<div><dt>{{ t('structureddiary', 'Period') }}</dt><dd>{{ formatDate(job.data_from) }} - {{ formatDate(job.data_until) }}</dd></div>
						<div><dt>{{ t('structureddiary', 'Creator') }}</dt><dd>{{ job.created_by }}</dd></div>
						<div><dt>{{ t('structureddiary', 'Created') }}</dt><dd>{{ formatDateTime(job.created_at) }}</dd></div>
						<div v-if="job.started_at !== null"><dt>{{ t('structureddiary', 'Started') }}</dt><dd>{{ formatDateTime(job.started_at) }}</dd></div>
						<div v-if="job.finished_at !== null"><dt>{{ t('structureddiary', 'Finished') }}</dt><dd>{{ formatDateTime(job.finished_at) }}</dd></div>
						<div><dt>{{ t('structureddiary', 'Formats') }}</dt><dd>{{ job.output_types.join(', ') }}</dd></div>
						<div><dt>{{ t('structureddiary', 'Text analysis') }}</dt><dd>{{ job.parameters.includeTextAnalysis ? t('structureddiary', 'Enabled') : t('structureddiary', 'Disabled') }}</dd></div>
						<div><dt>{{ t('structureddiary', 'Language') }}</dt><dd>{{ job.language }}</dd></div>
						<div><dt>{{ t('structureddiary', 'Moving average') }}</dt><dd>{{ job.parameters.shifting_median_width ?? 11 }}</dd></div>
						<div><dt>{{ t('structureddiary', 'Standard deviation') }}</dt><dd>{{ job.parameters.plot_std_error ? t('structureddiary', 'Enabled') : t('structureddiary', 'Disabled') }}</dd></div>
						<div><dt>{{ t('structureddiary', 'Single data points') }}</dt><dd>{{ job.parameters.show_single_data_points ? t('structureddiary', 'Enabled') : t('structureddiary', 'Disabled') }}</dd></div>
					</dl>
				</details>
			</div>

			<section v-if="job.status === 'FAILED'" :class="$style.failure">
				<h3>{{ t('structureddiary', 'Failure details') }}</h3>
				<p v-if="job.error_message">{{ job.error_message }}</p>
				<p v-if="job.status_message">{{ job.status_message }}</p>
				<a v-for="artifact in failureArtifacts" :key="artifact.id" :href="fileUrl(artifact, true) ?? undefined">{{ artifact.file_name }}</a>
			</section>

			<section v-if="isTerminalStatus(job.status)" :class="$style.artifacts">
				<div :class="$style.artifactToolbar">
					<NcButton v-if="job.storage_url !== null" :href="job.storage_url" :aria-label="t('structureddiary', 'Open directory')" target="_blank" variant="secondary">
						<template #icon><NcIconSvgWrapper :path="mdiFolderOpenOutline" /></template>
					</NcButton>
					<NcButton :aria-label="t('structureddiary', 'Show artifact list')" variant="secondary" @click="showArtifactList = !showArtifactList">
						<template #icon><NcIconSvgWrapper :path="mdiFileTreeOutline" /></template>
					</NcButton>
					<NcButton v-if="allDownloadUrl !== null" :href="allDownloadUrl" :aria-label="t('structureddiary', 'Download all')" variant="secondary">
						<template #icon><NcIconSvgWrapper :path="mdiDownload" /></template>
					</NcButton>
					<NcButton v-if="selectedDownloadUrl !== null" :href="selectedDownloadUrl" :aria-label="t('structureddiary', 'Download selected type')" variant="secondary">
						<template #icon><NcIconSvgWrapper :path="mdiDownload" /></template>
						<span :class="$style.buttonText">{{ t('structureddiary', 'Type') }}</span>
					</NcButton>
					<NcButton v-if="previewUrl !== null && (previewArtifact?.artifact_type === 'HTML' || previewArtifact?.artifact_type === 'PDF')" :href="previewUrl" target="_blank" :aria-label="t('structureddiary', 'Open in new tab')" variant="secondary">
						<template #icon><NcIconSvgWrapper :path="mdiOpenInNew" /></template>
					</NcButton>
					<select v-model="selectedType" :disabled="selectableArtifactTypes.length <= 1" :class="['nc-input-field__input', $style.typeSelect]">
						<option v-for="type in selectableArtifactTypes" :key="type" :value="type">{{ type }}</option>
					</select>
					<NcButton variant="secondary" @click="loadArtifacts()">{{ t('structureddiary', 'Refresh') }}</NcButton>
				</div>

				<div v-if="artifactsLoading" :class="$style.empty">{{ t('structureddiary', 'Loading results...') }}</div>
				<div v-else-if="artifactError !== null" :class="$style.errorBox">{{ artifactError }}</div>
				<div v-else-if="artifacts.length === 0" :class="$style.empty">{{ t('structureddiary', 'No results.') }}</div>

				<div v-if="showArtifactList && sortedArtifacts.length > 0" :class="$style.tree">
					<div v-for="artifact in sortedArtifacts" :key="artifact.id" :class="[$style.artifactRow, artifact.parent_id !== null && $style.artifactChild]">
						<span>{{ artifact.file_name }}</span>
						<span>{{ formatBytes(artifact.size) }}</span>
						<a :href="fileUrl(artifact, true) ?? undefined" :aria-disabled="fileUrl(artifact, true) === null">{{ t('structureddiary', 'Download') }}</a>
					</div>
				</div>

				<div v-if="!artifactsLoading && previewArtifact !== null" :class="$style.preview">
					<iframe
						v-if="previewArtifact?.artifact_type === 'HTML' && previewUrl !== null && !previewFailed"
						:src="previewUrl ?? undefined"
						sandbox="allow-same-origin allow-popups allow-downloads"
						:title="previewArtifact.file_name"
						@error="previewFailed = true" />
					<object
						v-else-if="previewArtifact?.artifact_type === 'PDF' && previewUrl !== null && !previewFailed"
						:data="previewUrl ?? undefined"
						type="application/pdf"
						:title="previewArtifact.file_name"
						@error="previewFailed = true">
						<a :href="fileUrl(previewArtifact, true) ?? undefined">{{ t('structureddiary', 'Download') }}</a>
					</object>
					<p v-else>
						<a :href="fileUrl(previewArtifact, true) ?? undefined">
							{{ t('structureddiary', 'Download') }}<span v-if="previewArtifact !== null"> ({{ formatBytes(previewArtifact.size) }})</span>
						</a>
					</p>
				</div>
			</section>
		</section>

		<NcDialog
			v-model:open="cancelDialogOpen"
			:name="t('structureddiary', 'Cancel analysis')"
			:message="t('structureddiary', 'Cancel this analysis job?')"
			:buttons="[
				{ label: t('structureddiary', 'Keep running'), callback: () => undefined },
				{ label: t('structureddiary', 'Cancel analysis'), variant: 'warning', callback: () => { void cancelJob() } },
			]"
			size="small" />
		<NcDialog
			v-model:open="deleteDialogOpen"
			:name="t('structureddiary', 'Delete analysis')"
			:message="t('structureddiary', 'Delete this analysis job?')"
			:buttons="[
				{ label: t('structureddiary', 'Cancel'), callback: () => undefined },
				{ label: t('structureddiary', 'Delete'), variant: 'error', callback: () => { void deleteJob() } },
			]"
			size="small" />
	</div>
</template>

<style module>
.wrap,
.detail {
	display: grid;
	gap: 16px;
	min-width: 0;
}

.header,
.meta,
.artifacts,
.failure {
	display: grid;
	gap: 12px;
	min-width: 0;
}

.titleRow {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	align-items: center;
}

.titleRow h2 {
	margin: 0;
	font-size: 1.5rem;
	overflow-wrap: anywhere;
}

.badge {
	padding: 3px 8px;
	border-radius: 999px;
	font-size: 0.78rem;
	font-weight: 700;
}

.tone_neutral { background: var(--color-background-dark); color: var(--color-main-text); }
.tone_info { background: rgba(0, 130, 201, 0.14); color: #005f8f; }
.tone_success { background: rgba(36, 154, 70, 0.16); color: #14612d; }
.tone_warning { background: rgba(181, 126, 0, 0.18); color: #7a5400; }
.tone_error { background: rgba(176, 0, 32, 0.14); color: #8c1024; }

.progressRow {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	gap: 10px;
	align-items: center;
}

.meta p,
.failure p {
	margin: 0;
}

.details {
	border-top: 1px solid var(--color-border);
	padding-top: 8px;
}

.metaGrid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
	gap: 8px 14px;
	margin: 10px 0 0;
}

.metaGrid div {
	min-width: 0;
}

.metaGrid dt {
	color: var(--color-text-maxcontrast);
	font-size: 0.8rem;
	font-weight: 700;
}

.metaGrid dd {
	margin: 0;
	overflow-wrap: anywhere;
}

.errorBox,
.failure {
	padding: 12px 14px;
	border-radius: var(--border-radius);
	background: rgba(176, 0, 32, 0.12);
	color: #8c1024;
	font-weight: 700;
}

.artifactToolbar {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: center;
}

.typeSelect {
	min-width: 130px;
	min-height: 44px;
}

.tree {
	display: grid;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	overflow: hidden;
}

.artifactRow {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto auto;
	gap: 12px;
	align-items: center;
	padding: 8px 10px;
}

.artifactRow:nth-child(even) {
	background: var(--color-background-hover);
}

.artifactRow span:first-child {
	overflow-wrap: anywhere;
}

.artifactChild span:first-child {
	padding-left: 24px;
}

.preview {
	min-width: 0;
}

.preview iframe,
.preview object {
	display: block;
	width: 100%;
	min-height: 70vh;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
}

.empty {
	display: grid;
	place-items: center;
	gap: 12px;
	min-height: 220px;
	color: var(--color-text-maxcontrast);
	text-align: center;
}

@media (max-width: 720px) {
	.artifactRow {
		grid-template-columns: minmax(0, 1fr);
	}

	.preview iframe,
	.preview object {
		min-height: 60vh;
	}
}
</style>
