<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiCheck } from '@mdi/js'
import { computed } from 'vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import { formatDate, formatDateTime, hasExplicitEntryTitle } from '@/utils/format'
import { t } from '@nextcloud/l10n'
import type { Entry } from '@/types/types'

const store = useStructuredDiaryStore()
const emit = defineEmits<{
	(event: 'open-center'): void
}>()

function formatDateInputValue(date: Date): string {
	const year = date.getFullYear()
	const month = String(date.getMonth() + 1).padStart(2, '0')
	const day = String(date.getDate()).padStart(2, '0')
	return `${year}-${month}-${day}`
}

function formatDateInputFromTimestamp(timestamp: number | null): string {
	if (timestamp === null) {
		return ''
	}

	return formatDateInputValue(new Date(timestamp * 1000))
}

function timestampFromDateInput(value: string, endOfDay: boolean): number | null {
	if (value === '') {
		return null
	}

	return Math.floor(new Date(`${value}T${endOfDay ? '23:59:59' : '00:00:00'}`).getTime() / 1000)
}

const fromValue = computed({
	get: () => formatDateInputFromTimestamp(store.effectiveEntryFromTimestamp),
	set: (value: string) => {
		store.entryFromTimestamp = timestampFromDateInput(value, false)
	},
})

const untilValue = computed({
	get: () => formatDateInputFromTimestamp(store.effectiveEntryUntilTimestamp),
	set: (value: string) => {
		store.entryUntilTimestamp = timestampFromDateInput(value, true)
	},
})

const duplicateDays = computed(() => {
	const counts = new Map<string, number>()
	for (const entry of store.currentEntries) {
		const key = formatDate(entry.timestamp)
		counts.set(key, (counts.get(key) ?? 0) + 1)
	}
	return counts
})

function formatEntryListTimestamp(entry: Entry): string {
	return (duplicateDays.value.get(formatDate(entry.timestamp)) ?? 0) > 1
		? formatDateTime(entry.timestamp)
		: formatDate(entry.timestamp)
}

function formatEntryListTitle(entry: Entry): string {
	return hasExplicitEntryTitle(entry) ? entry.title!.trim() : formatEntryListTimestamp(entry)
}

async function applyFilter(): Promise<void> {
	if (store.selectedDiaryId === null) {
		return
	}

	await store.loadEntries(store.selectedDiaryId, store.effectiveEntryFromTimestamp, store.effectiveEntryUntilTimestamp)
}

async function createEntry(): Promise<void> {
	await store.startCreatingEntry(store.selectedDiaryId)
	emit('open-center')
}

async function selectEntry(entryId: number): Promise<void> {
	if (store.selectedDiaryId === null) {
		return
	}

	await store.pushWorkspaceRoute({
		name: 'entry',
		params: { diaryId: store.selectedDiaryId, entryId },
	})
	await store.loadEntry(entryId)
	emit('open-center')
}

</script>

<template>
	<aside :class="$style.panel">
		<div :class="[$style.actions, $style.mobileActions]">
			<NcButton @click="createEntry()">
				{{ t('structureddiary', 'New entry') }}
			</NcButton>
		</div>
		<div :class="$style.filters">
			<label :class="$style.field">
				<span :class="$style.fieldLabel">{{ t('structureddiary', 'From') }}</span>
				<input
					:value="fromValue"
					type="date"
					:class="['nc-input-field__input', $style.input]"
					@input="fromValue = ($event.target as HTMLInputElement).value">
			</label>
			<label :class="$style.field">
				<span :class="$style.fieldLabel">{{ t('structureddiary', 'Until') }}</span>
				<input
					:value="untilValue"
					type="date"
					:class="['nc-input-field__input', $style.input]"
					@input="untilValue = ($event.target as HTMLInputElement).value">
			</label>
			<NcButton
				variant="secondary"
				:aria-label="t('structureddiary', 'Apply filter')"
				:class="$style.applyButton"
				@click="applyFilter()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiCheck" />
				</template>
			</NcButton>
		</div>
		<div :class="$style.list">
			<button
				v-for="entry in store.currentEntries"
				:key="entry.id"
				type="button"
				:class="$style.itemButton"
				@click="selectEntry(entry.id)">
				<span :class="[$style.item, entry.id === store.selectedEntryId && $style.itemActive]">
					<strong>{{ formatEntryListTitle(entry) }}</strong>
					<span v-if="hasExplicitEntryTitle(entry)">
						{{ formatEntryListTimestamp(entry) }}
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
	gap: 14px;
	padding: 18px;
	min-height: 0;
	height: 100%;
	overflow: hidden;
	background: var(--color-main-background);
}

.actions {
	display: flex;
	justify-content: flex-end;
}

.mobileActions {
	display: none;
}

.filters {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
	gap: 8px;
	align-items: end;
}

.field {
	display: grid;
	gap: 4px;
}

.fieldLabel {
	font-size: 0.8rem;
	color: var(--color-text-maxcontrast);
}

.input {
	width: 100%;
	min-height: 44px;
}

.applyButton {
	inline-size: 44px;
	block-size: 44px;
	padding: 0;
}

.list {
	flex: 1 1 auto;
	min-height: 0;
	overflow-x: hidden;
	overflow-y: auto;
}

.itemButton + .itemButton {
	margin-block-start: 8px;
}

.itemButton.itemButton {
	display: block;
	width: 100%;
  min-height: var(--default-clickable-area);
	padding: 0;
	font: inherit;
	text-align: left;
	white-space: normal;
	cursor: pointer;
}

.item {
	display: grid;
	grid-auto-rows: auto;
	align-content: start;
	gap: 4px;
	width: 100%;
	padding: 12px 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	text-align: left;
	line-height: 1.25;
	white-space: normal;
	box-sizing: border-box;
}

.item strong {
	display: block;
	min-width: 0;
	color: var(--color-main-text);
	line-height: 1.25;
	overflow-wrap: anywhere;
}

.item span {
	display: block;
	min-width: 0;
	font-size: 0.82rem;
	font-weight: 600;
	line-height: 1.25;
	color: var(--color-text-maxcontrast);
	overflow-wrap: anywhere;
}

.itemActive {
	border-color: var(--color-primary-element);
	background: var(--color-background-hover);
}

@media (max-width: 1080px) {
	.mobileActions {
		display: flex;
	}
}
</style>
