<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiCheck } from '@mdi/js'
import { computed } from 'vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import { formatDate, formatDateTime } from '@/utils/format'

const store = useStructuredDiaryStore()

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
	get: () => formatDateInputFromTimestamp(store.entryFromTimestamp),
	set: (value: string) => {
		store.entryFromTimestamp = timestampFromDateInput(value, false)
	},
})

const untilValue = computed({
	get: () => formatDateInputFromTimestamp(store.entryUntilTimestamp),
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

async function applyFilter(): Promise<void> {
	if (store.selectedDiaryId === null) {
		return
	}

	await store.loadEntries(store.selectedDiaryId, store.entryFromTimestamp, store.entryUntilTimestamp)
}
</script>

<template>
	<aside :class="$style.panel">
		<div :class="[$style.actions, $style.mobileActions]">
			<NcButton @click="store.startCreatingEntry(store.selectedDiaryId)">
				New entry
			</NcButton>
		</div>
		<div :class="$style.filters">
			<label :class="$style.field">
				<span :class="$style.fieldLabel">From</span>
				<input
					:value="fromValue"
					type="date"
					:class="['nc-input-field__input', $style.input]"
					@input="fromValue = ($event.target as HTMLInputElement).value">
			</label>
			<label :class="$style.field">
				<span :class="$style.fieldLabel">Until</span>
				<input
					:value="untilValue"
					type="date"
					:class="['nc-input-field__input', $style.input]"
					@input="untilValue = ($event.target as HTMLInputElement).value">
			</label>
			<NcButton
				variant="secondary"
				aria-label="Apply filter"
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
				:class="[$style.item, entry.id === store.selectedEntryId && $style.itemActive]"
				@click="store.selectedEntryId = entry.id">
				<strong>{{ entry.title || 'Untitled entry' }}</strong>
				<span>{{ duplicateDays.get(formatDate(entry.timestamp))! > 1 ? formatDateTime(entry.timestamp) : formatDate(entry.timestamp) }}</span>
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
	grid-template-columns: 1fr 1fr auto;
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
	display: grid;
	gap: 8px;
	overflow: auto;
}

.item {
	display: grid;
	gap: 4px;
	padding: 12px 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	text-align: left;
	cursor: pointer;
}

.item strong {
	color: var(--color-main-text);
}

.item span {
	font-size: 0.82rem;
	color: var(--color-text-maxcontrast);
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
