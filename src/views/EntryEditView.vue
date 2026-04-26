<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import EntryEditorForm from '@/components/entries/EntryEditorForm.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { Answer } from '@/types/types'

const store = useStructuredDiaryStore()
const route = useRoute()
const router = useRouter()

const entry = computed(() => store.creatingEntry ? null : store.selectedEntry)
const questions = computed(() => store.creatingEntry ? store.currentDiaryQuestions : store.currentEntryQuestions)
const answers = computed<Answer[]>(() => store.creatingEntry ? [] : Object.values(store.currentAnswers))

async function saveEntry(payload: { title: string | null, timestamp: number, answers: Answer[] }): Promise<void> {
	if (store.selectedDiaryId === null) {
		return
	}

	await store.saveEntry({
		entryId: store.creatingEntry ? null : store.selectedEntryId,
		diaryId: store.selectedDiaryId,
		title: payload.title,
		timestamp: payload.timestamp,
		answers: payload.answers,
	})
}

async function cancelEntryEdit(): Promise<void> {
	if (store.selectedDiaryId === null) {
		return
	}

	if (route.name === 'entryEdit' && store.selectedEntryId !== null) {
		await router.push({
			name: 'entry',
			params: { diaryId: store.selectedDiaryId, entryId: store.selectedEntryId },
			query: store.routeQueryFor('entry'),
		})
		return
	}

	await router.push({
		name: 'entries',
		params: { diaryId: store.selectedDiaryId },
		query: store.routeQueryFor('entries'),
	})
}
</script>

<template>
	<EntryEditorForm
		:entry="entry"
		:questions="questions"
		:answers="answers"
		@save="saveEntry"
		@cancel="cancelEntryEdit" />
</template>
