<script setup lang="ts">
import type { Diary, DiaryUpdatePayload, DiaryShare } from '@/types/types'
import DiaryEditorForm from '@/components/diaries/DiaryEditorForm.vue'
import DiaryShareEditor from '@/components/diaries/DiaryShareEditor.vue'

defineProps<{
	diary: Diary | null
	canChangeOwner: boolean
	shares: DiaryShare[]
}>()

defineEmits<{
	(event: 'saveDiary', payload: DiaryUpdatePayload): void
	(event: 'saveShares', payload: Array<{ sharedWith: string, permission: number }>): void
	(event: 'cancel'): void
}>()
</script>

<template>
	<div :style="{ display: 'grid', gap: '16px' }">
		<DiaryEditorForm
			:diary="diary"
			:can-change-owner="canChangeOwner"
			@save="$emit('saveDiary', $event)"
			@cancel="$emit('cancel')" />
		<DiaryShareEditor
			:shares="shares"
			@save="$emit('saveShares', $event)"
			@cancel="$emit('cancel')" />
	</div>
</template>
