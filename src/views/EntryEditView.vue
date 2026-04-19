<script setup lang="ts">
import type { Answer, Entry, Question } from '@/types/types'
import EntryEditorForm from '@/components/entries/EntryEditorForm.vue'

defineProps<{
	entry: Entry | null
	questions: Question[]
	answers: Answer[]
}>()

defineEmits<{
	(event: 'save', payload: { title: string | null, timestamp: number, answers: Answer[] }): void
	(event: 'cancel'): void
}>()
</script>

<template>
	<EntryEditorForm
		:entry="entry"
		:questions="questions"
		:answers="answers"
		@save="$emit('save', $event)"
		@cancel="$emit('cancel')" />
</template>
