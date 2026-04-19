<script setup lang="ts">
import type { Answer, Entry, Question } from '@/types/types'
import { entryQuestionProgress, formatDateTime } from '@/utils/format'
import AnswerDisplay from '@/components/answers/AnswerDisplay.vue'

const props = defineProps<{
	entry: Entry | null
	questions: Question[]
	answers: Answer[]
	answerHistories: Record<string, Answer[]>
}>()

const emit = defineEmits<{
	(event: 'loadHistory', questionId: number): void
	(event: 'deleteAnswer', answerId: number): void
}>()

function currentAnswer(questionId: number): Answer | undefined {
	return props.answers.find((answer) => answer.question_id === questionId)
}

function historyKey(questionId: number): string {
	return `${props.entry?.id ?? 0}:${questionId}`
}

function hasMultipleVersions(questionId: number): boolean {
	const answer = currentAnswer(questionId)
	if (!answer) {
		return false
	}

	const history = props.answerHistories[historyKey(questionId)] ?? []
	return history.length > 1 || answer.previous_version_id !== null || answer.next_version_id !== null
}
</script>

<template>
	<section :class="$style.card">
		<template v-if="props.entry">
			<header :class="$style.header">
				<div>
					<h2 :class="$style.title">
						{{ props.entry.title || 'Untitled entry' }}
					</h2>
					<div :class="$style.meta">
						{{ formatDateTime(props.entry.timestamp) }}
					</div>
				</div>
				<div :class="$style.progress">
					{{ entryQuestionProgress(props.entry, props.questions, props.answers) }}
				</div>
			</header>

			<div :class="$style.questionList">
				<article
					v-for="question in props.questions.filter((item) => currentAnswer(item.id))"
					:key="question.id"
					:class="$style.questionCard">
					<div :class="$style.questionHeader">
						<div>
							<h3 :class="$style.questionTitle">{{ question.display_text }}</h3>
							<div :class="$style.questionMeta">
								{{ formatDateTime(question.created_at) }}
							</div>
						</div>
						<div :class="$style.answerActions">
							<button
								v-if="!hasMultipleVersions(question.id)"
								type="button"
								:class="$style.miniButton"
								@click="currentAnswer(question.id) && emit('deleteAnswer', currentAnswer(question.id)!.id)">
								Delete
							</button>
							<button
								v-else
								type="button"
								:class="$style.miniButton"
								@click="emit('loadHistory', question.id)">
								Versions
							</button>
						</div>
					</div>
					<AnswerDisplay :question="question" :answer="currentAnswer(question.id)" />
				</article>
			</div>
		</template>

		<template v-else>
			<div :class="$style.empty">
				Select an entry to inspect it here.
			</div>
		</template>
	</section>
</template>

<style module>
.card {
	display: grid;
	gap: 18px;
	padding: 22px;
	min-height: 100%;
	border-radius: 26px;
	background:
		radial-gradient(circle at top right, rgba(245, 193, 155, 0.22), transparent 38%),
		linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(247, 249, 252, 0.98));
	box-shadow: 0 20px 48px rgba(12, 25, 46, 0.09);
}

.header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 14px;
	padding-bottom: 16px;
	border-bottom: 1px solid rgba(16, 37, 66, 0.12);
}

.title {
	margin: 0;
	font-size: clamp(1.3rem, 1.6vw, 2rem);
}

.meta {
	margin-top: 8px;
	color: #657587;
}

.progress {
	border-radius: 999px;
	padding: 8px 12px;
	background: rgba(16, 37, 66, 0.08);
	font-weight: 700;
}

.questionList {
	display: grid;
	gap: 16px;
}

.questionCard {
	display: grid;
	gap: 10px;
	padding: 16px;
	border-radius: 18px;
	background: rgba(246, 248, 252, 0.92);
}

.questionHeader {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 14px;
}

.questionTitle {
	margin: 0;
	font-size: 1rem;
}

.questionMeta {
	margin-top: 4px;
	font-size: 0.82rem;
	color: #6a798c;
}

.answerActions {
	display: flex;
	gap: 8px;
}

.miniButton {
	border: 0;
	border-radius: 999px;
	padding: 8px 10px;
	background: rgba(16, 37, 66, 0.1);
	cursor: pointer;
}

.empty {
	display: grid;
	place-items: center;
	min-height: 300px;
	color: #718194;
}
</style>
