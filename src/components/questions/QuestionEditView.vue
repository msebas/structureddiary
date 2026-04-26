<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'
import QuestionEditorForm from '@/components/questions/QuestionEditorForm.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { QuestionCreatePayload, QuestionUpdatePayload } from '@/types/types'

const store = useStructuredDiaryStore()
const route = useRoute()
const router = useRouter()

async function saveQuestion(payload: QuestionUpdatePayload): Promise<void> {
	if (store.creatingQuestion) {
		if (store.selectedDiaryId === null) {
			return
		}
		await store.saveQuestionAndReloadVersions({
			...payload,
			diaryId: store.selectedDiaryId,
			label: payload.label ?? null,
			type: payload.type ?? 'text',
			active: payload.active ?? true,
		} as QuestionCreatePayload)
		return
	}

	if (store.selectedQuestion === null) {
		return
	}

	await store.saveQuestionAndReloadVersions({
		...payload,
		questionId: store.selectedQuestion.id,
		chainId: store.selectedQuestion.chain_id,
	} as QuestionUpdatePayload)
}

async function cancelQuestionEdit(): Promise<void> {
	if (store.selectedDiaryId === null) {
		return
	}

	if (route.name === 'questionEdit' && store.selectedQuestion !== null) {
		await router.push({
			name: 'question',
			params: { diaryId: store.selectedDiaryId, questionId: store.selectedQuestion.id },
			query: store.routeQueryFor('question'),
		})
		return
	}

	await router.push({
		name: 'questions',
		params: { diaryId: store.selectedDiaryId },
		query: store.routeQueryFor('questions'),
	})
}
</script>

<template>
	<QuestionEditorForm
		:question="store.creatingQuestion ? null : store.selectedQuestion"
		:types="store.questionTypes"
		@save="saveQuestion"
		@cancel="cancelQuestionEdit" />
</template>
