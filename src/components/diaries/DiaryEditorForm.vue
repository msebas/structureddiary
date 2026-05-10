<script setup lang="ts">
import { reactive, watch } from 'vue'
import type { Diary, DiaryUpdatePayload } from '@/types/types'
import { dayTimeToSeconds, daysToScheduleSeconds, scheduleSecondsToDays, secondsToDayTime } from '@/utils/diary'
import { t } from '@nextcloud/l10n'

const props = defineProps<{
	diary: Diary | null
	canChangeOwner: boolean
}>()

const emit = defineEmits<{
	(event: 'save', payload: DiaryUpdatePayload): void
	(event: 'cancel'): void
}>()

const form = reactive({
	title: '',
	description: '',
	ownerUserId: '',
	entryScheduleDays: 1,
	reminderActive: false,
	reminderTime: '09:00',
	reminderCount: 3,
	reminderDelay: 2700,
	reminderSignalFirst: '',
	reminderSignalRepeat: '',
})

watch(() => props.diary, (diary) => {
	form.title = diary?.title ?? ''
	form.description = diary?.description ?? ''
	form.ownerUserId = diary?.user_id ?? ''
	form.entryScheduleDays = diary ? scheduleSecondsToDays(diary.entry_schedule) : 1
	form.reminderActive = diary?.reminder_active ?? false
	form.reminderTime = secondsToDayTime(diary?.reminder_time ?? 9 * 3600)
	form.reminderCount = diary?.reminder_count ?? 3
	form.reminderDelay = diary?.reminder_delay ?? 2700
	form.reminderSignalFirst = diary?.reminder_signal_first ?? ''
	form.reminderSignalRepeat = diary?.reminder_signal_repeat ?? ''
}, { immediate: true })

function submit(): void {
	emit('save', {
		title: form.title,
		description: form.description,
		ownerUserId: form.ownerUserId,
		entrySchedule: daysToScheduleSeconds(form.entryScheduleDays),
		reminderActive: form.reminderActive,
		reminderTime: dayTimeToSeconds(form.reminderTime),
		reminderCount: form.reminderCount,
		reminderDelay: form.reminderDelay,
		reminderSignalFirst: form.reminderSignalFirst,
		reminderSignalRepeat: form.reminderSignalRepeat,
	})
}
</script>

<template>
	<section :class="$style.form">
		<h2>{{ props.diary ? t('structureddiary', 'Edit diary') : t('structureddiary', 'Create diary') }}</h2>

		<label :class="$style.field">
			<span>{{ t('structureddiary', 'Title') }}</span>
			<input v-model="form.title" type="text">
		</label>

		<label :class="$style.field">
			<span>{{ t('structureddiary', 'Description') }}</span>
			<textarea v-model="form.description" rows="5" />
		</label>

		<label :class="$style.field">
			<span>{{ t('structureddiary', 'Owner') }}</span>
			<input v-model="form.ownerUserId" type="text" :disabled="!props.canChangeOwner">
		</label>

		<div :class="$style.grid">
			<label :class="$style.field">
				<span>{{ t('structureddiary', 'Cadence in days') }}</span>
				<input v-model.number="form.entryScheduleDays" type="number" min="0.5" step="0.5">
			</label>

			<label :class="$style.field">
				<span>{{ t('structureddiary', 'Reminder active') }}</span>
				<input v-model="form.reminderActive" type="checkbox">
			</label>
		</div>

		<div v-if="form.reminderActive" :class="$style.gridWide">
			<label :class="$style.field">
				<span>{{ t('structureddiary', 'Reminder time') }}</span>
				<input v-model="form.reminderTime" type="time">
			</label>
			<label :class="$style.field">
				<span>{{ t('structureddiary', 'Repeat count') }}</span>
				<input v-model.number="form.reminderCount" type="number" min="0" step="1">
			</label>
			<label :class="$style.field">
				<span>{{ t('structureddiary', 'Repeat delay (seconds)') }}</span>
				<input v-model.number="form.reminderDelay" type="number" min="0" step="60">
			</label>
			<label :class="$style.field">
				<span>{{ t('structureddiary', 'First signal') }}</span>
				<input v-model="form.reminderSignalFirst" type="text">
			</label>
			<label :class="$style.field">
				<span>{{ t('structureddiary', 'Repeat signal') }}</span>
				<input v-model="form.reminderSignalRepeat" type="text">
			</label>
		</div>

		<div :class="$style.actions">
			<button type="button" :class="$style.secondaryButton" @click="emit('cancel')">
				{{ t('structureddiary', 'Cancel') }}
			</button>
			<button type="button" :class="$style.primaryButton" @click="submit()">
				{{ t('structureddiary', 'Save diary') }}
			</button>
		</div>
	</section>
</template>

<style module>
.form {
	display: grid;
	gap: 16px;
	width: 100%;
	padding: 22px;
	box-sizing: border-box;
	border-radius: 24px;
	background: rgba(255, 255, 255, 0.98);
	box-shadow: 0 20px 48px rgba(12, 25, 46, 0.09);
}

.field {
	display: grid;
	gap: 8px;
	min-width: 0;
}

.field input,
.field textarea {
	width: 100%;
	box-sizing: border-box;
	border: 1px solid rgba(16, 37, 66, 0.15);
	border-radius: 14px;
	padding: 12px 14px;
}

.grid,
.gridWide {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 14px;
}

.gridWide {
	grid-template-columns: repeat(3, minmax(0, 1fr));
}

.actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
}

.primaryButton,
.secondaryButton {
	border: 0;
	border-radius: 999px;
	padding: 10px 14px;
	font-weight: 700;
	cursor: pointer;
}

.primaryButton {
	background: #d96941;
	color: white;
}

.secondaryButton {
	background: rgba(16, 37, 66, 0.08);
}

@media (max-width: 900px) {
	.grid,
	.gridWide {
		grid-template-columns: 1fr;
	}
}
</style>
