<script setup lang="ts">
import type { Diary, Entry, Question } from '@/types/types'

const props = defineProps<{
	diary: Diary | null
	entry: Entry | null
	question: Question | null
	view: string
}>()

const emit = defineEmits<{
	(event: 'openDiary'): void
	(event: 'newDiary'): void
	(event: 'newEntry'): void
	(event: 'editEntry'): void
	(event: 'editDiary'): void
	(event: 'editQuestion'): void
}>()
</script>

<template>
	<header :class="$style.header">
		<div :class="$style.leading">
			<button type="button" :class="$style.secondaryButton" @click="emit('openDiary')">
				Diary
			</button>
			<h1 :class="$style.title">
				{{ props.diary?.title ?? 'Structured Diary' }}
			</h1>
		</div>

		<div :class="$style.actions">
			<button
				type="button"
				:class="$style.secondaryButton"
				@click="emit('newDiary')">
				New diary
			</button>
			<button
				v-if="props.view !== 'entry-edit'"
				type="button"
				:class="$style.primaryButton"
				@click="emit('newEntry')">
				New entry
			</button>
			<button
				v-if="props.entry && props.view !== 'entry-edit'"
				type="button"
				:class="$style.secondaryButton"
				@click="emit('editEntry')">
				Edit entry
			</button>
			<button
				v-if="props.diary"
				type="button"
				:class="$style.secondaryButton"
				@click="emit('editDiary')">
				Edit diary
			</button>
			<button
				v-if="props.question"
				type="button"
				:class="$style.secondaryButton"
				@click="emit('editQuestion')">
				Edit question
			</button>
		</div>
	</header>
</template>

<style module>
.header {
	position: sticky;
	top: 0;
	z-index: 30;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 20px;
	padding: 18px 22px;
	background:
		linear-gradient(135deg, rgba(255, 248, 236, 0.96), rgba(243, 248, 255, 0.96));
	border-bottom: 1px solid rgba(24, 36, 56, 0.12);
	backdrop-filter: blur(8px);
}

.leading {
	display: flex;
	align-items: center;
	gap: 14px;
	min-width: 0;
}

.title {
	margin: 0;
	font-size: clamp(1.2rem, 2vw, 1.8rem);
	line-height: 1.1;
	word-break: break-word;
}

.actions {
	display: flex;
	flex-wrap: wrap;
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
	background: rgba(16, 37, 66, 0.1);
	color: #102542;
}
</style>
