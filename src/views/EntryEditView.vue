<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import EntryEditorForm from '@/components/entries/EntryEditorForm.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { Answer } from '@/types/types'

const store = useStructuredDiaryStore()
const route = useRoute()

const entry = computed(() => store.creatingEntry ? null : store.selectedEntry)
const questions = computed(() => (store.creatingEntry ? store.currentDiaryQuestions : store.currentEntryQuestions).filter(q => q!=null))
const answers = computed<Answer[]>(() => store.creatingEntry ? [] : Object.values(store.currentAnswers))

async function saveEntry(payload: { title: string | null, timestamp: number, answers: Answer[] }): Promise<void> {
	if (store.selectedDiaryId === null) {
		return
	}

	const savedEntry = await store.saveEntry({
			entryId: store.creatingEntry ? null : store.selectedEntryId,
			diaryId: store.selectedDiaryId,
			title: payload.title,
			timestamp: payload.timestamp,
			answers: payload.answers,
		}, false)
		.catch(() => null)

	if (savedEntry === null) {
		return
	}

	await store.pushWorkspaceRoute({
		name: 'entry',
		params: {diaryId: savedEntry.diary_id, entryId: savedEntry.id},
	})
}

async function cancelEntryEdit(): Promise<void> {
	if (store.selectedDiaryId === null) {
		return
	}

	if (route.name === 'entryEdit' && store.selectedEntryId !== null) {
		await store.pushWorkspaceRoute({
			name: 'entry',
			params: { diaryId: store.selectedDiaryId, entryId: store.selectedEntryId },
		})
		return
	}

	await store.pushWorkspaceRoute({
		name: 'entries',
		params: { diaryId: store.selectedDiaryId },
	})
}
</script>

<template>
	<EntryEditorForm
		:entry="entry"
		:questions="questions"
		:answers="answers"
		:is-creating="store.creatingEntry"
		@save="saveEntry"
		@cancel="cancelEntryEdit" />
</template>
