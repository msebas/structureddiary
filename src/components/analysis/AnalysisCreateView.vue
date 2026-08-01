<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { analysisService } from '@/services'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { AnalysisJob, AnalysisJobCopySettings, AnalysisOutputType } from '@/types/types'
import '@/css/workspace-card.css'
import { t } from '@nextcloud/l10n'

const store = useStructuredDiaryStore()
const route = useRoute()
const props = defineProps<{
	sourceJob?: AnalysisJob | null
}>()
const submitting = ref(false)
const submitError = ref<string | null>(null)
const title = ref(props.sourceJob?.title ?? 'Analysis')
const analysisType = ref(props.sourceJob?.analysis_type ?? 'standard')
const llmUrl = ref(props.sourceJob?.llm_url ?? null)
const llmHeader = ref<string | null>(null)
const includeTextAnalysis = ref(props.sourceJob?.parameters.includeTextAnalysis ?? true)
const language = ref(props.sourceJob?.language ?? 'de-DE')
const shiftingMedianWidth = ref(props.sourceJob?.parameters.shifting_median_width ?? 11)
const plotStdError = ref(props.sourceJob?.parameters.plot_std_error ?? false)
const showSingleDataPoints = ref(props.sourceJob?.parameters.show_single_data_points ?? true)
const outputFormats = ref<AnalysisOutputType[]>(props.sourceJob?.output_types ?? ['JSON', 'HTML'])
const copiedFrom = ref<{ date: string, timestamp: number } | null>(null)

function dateInputValue(timestamp: number): string {
	const date = new Date(timestamp * 1000)
	const year = date.getFullYear()
	const month = String(date.getMonth() + 1).padStart(2, '0')
	const day = String(date.getDate()).padStart(2, '0')
	return `${year}-${month}-${day}`
}

function timestampFromDate(value: string, endOfDay: boolean): number {
	return Math.floor(new Date(`${value}T${endOfDay ? '23:59:59' : '00:00:00'}`).getTime() / 1000)
}

const today = Math.floor(Date.now() / 1000)
const from = ref(dateInputValue(props.sourceJob?.data_from ?? store.selectedDiaryStats?.latest_entry_at ?? store.selectedDiaryStats?.first_entry_at ?? today))
const until = ref(dateInputValue(today))
const dateRangeInvalid = computed(() => timestampFromDate(from.value, false) > timestampFromDate(until.value, true))
const formatsInvalid = computed(() => outputFormats.value.length === 0)
const formInvalid = computed(() => store.selectedDiaryId === null || dateRangeInvalid.value || formatsInvalid.value || submitting.value)

function applySourceJob(sourceJob: AnalysisJobCopySettings): void {
	title.value = sourceJob.title
	analysisType.value = sourceJob.analysis_type
	llmUrl.value = sourceJob.llm_url ?? null
	llmHeader.value = sourceJob.llm_header ?? null
	includeTextAnalysis.value = sourceJob.parameters.includeTextAnalysis ?? true
	language.value = sourceJob.language
	shiftingMedianWidth.value = sourceJob.parameters.shifting_median_width ?? 11
	plotStdError.value = sourceJob.parameters.plot_std_error ?? false
	showSingleDataPoints.value = sourceJob.parameters.show_single_data_points ?? true
	outputFormats.value = [...sourceJob.output_types]
	const sourceFromDate = dateInputValue(sourceJob.data_from)
	from.value = sourceFromDate
	copiedFrom.value = { date: sourceFromDate, timestamp: sourceJob.data_from }
	until.value = dateInputValue(today)
}

async function loadSourceJob(): Promise<void> {
	if (props.sourceJob !== null && props.sourceJob !== undefined) {
		const sourceSettings = await analysisService.copySettings(props.sourceJob.id).catch(() => null)
		if (sourceSettings !== null) applySourceJob(sourceSettings)
		return
	}
	const value = route.query.sourceJobId
	const sourceJobId = typeof value === 'string' ? Number.parseInt(value, 10) : null
	if (sourceJobId === null || Number.isNaN(sourceJobId)) return
	const sourceSettings = await analysisService.copySettings(sourceJobId).catch(() => null)
	if (sourceSettings !== null) applySourceJob(sourceSettings)
}

function toggleFormat(format: AnalysisOutputType): void {
	outputFormats.value = outputFormats.value.includes(format)
		? outputFormats.value.filter((item) => item !== format)
		: [...outputFormats.value, format]
}

async function submit(start: boolean): Promise<void> {
	if (formInvalid.value || store.selectedDiaryId === null) {
		return
	}
	submitting.value = true
	submitError.value = null
	try {
		const job = await analysisService.create({
			diaryId: store.selectedDiaryId,
			fromTimestamp: copiedFrom.value?.date === from.value
				? copiedFrom.value.timestamp
				: timestampFromDate(from.value, false),
			untilTimestamp: timestampFromDate(until.value, true),
			title: title.value.trim() || 'Analysis',
			language: language.value,
			analysisType: analysisType.value,
			llmUrl: llmUrl.value,
			llmHeader: llmHeader.value,
			start,
			outputFormats: outputFormats.value,
			parameters: {
				includeTextAnalysis: includeTextAnalysis.value,
				shifting_median_width: shiftingMedianWidth.value,
				plot_std_error: plotStdError.value,
				show_single_data_points: showSingleDataPoints.value,
			},
		})
		document.dispatchEvent(new CustomEvent('structured-diary-analysis-job-changed', { detail: { job } }))
		await store.pushWorkspaceRoute({ name: 'analysis', params: { diaryId: job.diary_id, jobId: job.id } })
	} catch (error) {
		const message = error instanceof Error ? error.message : t('structureddiary', 'Analysis could not be created.')
		submitError.value = message
		store.addError({
			message,
			type: 'error',
			cause: error,
		})
	} finally {
		submitting.value = false
	}
}

async function onSubmit(event: SubmitEvent): Promise<void> {
	const submitter = event.submitter as HTMLElement | null
	await submit(submitter?.dataset.start === 'true')
}

async function cancel(): Promise<void> {
	await store.pushWorkspaceRoute({ name: 'analyses', params: { diaryId: store.selectedDiaryId } })
}

onMounted(() => {
	void loadSourceJob()
})
</script>

<template>
	<div :class="$style.wrap">
		<div v-if="store.selectedDiaryId === null" :class="$style.empty">
			{{ t('structureddiary', 'Select a diary before creating an analysis.') }}
		</div>
		<form
			v-else
			id="structured-diary-analysis-create-form"
			class="workspace-card"
			:class="$style.form"
			@submit.prevent="onSubmit($event as SubmitEvent)">
			<fieldset :disabled="submitting" :class="$style.fieldset">
				<section :class="$style.section">
					<h2>{{ t('structureddiary', 'Details and period') }}</h2>
					<label :class="$style.field">
						<span>{{ t('structureddiary', 'Title') }}</span>
						<input v-model="title" :class="['nc-input-field__input', $style.input]" type="text">
					</label>
					<div :class="$style.grid">
						<label :class="$style.field">
							<span>{{ t('structureddiary', 'From') }}</span>
							<input v-model="from" :class="['nc-input-field__input', $style.input]" type="date">
						</label>
						<label :class="$style.field">
							<span>{{ t('structureddiary', 'Until') }}</span>
							<input v-model="until" :class="['nc-input-field__input', $style.input]" type="date">
						</label>
					</div>
					<p v-if="dateRangeInvalid" :class="$style.error">{{ t('structureddiary', 'From must not be after Until.') }}</p>
				</section>

				<section :class="$style.section">
					<h2>{{ t('structureddiary', 'Analysis options') }}</h2>
					<label :class="$style.check">
						<input v-model="includeTextAnalysis" type="checkbox">
						<span>{{ t('structureddiary', 'Include text analysis') }}</span>
					</label>
					<p :class="$style.help">{{ t('structureddiary', 'Text analysis can take longer and depends on the server-side LLM configuration.') }}</p>
					<label :class="$style.field">
						<span>{{ t('structureddiary', 'Language') }}</span>
						<select v-model="language" :class="['nc-input-field__input', $style.input]">
							<option value="en-US">{{ t('structureddiary', 'US English') }}</option>
							<option value="en-GB">{{ t('structureddiary', 'British English') }}</option>
							<option value="de-DE">{{ t('structureddiary', 'German') }}</option>
						</select>
					</label>
					<label :class="$style.field">
						<span>{{ t('structureddiary', 'Moving average window') }}</span>
						<input v-model.number="shiftingMedianWidth" :class="['nc-input-field__input', $style.input]" type="number" min="1" step="1">
					</label>
					<label :class="$style.check">
						<input v-model="plotStdError" type="checkbox">
						<span>{{ t('structureddiary', 'Show standard deviation in charts and tables') }}</span>
					</label>
					<label :class="$style.check">
						<input v-model="showSingleDataPoints" type="checkbox">
						<span>{{ t('structureddiary', 'Show single data points') }}</span>
					</label>
				</section>

				<section :class="$style.section">
					<h2>{{ t('structureddiary', 'Output formats') }}</h2>
					<div :class="$style.formats">
						<label v-for="format in (['JSON', 'HTML', 'PDF', 'XLSX'] as AnalysisOutputType[])" :key="format" :class="$style.check">
							<input :checked="outputFormats.includes(format)" type="checkbox" @change="toggleFormat(format)">
							<span>{{ format }}</span>
						</label>
					</div>
					<p v-if="formatsInvalid" :class="$style.error">{{ t('structureddiary', 'Select at least one output format.') }}</p>
				</section>
				<p v-if="submitError !== null" :class="$style.error">{{ submitError }}</p>
			</fieldset>

			<div :class="$style.actions">
				<NcButton type="button" variant="secondary" :disabled="submitting" @click="cancel()">{{ t('structureddiary', 'Cancel') }}</NcButton>
				<NcButton id="structured-diary-analysis-create-draft" type="submit" :disabled="formInvalid" data-start="false">{{ t('structureddiary', 'Create draft') }}</NcButton>
				<NcButton type="submit" variant="primary" :disabled="formInvalid" data-start="true">{{ t('structureddiary', 'Create and start') }}</NcButton>
			</div>
		</form>
	</div>
</template>

<style module>
.wrap {
	display: grid;
	align-content: start;
	gap: 16px;
	min-height: 100%;
}

.form {
	display: grid;
	gap: 18px;
	max-width: 880px;
}

.fieldset {
	display: grid;
	gap: 18px;
	min-width: 0;
	padding: 0;
	border: 0;
}

.section {
	display: grid;
	gap: 12px;
}

.section h2 {
	margin: 0;
	font-size: 1rem;
}

.grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
}

.field {
	display: grid;
	gap: 5px;
	font-weight: 600;
}

.input {
	width: 100%;
	min-height: 44px;
}

.check {
	display: inline-flex;
	gap: 8px;
	align-items: center;
	min-height: 36px;
	font-weight: 600;
}

.formats {
	display: flex;
	flex-wrap: wrap;
	gap: 14px;
}

.help {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.error {
	margin: 0;
	color: var(--color-error);
	font-weight: 700;
}

.actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 8px;
}

.empty {
	display: grid;
	place-items: center;
	min-height: 240px;
	color: var(--color-text-maxcontrast);
}

@media (max-width: 720px) {
	.grid {
		grid-template-columns: 1fr;
	}
}
</style>
