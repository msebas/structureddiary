<script setup lang="ts">
import type { Answer, Entry, Question } from '@/types/types'
import EntryDisplayCard from '@/components/entries/EntryDisplayCard.vue'

defineProps<{
	entry: Entry | null
	questions: Question[]
	answers: Answer[]
	answerHistories: Record<string, Answer[]>
}>()

defineEmits<{
	(event: 'loadHistory', questionId: number): void
	(event: 'deleteAnswer', answerId: number): void
}>()
</script>

<template>
	<EntryDisplayCard
		:entry="entry"
		:questions="questions"
		:answers="answers"
		:answer-histories="answerHistories"
		@load-history="$emit('loadHistory', $event)"
		@delete-answer="$emit('deleteAnswer', $event)" />
</template>
