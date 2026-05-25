<script setup lang="ts">
import { Permissions, type Diary, type DiaryShare, type DiaryStats } from '@/types/types'
import { formatDurationSeconds, frequencyLabel, formatTimeOnly } from '@/utils/format'
import { scheduleSecondsToDays } from '@/utils/diary'
import { n, t } from '@nextcloud/l10n'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps<{
	diary: Diary | null
	shares: DiaryShare[]
	stats: DiaryStats | null
	hideStats?: boolean
}>()

type DisplayRow = { label: string, value: string }

const stackElement = ref<HTMLElement | null>(null)
const dataColumnCount = ref(1)
let resizeObserver: ResizeObserver | null = null

function updateDataColumnCount(width: number): void {
	const minimumColumnWidth = 300
	const columnGap = 20
	dataColumnCount.value = Math.max(1, Math.floor((width + columnGap) / (minimumColumnWidth + columnGap)))
}

function displayLines(rows: DisplayRow[]): DisplayRow[][] {
	const lines: DisplayRow[][] = []
	const columnCount = dataColumnCount.value
	for (let index = 0; index < rows.length; index += columnCount) {
		lines.push(rows.slice(index, index + columnCount))
	}
	return lines
}

function disconnectResizeObserver(): void {
	resizeObserver?.disconnect()
	resizeObserver = null
}

function attachResizeObserver(element: HTMLElement | null): void {
	disconnectResizeObserver()
	if (element === null) {
		dataColumnCount.value = 1
		return
	}

	const update = () => updateDataColumnCount(element.getBoundingClientRect().width)
	void nextTick(() => {
		update()
		requestAnimationFrame(update)
	})
	resizeObserver = new ResizeObserver((entries) => {
		const entry = entries[0]
		if (entry !== undefined) {
			updateDataColumnCount(entry.contentRect.width)
		}
	})
	resizeObserver.observe(element)
}

function permissionLabel(permission: number): string {
	const labels = []
	const hasPermissionBeyondRead = (permission & ~Permissions.READ) !== 0

	if ((permission & Permissions.READ) !== 0 && !hasPermissionBeyondRead) {
		labels.push(t('structureddiary', 'read'))
	}
	if ((permission & Permissions.WRITE) !== 0) {
		labels.push(t('structureddiary', 'write'))
	}
	if ((permission & Permissions.ANALYZE) !== 0) {
		labels.push(t('structureddiary', 'analyze'))
	}
	if ((permission & Permissions.MANAGE) !== 0) {
		labels.push(t('structureddiary', 'manage'))
	}

	return labels.length === 0 ? t('structureddiary', 'none') : labels.join(', ')
}

const scheduleRows = computed(() => {
	if (props.diary === null) {
		return []
	}

	return [
		{
			label: t('structureddiary', 'Target cadence'),
			value: n('structureddiary', '%n day', '%n days', scheduleSecondsToDays(props.diary.entry_schedule)),
		},
		{
			label: t('structureddiary', 'Reminder'),
			value: props.diary.reminder_active ? t('structureddiary', 'Active') : t('structureddiary', 'Disabled'),
		},
		{ label: t('structureddiary', 'Reminder time'), value: formatTimeOnly(props.diary.reminder_time) },
		{ label: t('structureddiary', 'Repeat count'), value: String(props.diary.reminder_count) },
		{ label: t('structureddiary', 'Repeat delay'), value: formatDurationSeconds(props.diary.reminder_delay) },
		{ label: t('structureddiary', 'First signal'), value: props.diary.reminder_signal_first || t('structureddiary', 'n/a') },
		{ label: t('structureddiary', 'Repeat signal'), value: props.diary.reminder_signal_repeat || t('structureddiary', 'n/a') },
	]
})

const shareRows = computed(() => props.shares.map((share) => ({
	label: share.shared_with,
	value: permissionLabel(share.permission),
})))

const statsRows = computed(() => {
	if (props.stats === null) {
		return []
	}

	return [
		{ label: t('structureddiary', 'Questions'), value: String(props.stats.question_count) },
		{ label: t('structureddiary', 'Entries'), value: String(props.stats.entry_count) },
		{ label: t('structureddiary', 'Answers'), value: String(props.stats.answer_count) },
		{ label: t('structureddiary', 'Avg answers'), value: props.stats.average_answer_count.toFixed(2) },
		{ label: t('structureddiary', 'Frequency'), value: frequencyLabel(props.stats.entry_frequency) },
		{ label: t('structureddiary', 'Last month'), value: frequencyLabel(props.stats.entry_frequency_last_month) },
		{ label: t('structureddiary', 'Avg duration'), value: formatDurationSeconds(props.stats.average_entry_duration ?? undefined) },
		{
			label: t('structureddiary', 'Avg duration last month'),
			value: formatDurationSeconds(props.stats.average_entry_duration_last_month ?? undefined),
		},
	]
})

watch(stackElement, attachResizeObserver, { flush: 'post' })

onBeforeUnmount(() => {
	disconnectResizeObserver()
})
</script>

<template>
	<section class="workspace-card">
		<template v-if="props.diary">
			<header :class="['workspace-card-header', $style.header]">
				<div>
					<h2 :class="$style.title">{{ props.diary.title }}</h2>
					<div :class="['workspace-card-muted', $style.owner]">{{ t('structureddiary', 'Owner: {owner}', {owner: props.diary.user_id}) }}</div>
				</div>
			</header>

			<div ref="stackElement" :class="$style.stack">
				<article :class="['workspace-card-subcard', $style.block, $style.descriptionBlock]">
					<h3>{{ t('structureddiary', 'Description') }}</h3>
					<p :class="$style.copy">{{ props.diary.description || t('structureddiary', 'No description.') }}</p>
				</article>

				<article :class="['workspace-card-subcard', $style.block]">
					<h3>{{ t('structureddiary', 'Schedule') }}</h3>
					<div :class="$style.dataTable">
						<div v-for="(line, index) in displayLines(scheduleRows)" :key="index" :class="$style.dataLine">
							<div v-for="row in line" :key="row.label" :class="$style.dataRow">
								<div :class="$style.dataLabel">{{ row.label }}</div>
								<div :class="$style.dataValue">{{ row.value }}</div>
							</div>
						</div>
					</div>
				</article>

				<article :class="['workspace-card-subcard', $style.block]">
					<h3>{{ t('structureddiary', 'Shares') }}</h3>
					<div v-if="shareRows.length === 0" class="workspace-card-muted">{{ t('structureddiary', 'No shares configured.') }}</div>
					<div v-else :class="$style.dataTable">
						<div v-for="(line, index) in displayLines(shareRows)" :key="index" :class="$style.dataLine">
							<div v-for="row in line" :key="row.label" :class="$style.dataRow">
								<div :class="$style.dataLabel">{{ row.label }}</div>
								<div :class="$style.dataValue">{{ row.value }}</div>
							</div>
						</div>
					</div>
				</article>

				<article v-if="!props.hideStats" :class="['workspace-card-subcard', $style.block]">
					<h3>{{ t('structureddiary', 'Statistics') }}</h3>
					<div v-if="props.stats" :class="$style.dataTable">
						<div v-for="(line, index) in displayLines(statsRows)" :key="index" :class="$style.dataLine">
							<div v-for="row in line" :key="row.label" :class="$style.dataRow">
								<div :class="$style.dataLabel">{{ row.label }}</div>
								<div :class="$style.dataValue">{{ row.value }}</div>
							</div>
						</div>
					</div>
					<div v-else class="workspace-card-muted">
						{{ t('structureddiary', 'Statistics not loaded yet.') }}
					</div>
				</article>
        <div class="workspace-end-space"></div>
			</div>
		</template>

		<template v-else>
			<div :class="['workspace-card-empty', $style.empty]">{{ t('structureddiary', 'Select a diary to inspect it here.') }}</div>
      <div class="workspace-end-space"></div>
    </template>
	</section>
</template>

<style module>
.title {
	margin: 0;
	font-size: 1.5rem;
	overflow-wrap: anywhere;
}

.owner {
	margin-top: 8px;
	overflow-wrap: anywhere;
}

.stack {
	display: grid;
	gap: 16px;
}

.block {
	min-width: 0;
	padding: 16px;
	overflow-wrap: anywhere;
}

.descriptionBlock {
	width: 100%;
}

.block h3 {
	margin: 0 0 12px;
	overflow-wrap: anywhere;
}

.copy {
	margin: 0;
	white-space: pre-wrap;
	line-height: 1.6;
	overflow-wrap: anywhere;
}

.dataTable {
	--diary-display-label-width: 180px;
	--diary-display-value-width: 100px;
	display: grid;
	gap: 8px;
	overflow-wrap: anywhere;
}

.dataLine {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(calc(var(--diary-display-label-width) + var(--diary-display-value-width)), 1fr));
	gap: 8px 20px;
	padding: 5px 7px;
	border-radius: var(--border-radius-small);
}

.dataLine:nth-child(odd) {
	background: color-mix(in srgb, var(--color-main-text) 5%, transparent);
}

.dataRow {
	display: grid;
	grid-template-columns: var(--diary-display-label-width) minmax(var(--diary-display-value-width), 1fr);
	gap: 8px;
	align-items: baseline;
	min-width: 0;
}

.dataLabel {
	color: var(--color-text-maxcontrast);
	font-weight: 600;
	overflow-wrap: anywhere;
}

.dataValue {
	min-width: 0;
	overflow-wrap: anywhere;
}

.empty {
	min-height: 260px;
}

@media (max-width: 640px) {
	.dataTable {
		grid-template-columns: 1fr;
	}

	.dataRow {
		grid-template-columns: minmax(0, 1fr);
		gap: 2px;
	}
}
</style>
