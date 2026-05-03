<script setup lang="ts">
import type { Diary, DiaryShare, DiaryStats } from '@/types/types'
import { formatDurationSeconds, frequencyLabel, formatTimeOnly } from '@/utils/format'
import { scheduleSecondsToDays } from '@/utils/diary'

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
					<div :class="['workspace-card-muted', $style.owner]">Owner: {{ props.diary.user_id }}</div>
				</div>
			</header>

			<div :class="$style.grid">
				<article :class="['workspace-card-subcard', $style.block]">
					<h3>Description</h3>
					<p :class="$style.copy">{{ props.diary.description || 'No description.' }}</p>
				</article>

				<article :class="['workspace-card-subcard', $style.block]">
					<h3>Schedule</h3>
					<ul :class="$style.definitionList">
						<li>Target cadence: {{ scheduleSecondsToDays(props.diary.entry_schedule) }} day(s)</li>
						<li>Reminder: {{ props.diary.reminder_active ? 'Active' : 'Disabled' }}</li>
						<li>Reminder time: {{ formatTimeOnly(props.diary.reminder_time) }}</li>
						<li>Repeat count: {{ props.diary.reminder_count }}</li>
						<li>Repeat delay: {{ formatDurationSeconds(props.diary.reminder_delay) }}</li>
						<li>First signal: {{ props.diary.reminder_signal_first || 'n/a' }}</li>
						<li>Repeat signal: {{ props.diary.reminder_signal_repeat || 'n/a' }}</li>
					</ul>
				</article>

				<article :class="['workspace-card-subcard', $style.block]">
					<h3>Shares</h3>
					<ul :class="$style.definitionList">
						<li v-if="props.shares.length === 0">No shares configured.</li>
						<li v-for="share in props.shares" :key="share.id">
							{{ share.shared_with }} · permission {{ share.permission }}
						</li>
					</ul>
				</article>

				<article v-if="!props.hideStats" :class="['workspace-card-subcard', $style.block]">
					<h3>Statistics</h3>
					<div v-if="props.stats" :class="$style.statsGrid">
						<div>Questions: {{ props.stats.question_count }}</div>
						<div>Entries: {{ props.stats.entry_count }}</div>
						<div>Answers: {{ props.stats.answer_count }}</div>
						<div>Avg answers: {{ props.stats.average_answer_count.toFixed(2) }}</div>
						<div>Frequency: {{ frequencyLabel(props.stats.entry_frequency) }}</div>
						<div>Last month: {{ frequencyLabel(props.stats.entry_frequency_last_month) }}</div>
						<div>Avg duration: {{ formatDurationSeconds(props.stats.average_entry_duration ?? undefined) }}</div>
						<div>Avg duration last month: {{ formatDurationSeconds(props.stats.average_entry_duration_last_month ?? undefined) }}</div>
					</div>
					<div v-else class="workspace-card-muted">
						Statistics not loaded yet.
					</div>
				</article>
        <div class="workspace-end-space"></div>
			</div>
		</template>

		<template v-else>
			<div :class="['workspace-card-empty', $style.empty]">Select a diary to inspect it here.</div>
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
