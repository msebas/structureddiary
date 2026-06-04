<script setup lang="ts">
import {computed} from 'vue'
import EntryDisplayCard from '@/components/entries/EntryDisplayCard.vue'
import {useStructuredDiaryStore} from '@/stores/structuredDiary'
import type {Answer, Question} from '@/types/types'
import OverlayPanel from "@/components/common/OverlayPanel.vue";
import AnswerHistoryList from "@/components/answers/AnswerHistoryList.vue";
import { t } from '@nextcloud/l10n'

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

const answerHistoryQuestion = computed<Question | null>(() =>
  store.currentEntryQuestions.find((question) => question?.id === store.answerHistoryQuestionId) ?? null)
const answerHistoryQuestions = computed<Question[]>(() => {
  const question = answerHistoryQuestion.value
  if (question === null) {
    return []
  }

  const versionQuestions = store.questionVersionMap[question.id] ?? []
  const loadedAnswerQuestions = store.answerHistoryByEntryQuestion[store.selectedEntryId ?? 0]?.[question.id]
      ?.map((answer) => store.questionById[answer.question_id])
      .filter((question): question is Question => question !== undefined) ?? []

  return [...new Map([question, ...versionQuestions, ...loadedAnswerQuestions].map((question) => [question.id, question])).values()]
})

async function openAnswerHistory(questionId: number): Promise<void> {
  if (store.selectedEntryId === null) {
    return
  }
  store.answerHistoryQuestionId = questionId
  await Promise.all([
    store.loadQuestionVersions(questionId),
    store.loadAnswerHistory(store.selectedEntryId, questionId),
  ])
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
      :open="store.answerHistoryQuestionId != null"
      :title="t('structureddiary', 'Answer versions')"
      :level="1"
      @close="store.answerHistoryQuestionId = null">
    <AnswerHistoryList
        :question="answerHistoryQuestion"
        :questions="answerHistoryQuestions"
        :answers="store.answerHistoryByEntryQuestion[store.selectedEntryId ?? 0]?.[store.answerHistoryQuestionId ?? 0] ?? []"
        @delete="deleteCurrentAnswer"/>
  </OverlayPanel>
</template>
