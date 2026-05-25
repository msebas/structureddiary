<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import type { NcSelectUsersModel } from '@nextcloud/vue/components/NcSelectUsers'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { alarmSoundService, userService } from '@/services'
import type { AlarmSound, Diary, DiaryUpdatePayload, SelectOption } from '@/types/types'
import { dayTimeToSeconds, daysToScheduleSeconds, scheduleSecondsToDays, secondsToDayTime } from '@/utils/diary'
import { n, t } from '@nextcloud/l10n'

const props = defineProps<{
	diary: Diary | null
	initialDraft: DiaryUpdatePayload | null
	isCreating: boolean
	canChangeOwner: boolean
	entryCount: number | null
}>()

const emit = defineEmits<{
	(event: 'save', payload: DiaryUpdatePayload): void
	(event: 'cancel'): void
	(event: 'duplicate'): void
	(event: 'delete'): void
}>()

const deleteDialogOpen = ref(false)
const owner = ref<NcSelectUsersModel | null>(null)
const userOptions = ref<NcSelectUsersModel[]>([])
const loadingUsers = ref(false)
const alarmSounds = ref<AlarmSound[]>([])

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

const cadenceOptions = computed(() => [
	{ label: t('structureddiary', '1/2 day'), value: 0.5 },
	{ label: n('structureddiary', '%n day', '%n days', 1), value: 1 },
	{ label: n('structureddiary', '%n day', '%n days', 2), value: 2 },
	{ label: n('structureddiary', '%n day', '%n days', 3), value: 3 },
	{ label: n('structureddiary', '%n day', '%n days', 7), value: 7 },
])

const reminderMax = computed(() => form.entryScheduleDays === 0.5 ? '12:00' : '23:59')
const deleteLabel = computed(() => {
	if (props.entryCount === null) {
		return t('structureddiary', 'Delete diary')
	}
	return t('structureddiary', 'Delete diary ({count})', {count: n('structureddiary', '%n entry', '%n entries', props.entryCount)})
})
const deleteMessage = computed(() => {
	const countLabel = props.entryCount === null ? t('structureddiary', 'an unknown number of entries') : n('structureddiary', '%n entry', '%n entries', props.entryCount)
	return t('structureddiary', 'Delete this diary with {countLabel}?', {countLabel})
})
const deleteDialogButtons = computed(() => [
	{
		label: t('structureddiary', 'Cancel'),
		callback: () => undefined,
	},
	{
		label: t('structureddiary', 'Delete diary'),
		variant: 'error' as const,
		callback: () => {
			emit('delete')
		},
	},
])
const alarmSoundOptions = computed(() => {
	const options = alarmSounds.value.map((sound) => ({
		value: alarmSoundValue(sound),
		label: alarmSoundLabel(sound),
	}))
	for (const value of [form.reminderSignalFirst, form.reminderSignalRepeat]) {
		if (value !== '' && !options.some((option) => option.value === value)) {
			options.push({
				value,
				label: value,
			})
		}
	}

	return options
})

function alarmSoundValue(sound: AlarmSound): string {
	return sound.path ?? sound.name
}

function alarmSoundLabel(sound: AlarmSound): string {
	const osLabel = sound.os_affinity.join(', ')
	const suffix = osLabel === '' ? '' : ` (${osLabel})`
	return `${sound.name}${suffix}`
}

function toUserModel(option: SelectOption<string>): NcSelectUsersModel {
	return {
		id: option.value,
		user: option.value,
		displayName: option.label,
	}
}

function fromUserId(userId: string): NcSelectUsersModel {
	return {
		id: userId,
		user: userId,
		displayName: userId,
	}
}

function upsertUserOptions(nextItems: NcSelectUsersModel[]): void {
	const merged = new Map(userOptions.value.map((item) => [item.id, item]))
	for (const item of nextItems) {
		merged.set(item.id, item)
	}
	userOptions.value = Array.from(merged.values())
}

async function searchUsers(query: string): Promise<void> {
	loadingUsers.value = true
	try {
		const matches = await userService.search(query)
		upsertUserOptions(matches.map(toUserModel))
	} finally {
		loadingUsers.value = false
	}
}

async function loadAlarmSounds(): Promise<void> {
	try {
		alarmSounds.value = await alarmSoundService.list()
	} catch {
		alarmSounds.value = []
	}
}

function currentDraft(): DiaryUpdatePayload {
	return {
		title: form.title.trim(),
		description: form.description,
		ownerUserId: owner.value?.id ?? form.ownerUserId,
		entrySchedule: daysToScheduleSeconds(form.entryScheduleDays),
		reminderActive: form.reminderActive,
		reminderTime: dayTimeToSeconds(form.reminderTime),
		reminderCount: form.reminderCount,
		reminderDelay: form.reminderDelay,
		reminderSignalFirst: form.reminderSignalFirst,
		reminderSignalRepeat: form.reminderSignalRepeat,
	}
}

function confirmDelete(): void {
	deleteDialogOpen.value = true
}

watch(() => [props.diary, props.initialDraft, props.isCreating] as const, ([currentDiary, draft, creating]) => {
	const source = creating && draft ? draft : null
	form.title = source?.title ?? currentDiary?.title ?? ''
	form.description = source?.description ?? currentDiary?.description ?? ''
	form.ownerUserId = source?.ownerUserId ?? currentDiary?.user_id ?? ''
	form.entryScheduleDays = source?.entrySchedule ? scheduleSecondsToDays(source.entrySchedule) : currentDiary ? scheduleSecondsToDays(currentDiary.entry_schedule) : 1
	form.reminderActive = source?.reminderActive ?? currentDiary?.reminder_active ?? false
	form.reminderTime = secondsToDayTime(source?.reminderTime ?? currentDiary?.reminder_time ?? 9 * 3600)
	form.reminderCount = source?.reminderCount ?? currentDiary?.reminder_count ?? 3
	form.reminderDelay = source?.reminderDelay ?? currentDiary?.reminder_delay ?? 2700
	form.reminderSignalFirst = source?.reminderSignalFirst ?? currentDiary?.reminder_signal_first ?? ''
	form.reminderSignalRepeat = source?.reminderSignalRepeat ?? currentDiary?.reminder_signal_repeat ?? ''
	owner.value = form.ownerUserId === '' ? null : fromUserId(form.ownerUserId)
	upsertUserOptions(owner.value ? [owner.value] : [])
}, { immediate: true })

watch(() => form.entryScheduleDays, (days) => {
	if (days === 0.5 && form.reminderTime > '12:00') {
		form.reminderTime = '09:00'
	}
})

onMounted(() => {
	void loadAlarmSounds()
})
</script>

<template>
	<section class="workspace-card workspace-card--form">
		<h2 :class="$style.heading">{{ isCreating ? t('structureddiary', 'Create diary') : t('structureddiary', 'Edit diary') }}</h2>

		<div :class="$style.field">
			<NcTextField
				:model-value="form.title"
				:label="t('structureddiary', 'Title')"
				@update:model-value="form.title = String($event)" />
		</div>

		<div :class="$style.field">
			<NcTextArea
				:model-value="form.description"
				:label="t('structureddiary', 'Description')"
				:helper-text="t('structureddiary', 'Markdown is supported.')"
				resize="vertical"
				@update:model-value="form.description = $event" />
			<div v-if="form.description.trim() !== ''" :class="$style.preview">
				<div :class="$style.previewLabel">{{ t('structureddiary', 'Preview') }}</div>
				<NcRichText :text="form.description" :use-markdown="true" :use-extended-markdown="true" />
			</div>
		</div>

		<div :class="$style.field">
			<label :class="$style.label">{{ t('structureddiary', 'Owner') }}</label>
			<NcSelectUsers
				:model-value="owner"
				:options="userOptions"
				:loading="loadingUsers"
				:input-label="t('structureddiary', 'Owner')"
				:placeholder="t('structureddiary', 'Select owner')"
				:disabled="!canChangeOwner"
				:label-outside="true"
				@search="searchUsers"
				@update:model-value="owner = Array.isArray($event) ? ($event[0] ?? null) : $event" />
		</div>

		<div :class="$style.grid">
			<label :class="$style.field">
				<span :class="$style.label">{{ t('structureddiary', 'Entry cadence in days') }}</span>
				<select v-model.number="form.entryScheduleDays" :class="['nc-input-field__input', $style.nativeInput]">
					<option v-for="option in cadenceOptions" :key="option.value" :value="option.value">
						{{ option.label }}
					</option>
				</select>
			</label>

			<div :class="$style.switchField">
				<span :class="$style.label">{{ t('structureddiary', 'Reminder active') }}</span>
				<NcCheckboxRadioSwitch
					type="switch"
					:model-value="form.reminderActive"
					@update:model-value="form.reminderActive = Boolean($event)">
					{{ form.reminderActive ? t('structureddiary', 'Enabled') : t('structureddiary', 'Disabled') }}
				</NcCheckboxRadioSwitch>
			</div>
		</div>

		<div v-if="form.reminderActive" :class="$style.gridWide">
			<label :class="$style.field">
				<span :class="$style.label">{{ t('structureddiary', 'Reminder time') }}</span>
				<input
					v-model="form.reminderTime"
					type="time"
					:max="reminderMax"
					:class="['nc-input-field__input', $style.nativeInput]">
			</label>

			<NcTextField
				:model-value="String(form.reminderCount)"
				:label="t('structureddiary', 'Repeat count')"
				type="number"
				@update:model-value="form.reminderCount = Number($event)" />

			<NcTextField
				:model-value="String(form.reminderDelay)"
				:label="t('structureddiary', 'Repeat delay (seconds)')"
				type="number"
				@update:model-value="form.reminderDelay = Number($event)" />

			<label :class="$style.field">
				<span :class="$style.label">{{ t('structureddiary', 'First signal') }}</span>
				<select
					v-model="form.reminderSignalFirst"
					:class="['nc-input-field__input', $style.nativeInput]">
					<option value="">
						{{ t('structureddiary', 'No signal') }}
					</option>
					<option v-for="option in alarmSoundOptions" :key="`first-${option.value}`" :value="option.value">
						{{ option.label }}
					</option>
				</select>
			</label>

			<label :class="$style.field">
				<span :class="$style.label">{{ t('structureddiary', 'Repeat signal') }}</span>
				<select
					v-model="form.reminderSignalRepeat"
					:class="['nc-input-field__input', $style.nativeInput]">
					<option value="">
						{{ t('structureddiary', 'No signal') }}
					</option>
					<option v-for="option in alarmSoundOptions" :key="`repeat-${option.value}`" :value="option.value">
						{{ option.label }}
					</option>
				</select>
			</label>
		</div>

		<div :class="$style.footer">
			<div>
				<NcButton
					v-if="!isCreating && diary"
					variant="error"
					@click="confirmDelete()">
					{{ deleteLabel }}
				</NcButton>
			</div>

			<div :class="$style.actions">
				<NcButton
					v-if="!isCreating && diary"
					variant="secondary"
					@click="emit('duplicate')">
					{{ t('structureddiary', 'Copy diary') }}
				</NcButton>
				<NcButton
					variant="secondary"
					@click="emit('cancel')">
					{{ t('structureddiary', 'Cancel') }}
				</NcButton>
				<NcButton @click="emit('save', currentDraft())">
					{{ isCreating ? t('structureddiary', 'Create diary') : t('structureddiary', 'Save diary') }}
				</NcButton>
			</div>
		</div>

		<NcDialog
			v-model:open="deleteDialogOpen"
			:name="t('structureddiary', 'Delete diary')"
			:message="deleteMessage"
			:buttons="deleteDialogButtons"
			size="small" />
	</section>
</template>

<style module>
.heading {
	margin: 0;
	font-size: 1.6rem;
}

.field {
	display: grid;
	gap: 8px;
	min-width: 0;
}

.label {
	font-size: 0.9rem;
	font-weight: 600;
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

.switchField {
	display: grid;
	align-content: start;
	gap: 8px;
}

.nativeInput {
	width: 100%;
	min-height: 44px;
	box-sizing: border-box;
}

.preview {
	display: grid;
	gap: 8px;
	padding: 12px 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-dark);
}

.previewLabel {
	font-size: 0.8rem;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.06em;
}

.footer {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 10px;
}

@media (max-width: 900px) {
	.grid,
	.gridWide {
		grid-template-columns: 1fr;
	}
}
</style>
