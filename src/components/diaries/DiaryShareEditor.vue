<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import type { DiaryShare } from '@/types/types'

const props = defineProps<{
	shares: DiaryShare[]
}>()

const emit = defineEmits<{
	(event: 'save', payload: Array<{ sharedWith: string, permission: number }>): void
	(event: 'cancel'): void
}>()

const state = reactive({
	readers: '' as string,
	writers: '' as string,
	managers: '' as string,
})

function stringify(permissionMask: number): string {
	return props.shares
		.filter((share) => (share.permission & permissionMask) === permissionMask)
		.map((share) => share.shared_with)
		.join(', ')
}

watch(() => props.shares, () => {
	state.readers = stringify(1)
	state.writers = stringify(3)
	state.managers = stringify(9)
}, { immediate: true })

const payload = computed(() => {
	const entries = new Map<string, number>()
	const assign = (values: string, permission: number): void => {
		values.split(',')
			.map((value) => value.trim())
			.filter(Boolean)
			.forEach((user) => {
				const previous = entries.get(user) ?? 0
				entries.set(user, previous | permission | 1)
			})
	}

	assign(state.readers, 1)
	assign(state.writers, 3)
	assign(state.managers, 9)

	return Array.from(entries.entries()).map(([sharedWith, permission]) => ({ sharedWith, permission }))
})
</script>

<template>
	<section :class="$style.editor">
		<h3>Share diary</h3>
		<label :class="$style.field">
			<span>Readers</span>
			<input v-model="state.readers" type="text" placeholder="alice, bob">
		</label>
		<label :class="$style.field">
			<span>Writers</span>
			<input v-model="state.writers" type="text" placeholder="alice, bob">
		</label>
		<label :class="$style.field">
			<span>Managers</span>
			<input v-model="state.managers" type="text" placeholder="alice, bob">
		</label>
		<div :class="$style.actions">
			<button type="button" :class="$style.secondaryButton" @click="emit('cancel')">
				Cancel
			</button>
			<button type="button" :class="$style.primaryButton" @click="emit('save', payload)">
				Save shares
			</button>
		</div>
	</section>
</template>

<style module>
.editor {
	display: grid;
	gap: 14px;
	width: 100%;
	padding: 18px;
	box-sizing: border-box;
	border-radius: 20px;
	background: rgba(246, 248, 252, 0.9);
}

.field {
	display: grid;
	gap: 8px;
	min-width: 0;
}

.field input {
	width: 100%;
	box-sizing: border-box;
	border: 1px solid rgba(16, 37, 66, 0.15);
	border-radius: 14px;
	padding: 12px 14px;
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
	background: #102542;
	color: white;
}

.secondaryButton {
	background: rgba(16, 37, 66, 0.08);
}
</style>
