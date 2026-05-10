<script setup lang="ts">
import type { Answer, Question } from '@/types/types'
import { formatDateTime } from '@/utils/format'
import AnswerDisplay from '@/components/answers/AnswerDisplay.vue'
import { n, t } from '@nextcloud/l10n'

const props = defineProps<{
	question: Question | null
	answers: Answer[]
}>()

const emit = defineEmits<{
	(event: 'delete', answerId: number): void
}>()
</script>

<template>
	<section :class="$style.list">
		<header :class="$style.header">
			<h3 :class="$style.title">
				{{ props.question?.label ?? t('structureddiary', 'Answer versions') }}
			</h3>
			<div :class="$style.meta">
				{{ n('structureddiary', '%n version', '%n versions', props.answers.length) }}
			</div>
		</header>

		<div v-if="props.question && props.answers.length > 0" :class="$style.items">
			<article v-for="answer in props.answers" :key="answer.id" :class="$style.item">
				<div :class="$style.itemHeader">
					<div>{{ formatDateTime(answer.created_at) }}</div>
					<button type="button" :class="$style.deleteButton" @click="emit('delete', answer.id)">
						{{ t('structureddiary', 'Delete') }}
					</button>
				</div>
				<AnswerDisplay :question="props.question" :answer="answer" />
			</article>
		</div>

		<div v-else :class="$style.empty">
			{{ t('structureddiary', 'No answer versions loaded.') }}
		</div>
	</section>
</template>

<style module>
.list {
	display: grid;
	gap: 14px;
}

.header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.title {
	margin: 0;
}

.meta {
	color: #6b7a8c;
}

.items {
	display: grid;
	gap: 12px;
}

.item {
	display: grid;
	gap: 10px;
	padding: 14px;
	border-radius: 16px;
	background: rgba(246, 248, 252, 0.92);
}

.itemHeader {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	font-size: 0.9rem;
	color: #516175;
}

.deleteButton {
	border: 0;
	border-radius: 999px;
	padding: 7px 10px;
	background: rgba(217, 105, 65, 0.12);
	color: #973f22;
	font-weight: 700;
	cursor: pointer;
}

.empty {
	color: #718194;
}
</style>
