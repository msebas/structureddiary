<script setup lang="ts">
import {computed, ref} from 'vue'
import EntryDisplayCard from '@/components/entries/EntryDisplayCard.vue'
import {useStructuredDiaryStore} from '@/stores/structuredDiary'
import type {Answer} from '@/types/types'
import OverlayPanel from "@/components/common/OverlayPanel.vue";
import AnswerHistoryList from "@/components/answers/AnswerHistoryList.vue";

const store = useStructuredDiaryStore()

const answers = computed(() => Object.values(store.currentAnswers))
const answerHistories = computed<Record<string, Answer[]>>(() => {
  const entryId = store.selectedEntryId
  if (entryId === null) {
    return {}
  }

  return Object.fromEntries(
      Object.entries(store.answerHistoryByEntryQuestion[entryId] ?? {}).map(([questionId, history]) => [`${entryId}:${questionId}`, history]),
  )
})

const showAnswerHistory = ref<number | null>(null)

async function openAnswerHistory(questionId: number): Promise<void> {
  if (store.selectedEntryId === null) {
    return
  }
  showAnswerHistory.value = questionId
  await store.loadAnswerHistory(store.selectedEntryId, questionId)
}

async function deleteCurrentAnswer(answerId: number): Promise<void> {
  await store.deleteAnswer(answerId)
}

</script>

<template>
  <EntryDisplayCard
      :entry="store.selectedEntry"
      :questions="store.currentEntryQuestions"
      :answers="answers"
      :answer-histories="answerHistories"
      @load-history="openAnswerHistory"
      @delete-answer="deleteCurrentAnswer"/>

  <OverlayPanel
      :open="showAnswerHistory !=null"
      title="Answer versions"
      @close="showAnswerHistory = null">
    <AnswerHistoryList
        :question="store.currentEntryQuestions.find((question) => question?.id === showAnswerHistory) ?? null"
        :answers="store.answerHistoryByEntryQuestion[store.selectedEntryId ?? 0]?.[showAnswerHistory ?? 0] ?? []"
        @delete="deleteCurrentAnswer"/>
  </OverlayPanel>
</template>
