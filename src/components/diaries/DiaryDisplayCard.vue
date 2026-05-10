<script setup lang="ts">
import type { Diary, DiaryShare, DiaryStats } from '@/types/types'
import { formatDurationSeconds, frequencyLabel, formatTimeOnly } from '@/utils/format'
import { scheduleSecondsToDays } from '@/utils/diary'
import { n, t } from '@nextcloud/l10n'

const props = defineProps<{
	diary: Diary | null
	shares: DiaryShare[]
	stats: DiaryStats | null
	hideStats?: boolean
}>()
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

			<div :class="$style.grid">
				<article :class="['workspace-card-subcard', $style.block]">
					<h3>{{ t('structureddiary', 'Description') }}</h3>
					<p :class="$style.copy">{{ props.diary.description || t('structureddiary', 'No description.') }}</p>
				</article>

				<article :class="['workspace-card-subcard', $style.block]">
					<h3>{{ t('structureddiary', 'Schedule') }}</h3>
					<ul :class="$style.definitionList">
						<li>{{ t('structureddiary', 'Target cadence: {cadence}', {cadence: n('structureddiary', '%n day', '%n days', scheduleSecondsToDays(props.diary.entry_schedule))}) }}</li>
						<li>{{ t('structureddiary', 'Reminder: {state}', {state: props.diary.reminder_active ? t('structureddiary', 'Active') : t('structureddiary', 'Disabled')}) }}</li>
						<li>{{ t('structureddiary', 'Reminder time: {time}', {time: formatTimeOnly(props.diary.reminder_time)}) }}</li>
						<li>{{ t('structureddiary', 'Repeat count: {count}', {count: props.diary.reminder_count}) }}</li>
						<li>{{ t('structureddiary', 'Repeat delay: {delay}', {delay: formatDurationSeconds(props.diary.reminder_delay)}) }}</li>
						<li>{{ t('structureddiary', 'First signal: {signal}', {signal: props.diary.reminder_signal_first || t('structureddiary', 'n/a')}) }}</li>
						<li>{{ t('structureddiary', 'Repeat signal: {signal}', {signal: props.diary.reminder_signal_repeat || t('structureddiary', 'n/a')}) }}</li>
					</ul>
				</article>

				<article :class="['workspace-card-subcard', $style.block]">
					<h3>{{ t('structureddiary', 'Shares') }}</h3>
					<ul :class="$style.definitionList">
						<li v-if="props.shares.length === 0">{{ t('structureddiary', 'No shares configured.') }}</li>
						<li v-for="share in props.shares" :key="share.id">
							{{ t('structureddiary', '{user} · permission {permission}', {user: share.shared_with, permission: share.permission}) }}
						</li>
					</ul>
				</article>

				<article v-if="!props.hideStats" :class="['workspace-card-subcard', $style.block]">
					<h3>{{ t('structureddiary', 'Statistics') }}</h3>
					<div v-if="props.stats" :class="$style.statsGrid">
						<div>{{ t('structureddiary', 'Questions: {count}', {count: props.stats.question_count}) }}</div>
						<div>{{ t('structureddiary', 'Entries: {count}', {count: props.stats.entry_count}) }}</div>
						<div>{{ t('structureddiary', 'Answers: {count}', {count: props.stats.answer_count}) }}</div>
						<div>{{ t('structureddiary', 'Avg answers: {count}', {count: props.stats.average_answer_count.toFixed(2)}) }}</div>
						<div>{{ t('structureddiary', 'Frequency: {frequency}', {frequency: frequencyLabel(props.stats.entry_frequency)}) }}</div>
						<div>{{ t('structureddiary', 'Last month: {frequency}', {frequency: frequencyLabel(props.stats.entry_frequency_last_month)}) }}</div>
						<div>{{ t('structureddiary', 'Avg duration: {duration}', {duration: formatDurationSeconds(props.stats.average_entry_duration ?? undefined)}) }}</div>
						<div>{{ t('structureddiary', 'Avg duration last month: {duration}', {duration: formatDurationSeconds(props.stats.average_entry_duration_last_month ?? undefined)}) }}</div>
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
}

.owner {
	margin-top: 8px;
}

.grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 16px;
}

.block {
	min-width: 0;
	padding: 16px;
}

.block h3 {
	margin: 0 0 12px;
}

.copy {
	margin: 0;
	white-space: pre-wrap;
	line-height: 1.6;
}

.definitionList {
	margin: 0;
	padding-left: 18px;
	display: grid;
	gap: 8px;
}

.statsGrid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 10px;
}

.empty {
	min-height: 260px;
}

@media (max-width: 900px) {
	.grid,
	.statsGrid {
		grid-template-columns: 1fr;
	}
}
</style>
