<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import type { Answer, Entry, Question } from '@/types/types'
import { formatDateTime, isAnswerEmptyForQuestion } from '@/utils/format'
import AnswerEditorField from '@/components/answers/AnswerEditorField.vue'
import { t } from '@nextcloud/l10n'

const props = defineProps<{
	entry: Entry | null
	questions: Question[]
	answers: Answer[]
}>()

const emit = defineEmits<{
	(event: 'save', payload: { title: string | null, timestamp: number, answers: Answer[] }): void
	(event: 'cancel'): void
}>()

const form = reactive({
	title: '' as string,
	timestamp: 0,
	answers: {} as Record<number, Answer>,
})

watch(() => props.entry, (entry) => {
	form.title = entry?.title ?? ''
	form.timestamp = entry?.timestamp ?? Math.floor(Date.now() / 1000)
	form.answers = Object.fromEntries((props.answers ?? []).map((answer) => [answer.question_id, { ...answer }]))
}, { immediate: true })

watch(() => props.answers, (answers) => {
	form.answers = Object.fromEntries(answers.map((answer) => [answer.question_id, { ...answer }]))
}, { immediate: true })

const activeQuestions = computed(() =>
	props.questions.filter((question) => question.created_at <= form.timestamp && question.active))

function submit(): void {
	emit('save', {
		title: form.title.trim() === '' ? null : form.title.trim(),
		timestamp: form.timestamp,
		answers: Object.values(form.answers),
	})
}
</script>

<template>
	<section :class="$style.editor">
		<header :class="$style.header">
			<div>
				<div :class="$style.date">{{ formatDateTime(form.timestamp) }}</div>
				<input
					v-model="form.title"
					type="text"
					:placeholder="t('structureddiary', 'Entry title')"
					:class="$style.titleInput">
			</div>
			<div :class="$style.actions">
				<button type="button" :class="$style.secondaryButton" @click="emit('cancel')">
					{{ t('structureddiary', 'Cancel') }}
				</button>
				<button type="button" :class="$style.primaryButton" @click="submit()">
					{{ t('structureddiary', 'Save') }}
				</button>
			</div>
		</header>

		<div :class="$style.questionList">
			<AnswerEditorField
				v-for="question in activeQuestions"
				:key="question.id"
				:question="question"
				:model-value="form.answers[question.id]"
				:highlight-empty="isAnswerEmptyForQuestion(question, form.answers[question.id])"
				@update:model-value="form.answers[question.id] = $event" />
		</div>

		<footer :class="$style.footer">
			<button type="button" :class="$style.secondaryButton" @click="emit('cancel')">
				{{ t('structureddiary', 'Cancel') }}
			</button>
			<button type="button" :class="$style.primaryButton" @click="submit()">
				{{ t('structureddiary', 'Save') }}
			</button>
		</footer>
	</section>
</template>

<style module>
.editor {
	display: grid;
	gap: 18px;
	padding: 22px;
	border-radius: 26px;
	background: rgba(255, 255, 255, 0.98);
	box-shadow: 0 20px 48px rgba(12, 25, 46, 0.09);
}

.header,
.footer {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 14px;
}

.date {
	font-size: 0.85rem;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	color: #6e7d90;
}

.titleInput {
	margin-top: 8px;
	width: min(460px, 100%);
	border: 1px solid rgba(16, 37, 66, 0.15);
	border-radius: 14px;
	padding: 12px 14px;
	font-size: 1rem;
}

.questionList {
	display: grid;
	gap: 14px;
}

.actions {
	display: flex;
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
	background: rgba(16, 37, 66, 0.08);
	color: #102542;
}

@media (max-width: 720px) {
	.header,
	.footer {
		flex-direction: column;
		align-items: stretch;
	}
}
</style>
