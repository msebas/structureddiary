<script setup lang="ts">
import type { Answer, Entry, Question } from '@/types/types'
import { entryQuestionProgress, formatDateTime, formatEntryTitle, hasExplicitEntryTitle } from '@/utils/format'
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
	<section class="workspace-card workspace-card--full-height">
		<template v-if="props.entry">
			<header :class="['workspace-card-header', $style.header]">
				<div>
					<h2 :class="$style.title">
						{{ formatEntryTitle(props.entry) }}
					</h2>
					<div v-if="hasExplicitEntryTitle(props.entry)" :class="['workspace-card-muted', $style.meta]">
						{{ formatDateTime(props.entry.timestamp) }}
					</div>
				</div>
				<div :class="['workspace-card-pill', $style.progress]">
					{{ entryQuestionProgress(props.entry, props.questions, props.answers) }}
				</div>
			</header>

			<div :class="$style.questionList">
				<article
					v-for="question in props.questions.filter((item) => currentAnswer(item.id))"
					:key="question.id"
					:class="['workspace-card-subcard', $style.questionCard]">
					<div :class="$style.questionHeader">
						<div>
							<h3 :class="$style.questionTitle">{{ question.display_text }}</h3>
							<div :class="['workspace-card-muted', $style.questionMeta]">
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
				<div aria-hidden="true" :class="$style.answerSpacer" />
			</div>
      <div class="workspace-end-space"></div>
		</template>

		<template v-else>
			<div :class="['workspace-card-empty', $style.empty]">
				Select an entry to inspect it here.
			</div>
      <div class="workspace-end-space"></div>
		</template>

	</section>
</template>

<style module>

.header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 14px;
}

.title {
	margin: 0;
	font-size: clamp(1.3rem, 1.6vw, 2rem);
}

.meta {
	margin-top: 8px;
}

.progress {
	padding: 8px 12px;
}

.questionList {
	display: grid;
	gap: 16px;
}

.questionCard {
	display: grid;
	gap: 10px;
	padding: 16px;
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
}

.answerActions {
	display: flex;
	gap: 8px;
}

.miniButton {
	border: 0;
	border-radius: var(--border-radius-pill);
	padding: 8px 10px;
	background: var(--color-background-dark);
	color: var(--color-main-text);
	cursor: pointer;
}

.empty {
	min-height: 300px;
}

.answerSpacer {
	min-height: 20%;
	pointer-events: none;
}
</style>
