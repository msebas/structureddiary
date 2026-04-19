<script setup lang="ts">
import { computed } from 'vue'
import type { Entry } from '@/types/types'
import { formatDate, formatDateTime } from '@/utils/format'

const props = defineProps<{
	entries: Entry[]
	selectedEntryId: number | null
	fromValue: string
	untilValue: string
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
		<div :class="$style.actions">
			<button type="button" :class="$style.primaryButton" @click="emit('create')">
				New entry
			</button>
		</div>
		<div :class="$style.filters">
			<input
				:value="props.fromValue"
				type="date"
				:class="$style.input"
				@input="emit('update:fromValue', ($event.target as HTMLInputElement).value)">
			<input
				:value="props.untilValue"
				type="date"
				:class="$style.input"
				@input="emit('update:untilValue', ($event.target as HTMLInputElement).value)">
			<button type="button" :class="$style.filterButton" @click="emit('applyFilter')">
				Apply
			</button>
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
	background:
		radial-gradient(circle at top right, rgba(155, 204, 246, 0.24), transparent 42%),
		linear-gradient(180deg, #f8fbff, #eef3f8);
	border-left: 1px solid rgba(27, 41, 58, 0.12);
}

.actions {
	display: flex;
	justify-content: flex-end;
}

.primaryButton,
.filterButton {
	border: 0;
	border-radius: 14px;
	padding: 10px 14px;
	font-weight: 700;
	cursor: pointer;
}

.primaryButton {
	background: #d96941;
	color: white;
}

.filterButton {
	background: #102542;
	color: white;
}

.filters {
	display: grid;
	grid-template-columns: 1fr 1fr auto;
	gap: 8px;
}

.input {
	border: 1px solid rgba(16, 37, 66, 0.15);
	border-radius: 12px;
	padding: 10px 12px;
	background: rgba(255, 255, 255, 0.92);
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
	border: 1px solid transparent;
	border-radius: 16px;
	background: rgba(255, 255, 255, 0.72);
	text-align: left;
	cursor: pointer;
}

.item strong {
	color: #12253e;
}

.item span {
	font-size: 0.82rem;
	color: #66768a;
}

.itemActive {
	border-color: rgba(16, 37, 66, 0.18);
	box-shadow: 0 10px 24px rgba(16, 37, 66, 0.08);
	background: rgba(255, 255, 255, 0.95);
}
</style>

