<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiCheckAll, mdiCloseCircleOutline, mdiCogOutline, mdiPlus, mdiRefresh } from '@mdi/js'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { analysisService } from '@/services'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { AnalysisJob, AnalysisJobStatus } from '@/types/types'
import { analysisStatusLabel, analysisStatusTone, compareAnalysisJobs, isAnalysisMutable } from '@/utils/analysis'
import { formatDateTime } from '@/utils/format'
import { t } from '@nextcloud/l10n'

type FilterKey = 'active' | 'completed' | 'failed'

const emit = defineEmits<{
	(event: 'open-center'): void
}>()

const store = useStructuredDiaryStore()
const route = useRoute()
const jobs = ref<Record<number, AnalysisJob>>({})
const loading = ref(false)
const pollingOpen = ref(false)
const selectedFilters = ref<FilterKey[]>(['active', 'completed', 'failed'])
const pollingSeconds = ref<1 | 15 | 60 | 120 | 300 | 0>(120)
const lastRefreshIso = ref<string | null>(null)
let pollingTimer: ReturnType<typeof setTimeout> | null = null
let idleResetTimer: ReturnType<typeof setTimeout> | null = null

const selectedJobId = computed(() => {
	const value = route.params.jobId
	return typeof value === 'string' ? Number.parseInt(value, 10) : null
})

const currentJobs = computed(() => {
	const diaryId = store.selectedDiaryId
	if (diaryId === null) return []
	return Object.values(jobs.value)
		.filter((job) => job.diary_id === diaryId)
		.filter((job) => selectedFilters.value.includes(filterKeyForStatus(job.status)))
		.sort(compareAnalysisJobs)
})

const hasMutableJobs = computed(() => Object.values(jobs.value).some((job) => job.diary_id === store.selectedDiaryId && isAnalysisMutable(job)))

function filterKeyForStatus(status: AnalysisJobStatus): FilterKey {
	if (status === 'COMPLETED') return 'completed'
	if (status === 'FAILED' || status === 'CANCELED') return 'failed'
	return 'active'
}

function progressPercent(job: AnalysisJob): number {
	return Math.max(0, Math.min(100, Math.round(job.progress)))
}

function mergeJobs(nextJobs: AnalysisJob[]): void {
	for (const job of nextJobs) {
		jobs.value[job.id] = job
	}
}

function notifyJobsRefreshed(): void {
	document.dispatchEvent(new CustomEvent('structured-diary-analysis-jobs-refreshed', {
		detail: { jobs: Object.values(jobs.value) },
	}))
}

async function refresh(changedOnly = false): Promise<void> {
	if (store.selectedDiaryId === null) {
		jobs.value = {}
		notifyJobsRefreshed()
		return
	}
	loading.value = true
	try {
		const changedSince = changedOnly ? lastRefreshIso.value : null
		const response = await analysisService.list(changedSince)
		if (!changedOnly) {
			jobs.value = Object.fromEntries(response.map((job) => [job.id, job]))
		} else {
			mergeJobs(response)
		}
		lastRefreshIso.value = new Date().toISOString()
		notifyJobsRefreshed()
	} catch (error) {
		store.addError({
			message: t('structureddiary', 'The analysis service is not available. Please contact an administrator.'),
			type: 'error',
			cause: error,
		})
	} finally {
		loading.value = false
	}
}

async function createAnalysis(): Promise<void> {
	if (store.selectedDiaryId === null) return
	await store.pushWorkspaceRoute({ name: 'analysisCreate', params: { diaryId: store.selectedDiaryId } })
	emit('open-center')
}

async function selectJob(job: AnalysisJob): Promise<void> {
	await store.pushWorkspaceRoute({ name: 'analysis', params: { diaryId: job.diary_id, jobId: job.id } })
	emit('open-center')
}

function toggleFilter(filter: FilterKey): void {
	selectedFilters.value = selectedFilters.value.includes(filter)
		? selectedFilters.value.filter((item) => item !== filter)
		: [...selectedFilters.value, filter]
}

function setAllFilters(enabled: boolean): void {
	selectedFilters.value = enabled ? ['active', 'completed', 'failed'] : []
}

function clearPollingTimer(): void {
	if (pollingTimer !== null) {
		clearTimeout(pollingTimer)
		pollingTimer = null
	}
}

function schedulePolling(): void {
	clearPollingTimer()
	if (pollingSeconds.value === 0) return
	pollingTimer = setTimeout(async () => {
		await refresh(true)
		schedulePolling()
	}, pollingSeconds.value * 1000)
}

watch([pollingSeconds, () => route.name], () => {
	if (route.name === 'analyses' || route.name === 'analysis' || route.name === 'analysisCreate') {
		schedulePolling()
	} else {
		clearPollingTimer()
	}
})

watch(hasMutableJobs, (mutable) => {
	if (idleResetTimer !== null) {
		clearTimeout(idleResetTimer)
		idleResetTimer = null
	}
	if (!mutable && pollingSeconds.value !== 120) {
		idleResetTimer = setTimeout(() => {
			pollingSeconds.value = 120
		}, 120_000)
	}
})

watch(() => store.selectedDiaryId, () => {
	void refresh(false)
})

onMounted(async () => {
	await refresh(false)
	schedulePolling()
})

onBeforeUnmount(() => {
	clearPollingTimer()
	if (idleResetTimer !== null) {
		clearTimeout(idleResetTimer)
	}
})
</script>

<template>
	<aside :class="$style.panel">
		<div :class="$style.actions">
			<NcButton
				:disabled="store.selectedDiaryId === null || !store.selectedDiaryCanAnalyze"
				:aria-label="t('structureddiary', 'New analysis')"
				@click="createAnalysis()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				{{ t('structureddiary', 'New analysis') }}
			</NcButton>
			<NcButton :aria-label="t('structureddiary', 'Refresh analyses')" variant="secondary" @click="refresh(false)">
				<template #icon>
					<NcIconSvgWrapper :path="mdiRefresh" />
				</template>
			</NcButton>
			<NcButton :aria-label="t('structureddiary', 'Polling rate')" variant="secondary" @click="pollingOpen = !pollingOpen">
				<template #icon>
					<NcIconSvgWrapper :path="mdiCogOutline" />
				</template>
			</NcButton>
		</div>

		<div v-if="pollingOpen" :class="$style.popover">
			<label :class="$style.field">
				<span>{{ t('structureddiary', 'Polling') }}</span>
				<select v-model.number="pollingSeconds" :class="['nc-input-field__input', $style.input]">
					<option :value="1">1 s</option>
					<option :value="15">15 s</option>
					<option :value="60">60 s</option>
					<option :value="120">120 s</option>
					<option :value="300">300 s</option>
					<option :value="0">{{ t('structureddiary', 'None') }}</option>
				</select>
			</label>
		</div>

		<div :class="$style.filters">
			<NcButton variant="tertiary" :aria-label="t('structureddiary', 'Select all statuses')" @click="setAllFilters(true)">
				<template #icon><NcIconSvgWrapper :path="mdiCheckAll" /></template>
			</NcButton>
			<NcButton variant="tertiary" :aria-label="t('structureddiary', 'Clear all statuses')" @click="setAllFilters(false)">
				<template #icon><NcIconSvgWrapper :path="mdiCloseCircleOutline" /></template>
			</NcButton>
			<label v-for="filter in (['active', 'completed', 'failed'] as FilterKey[])" :key="filter" :class="$style.filter">
				<input
					type="checkbox"
					:checked="selectedFilters.includes(filter)"
					@change="toggleFilter(filter)">
				<span>{{ filter === 'active' ? t('structureddiary', 'Active') : filter === 'completed' ? t('structureddiary', 'Completed') : t('structureddiary', 'Failed') }}</span>
			</label>
		</div>

		<div v-if="store.selectedDiaryId === null" :class="$style.empty">
			{{ t('structureddiary', 'Select a diary first.') }}
		</div>
		<div v-else-if="!loading && currentJobs.length === 0" :class="$style.empty">
			<p>{{ t('structureddiary', 'No analyses yet.') }}</p>
			<NcButton v-if="store.selectedDiaryCanAnalyze" @click="createAnalysis()">{{ t('structureddiary', 'New analysis') }}</NcButton>
		</div>
		<div v-else :class="$style.list">
			<button
				v-for="job in currentJobs"
				:key="job.id"
				type="button"
				:class="$style.itemButton"
				@click="selectJob(job)">
				<span :class="[$style.item, job.id === selectedJobId && $style.itemActive]">
					<span :class="$style.itemTitle">{{ job.title }}</span>
					<span :class="$style.itemMeta">{{ formatDateTime(job.created_at) }}</span>
					<span :class="[$style.badge, $style[`tone_${analysisStatusTone(job.status)}`]]">{{ analysisStatusLabel(job.status) }}</span>
					<span v-if="job.status === 'RUNNING'" :class="$style.progressWrap">
						<progress :value="progressPercent(job)" max="100" :class="$style.progress" />
						<span>{{ progressPercent(job) }}%</span>
					</span>
				</span>
			</button>
		</div>
	</aside>
</template>

<style module>
.panel {
	display: flex;
	flex-direction: column;
	gap: 12px;
	height: 100%;
	min-height: 0;
	padding: 18px;
	overflow: hidden;
	background: var(--color-main-background);
}

.actions,
.filters {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: center;
}

.popover {
	padding: 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
}

.field {
	display: grid;
	gap: 4px;
}

.input {
	min-height: 40px;
}

.filter {
	display: inline-flex;
	gap: 6px;
	align-items: center;
	min-height: 36px;
	font-weight: 600;
}

.list {
	flex: 1 1 auto;
	min-height: 0;
	overflow-x: hidden;
	overflow-y: auto;
}

.itemButton {
	display: block;
	width: 100%;
	min-height: var(--default-clickable-area);
	padding: 0;
	font: inherit;
	text-align: left;
	white-space: normal;
	cursor: pointer;
}

.itemButton + .itemButton {
	margin-block-start: 8px;
}

.item {
	display: grid;
	gap: 6px;
	width: 100%;
	padding: 12px 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	box-sizing: border-box;
}

.itemActive {
	border-color: var(--color-primary-element);
	background: var(--color-background-hover);
}

.itemTitle,
.itemMeta {
	min-width: 0;
	overflow-wrap: anywhere;
}

.itemTitle {
	color: var(--color-main-text);
	font-weight: 700;
}

.itemMeta {
	color: var(--color-text-maxcontrast);
	font-size: 0.82rem;
	font-weight: 600;
}

.badge {
	justify-self: start;
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

.progressWrap {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	gap: 8px;
	align-items: center;
	font-size: 0.82rem;
	font-weight: 700;
}

.progress {
	width: 100%;
}

.empty {
	display: grid;
	place-items: center;
	gap: 12px;
	min-height: 180px;
	color: var(--color-text-maxcontrast);
	text-align: center;
}
</style>
