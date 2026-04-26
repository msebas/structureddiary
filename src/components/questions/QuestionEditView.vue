<script setup lang="ts">
import type { Question, QuestionTypeDefinition, QuestionUpdatePayload } from '@/types/types'
import QuestionEditorForm from '@/components/questions/QuestionEditorForm.vue'

defineProps<{
	question: Question | null
	types: QuestionTypeDefinition[]
}>()

defineEmits<{
	(event: 'save', payload: QuestionUpdatePayload): void
	(event: 'cancel'): void
}>()
</script>

<template>
	<QuestionEditorForm
		:question="question"
		:types="types"
		@save="$emit('save', $event)"
		@cancel="$emit('cancel')" />
</template>
