<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import type { NcSelectUsersModel } from '@nextcloud/vue/components/NcSelectUsers'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { userService } from '@/services'
import { type DiaryEditSubmitPayload, type DiaryShareInput, useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { DiaryUpdatePayload, SelectOption } from '@/types/types'
import { dayTimeToSeconds, daysToScheduleSeconds, scheduleSecondsToDays, secondsToDayTime } from '@/utils/diary'

const signalSuggestions = [
	'Default',
	'Bell',
	'Chime',
	'Digital',
	'Signal',
	'Vibrate',
]

const store = useStructuredDiaryStore()
const route = useRoute()
const router = useRouter()
const copiedDiaryPayload = ref<DiaryEditSubmitPayload | null>(null)

const diary = computed(() => store.selectedDiary)
const shares = computed(() => Object.values(store.selectedDiaryShares))
const isCreating = computed(() => store.creatingDiary)
const initialDraft = computed(() => copiedDiaryPayload.value?.diary ?? null)
const entryCount = computed(() => store.selectedDiaryStats?.entry_count ?? null)
const canChangeOwner = computed(() => isCreating.value || entryCount.value === 0)

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

const readers = ref<NcSelectUsersModel[]>([])
const writers = ref<NcSelectUsersModel[]>([])
const managers = ref<NcSelectUsersModel[]>([])
const owner = ref<NcSelectUsersModel | null>(null)
const userOptions = ref<NcSelectUsersModel[]>([])
const shareWarning = ref<string | null>(null)
const loadingUsers = ref(false)

const cadenceOptions = [
	{ label: '1/2 day', value: 0.5 },
	{ label: '1 day', value: 1 },
	{ label: '2 days', value: 2 },
	{ label: '3 days', value: 3 },
	{ label: '7 days', value: 7 },
]

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

function shareUsers(permissionMask: number): NcSelectUsersModel[] {
	return shares.value
		.filter((share) => (share.permission & permissionMask) === permissionMask)
		.map((share) => fromUserId(share.shared_with))
}

function normalizeSelection(items: NcSelectUsersModel[]): NcSelectUsersModel[] {
	const seen = new Set<string>()
	return items.filter((item) => {
		if (seen.has(item.id)) {
			return false
		}
		seen.add(item.id)
		return true
	})
}

function ensureReadersIncludeElevated(): void {
	const requiredReaders = [...writers.value, ...managers.value]
	const readerIds = new Set(readers.value.map((item) => item.id))
	const missingReaders = requiredReaders.filter((item) => !readerIds.has(item.id))
	if (missingReaders.length === 0) {
		return
	}

	readers.value = normalizeSelection([...readers.value, ...missingReaders])
	shareWarning.value = 'Write and manage access imply read access. Missing readers were added again.'
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

const sharePayload = computed<DiaryShareInput[]>(() => {
	const entries = new Map<string, number>()
	for (const reader of readers.value) {
		entries.set(reader.id, 1)
	}
	for (const writer of writers.value) {
		entries.set(writer.id, (entries.get(writer.id) ?? 0) | 3)
	}
	for (const manager of managers.value) {
		entries.set(manager.id, (entries.get(manager.id) ?? 0) | 9)
	}

	return Array.from(entries.entries()).map(([sharedWith, permission]) => ({ sharedWith, permission }))
})

const reminderMax = computed(() => form.entryScheduleDays === 0.5 ? '12:00' : '23:59')
const deleteLabel = computed(() => {
	if (entryCount.value === null) {
		return 'Delete diary'
	}
	return `Delete diary (${entryCount.value} entries)`
})

watch(() => [diary.value, initialDraft.value, isCreating.value, shares.value] as const, ([currentDiary, draft, creating]) => {
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
	readers.value = shareUsers(1)
	writers.value = shareUsers(3)
	managers.value = shareUsers(9)
	shareWarning.value = null
	upsertUserOptions([
		...(owner.value ? [owner.value] : []),
		...readers.value,
		...writers.value,
		...managers.value,
	])
}, { immediate: true })

watch(() => route.name, (routeName) => {
	if (routeName !== 'diaryCreate') {
		copiedDiaryPayload.value = null
	}
})

watch([writers, managers], () => {
	ensureReadersIncludeElevated()
}, { deep: true })

watch(() => form.entryScheduleDays, (days) => {
	if (days === 0.5 && form.reminderTime > '12:00') {
		form.reminderTime = '09:00'
	}
})

async function submit(): Promise<void> {
	const currentDiary = diary.value
	const savePayload: DiaryEditSubmitPayload = {
		diaryId: isCreating.value ? null : currentDiary?.id ?? null,
		diary: currentDraft(),
		shares: sharePayload.value,
		questions: copiedDiaryPayload.value?.questions ?? null,
	}

	const savedDiary = await store.saveDiary(savePayload)
	await router.push({
		name: 'diary',
		params: { diaryId: savedDiary.id },
		query: store.routeQueryFor('diary'),
	})
}

async function duplicate(): Promise<void> {
	if (diary.value === null) {
		return
	}

	copiedDiaryPayload.value = await store.copyDiary(diary.value.id)
}

function confirmDelete(): void {
	const countLabel = entryCount.value === null ? 'an unknown number of entries' : `${entryCount.value} entries`
	if (window.confirm(`Delete this diary with ${countLabel}?`)) {
		void deleteDiary()
	}
}

async function cancelEdit(): Promise<void> {
	if (isCreating.value) {
		await store.cancelCreateDiary()
		return
	}
	if (diary.value !== null) {
		await router.push({
			name: 'diary',
			params: { diaryId: diary.value.id },
			query: store.routeQueryFor('diary'),
		})
	}
}

async function deleteDiary(): Promise<void> {
	await store.deleteDiary(diary.value?.id ?? null)
}
</script>

<template>
	<section :class="$style.view">
		<section :class="$style.card">
			<h2 :class="$style.heading">Share diary</h2>

			<NcNoteCard v-if="shareWarning" type="warning">
				{{ shareWarning }}
			</NcNoteCard>

			<div :class="$style.field">
				<label :class="$style.label">Readers</label>
				<NcSelectUsers
					:model-value="readers"
					:options="userOptions"
					:loading="loadingUsers"
					input-label="Readers"
					placeholder="Select readers"
					:multiple="true"
					:label-outside="true"
					@search="searchUsers"
					@update:model-value="readers = Array.isArray($event) ? normalizeSelection($event) : []" />
			</div>

			<div :class="$style.field">
				<label :class="$style.label">Writers</label>
				<NcSelectUsers
					:model-value="writers"
					:options="userOptions"
					:loading="loadingUsers"
					input-label="Writers"
					placeholder="Select writers"
					:multiple="true"
					:label-outside="true"
					@search="searchUsers"
					@update:model-value="writers = Array.isArray($event) ? normalizeSelection($event) : []" />
			</div>

			<div :class="$style.field">
				<label :class="$style.label">Managers</label>
				<NcSelectUsers
					:model-value="managers"
					:options="userOptions"
					:loading="loadingUsers"
					input-label="Managers"
					placeholder="Select managers"
					:multiple="true"
					:label-outside="true"
					@search="searchUsers"
					@update:model-value="managers = Array.isArray($event) ? normalizeSelection($event) : []" />
			</div>
		</section>

		<section :class="$style.card">
			<h2 :class="$style.heading">{{ isCreating ? 'Create diary' : 'Edit diary' }}</h2>

			<div :class="$style.field">
				<NcTextField
					:model-value="form.title"
					label="Title"
					@update:model-value="form.title = String($event)" />
			</div>

			<div :class="$style.field">
				<NcTextArea
					:model-value="form.description"
					label="Description"
					helper-text="Markdown is supported."
					resize="vertical"
					@update:model-value="form.description = $event" />
				<div v-if="form.description.trim() !== ''" :class="$style.preview">
					<div :class="$style.previewLabel">Preview</div>
					<NcRichText :text="form.description" :use-markdown="true" :use-extended-markdown="true" />
				</div>
			</div>

			<div :class="$style.field">
				<label :class="$style.label">Owner</label>
				<NcSelectUsers
					:model-value="owner"
					:options="userOptions"
					:loading="loadingUsers"
					input-label="Owner"
					placeholder="Select owner"
					:disabled="!canChangeOwner"
					:label-outside="true"
					@search="searchUsers"
					@update:model-value="owner = Array.isArray($event) ? ($event[0] ?? null) : $event" />
			</div>

			<div :class="$style.grid">
				<label :class="$style.field">
					<span :class="$style.label">Entry cadence in days</span>
					<select v-model.number="form.entryScheduleDays" :class="['nc-input-field__input', $style.nativeInput]">
						<option v-for="option in cadenceOptions" :key="option.value" :value="option.value">
							{{ option.label }}
						</option>
					</select>
				</label>

				<div :class="$style.switchField">
					<span :class="$style.label">Reminder active</span>
					<NcCheckboxRadioSwitch
						type="switch"
						:model-value="form.reminderActive"
						@update:model-value="form.reminderActive = Boolean($event)">
						{{ form.reminderActive ? 'Enabled' : 'Disabled' }}
					</NcCheckboxRadioSwitch>
				</div>
			</div>

			<div v-if="form.reminderActive" :class="$style.gridWide">
				<label :class="$style.field">
					<span :class="$style.label">Reminder time</span>
					<input
						v-model="form.reminderTime"
						type="time"
						:max="reminderMax"
						:class="['nc-input-field__input', $style.nativeInput]">
				</label>

				<NcTextField
					:model-value="String(form.reminderCount)"
					label="Repeat count"
					type="number"
					@update:model-value="form.reminderCount = Number($event)" />

				<NcTextField
					:model-value="String(form.reminderDelay)"
					label="Repeat delay (seconds)"
					type="number"
					@update:model-value="form.reminderDelay = Number($event)" />

				<label :class="$style.field">
					<span :class="$style.label">First signal</span>
					<input
						v-model="form.reminderSignalFirst"
						list="structured-diary-signal-list"
						type="text"
						:class="['nc-input-field__input', $style.nativeInput]">
				</label>

				<label :class="$style.field">
					<span :class="$style.label">Repeat signal</span>
					<input
						v-model="form.reminderSignalRepeat"
						list="structured-diary-signal-list"
						type="text"
						:class="['nc-input-field__input', $style.nativeInput]">
				</label>
			</div>

			<datalist id="structured-diary-signal-list">
				<option v-for="signal in signalSuggestions" :key="signal" :value="signal" />
			</datalist>

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
						@click="duplicate()">
						Copy diary
					</NcButton>
					<NcButton
						variant="secondary"
						@click="cancelEdit()">
						Cancel
					</NcButton>
					<NcButton @click="submit()">
						{{ isCreating ? 'Create diary' : 'Save diary' }}
					</NcButton>
				</div>
			</div>
		</section>
	</section>
</template>

<style module>
.view {
	display: grid;
	gap: 16px;
}

.card {
	display: grid;
	gap: 16px;
	width: 100%;
	padding: 22px;
	box-sizing: border-box;
	border-radius: 24px;
	background: var(--color-main-background);
	box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
}

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
	.gridWide,
	.footer {
		grid-template-columns: 1fr;
		flex-direction: column;
		align-items: stretch;
	}

	.actions {
		justify-content: stretch;
	}
}
</style>
