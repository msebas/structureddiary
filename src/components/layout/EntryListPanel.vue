<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiCheck } from '@mdi/js'
import { computed } from 'vue'
import type { Entry } from '@/types/types'
import { formatDate, formatDateTime } from '@/utils/format'

const props = defineProps<{
	entries: Entry[]
	selectedEntryId: number | null
	fromValue: string
	untilValue: string
	showCreateButton?: boolean
}>()

const emit = defineEmits<{
	(event: 'select', entry: Entry): void
	(event: 'create'): void
	(event: 'update:fromValue', value: string): void
	(event: 'update:untilValue', value: string): void
	(event: 'applyFilter'): void
}>()

const duplicateDays = computed(() => {
	const counts = new Map<string, number>()
	for (const entry of props.entries) {
		const key = formatDate(entry.timestamp)
		counts.set(key, (counts.get(key) ?? 0) + 1)
	}
	return counts
})
</script>

<template>
	<aside :class="$style.panel">
		<div v-if="props.showCreateButton" :class="$style.actions">
			<NcButton @click="emit('create')">
				New entry
			</NcButton>
		</div>
		<div :class="$style.filters">
			<label :class="$style.field">
				<span :class="$style.fieldLabel">From</span>
				<input
					:value="props.fromValue"
					type="date"
					:class="['nc-input-field__input', $style.input]"
					@input="emit('update:fromValue', ($event.target as HTMLInputElement).value)">
			</label>
			<label :class="$style.field">
				<span :class="$style.fieldLabel">Until</span>
				<input
					:value="props.untilValue"
					type="date"
					:class="['nc-input-field__input', $style.input]"
					@input="emit('update:untilValue', ($event.target as HTMLInputElement).value)">
			</label>
			<NcButton
				variant="secondary"
				aria-label="Apply filter"
				:class="$style.applyButton"
				@click="emit('applyFilter')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiCheck" />
				</template>
			</NcButton>
		</div>
		<div :class="$style.list">
			<button
				v-for="entry in props.entries"
				:key="entry.id"
				type="button"
				:class="[$style.item, entry.id === props.selectedEntryId && $style.itemActive]"
				@click="emit('select', entry)">
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
</style>
