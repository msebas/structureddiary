<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import DiaryEditorForm from '@/components/diaries/DiaryEditorForm.vue'
import DiaryShareEditor from '@/components/diaries/DiaryShareEditor.vue'
import { type DiaryEditSubmitPayload, type DiaryShareInput, useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { DiaryUpdatePayload } from '@/types/types'

const store = useStructuredDiaryStore()
const route = useRoute()
const copiedDiaryPayload = ref<DiaryEditSubmitPayload | null>(null)
const sharePayload = ref<DiaryShareInput[]>([])

const diary = computed(() => store.selectedDiary)
const shares = computed(() => Object.values(store.selectedDiaryShares))
const isCreating = computed(() => store.creatingDiary)
const initialDraft = computed(() => copiedDiaryPayload.value?.diary ?? null)
const entryCount = computed(() => store.selectedDiaryStats?.entry_count ?? null)
const canChangeOwner = computed(() => isCreating.value || entryCount.value === 0)

async function submit(diaryPayload: DiaryUpdatePayload): Promise<void> {
	const currentDiary = diary.value
	const savePayload: DiaryEditSubmitPayload = {
		diaryId: isCreating.value ? null : currentDiary?.id ?? null,
		diary: diaryPayload,
		shares: sharePayload.value,
		questions: copiedDiaryPayload.value?.questions ?? null,
	}

	await store.saveDiary(savePayload)
}

async function duplicate(): Promise<void> {
	if (diary.value === null) {
		return
	}

	copiedDiaryPayload.value = await store.copyDiary(diary.value.id)
}

async function cancelEdit(): Promise<void> {
	if (isCreating.value) {
		await store.cancelCreateDiary()
		return
	}
	if (diary.value !== null) {
		await store.pushWorkspaceRoute({
			name: 'diary',
			params: { diaryId: diary.value.id },
		})
	}
}

async function deleteDiary(): Promise<void> {
	await store.deleteDiary(diary.value?.id ?? null)
}

watch(() => route.name, (routeName) => {
	if (routeName !== 'diaryCreate') {
		copiedDiaryPayload.value = null
	}
})
</script>

<template>
	<section :class="$style.view">
		<DiaryShareEditor
			:shares="shares"
			@update:shares="sharePayload = $event" />

		<DiaryEditorForm
			:diary="diary"
			:initial-draft="initialDraft"
			:is-creating="isCreating"
			:can-change-owner="canChangeOwner"
			:entry-count="entryCount"
			@save="submit"
			@duplicate="duplicate"
			@cancel="cancelEdit"
			@delete="deleteDiary" />
	</section>
</template>

<style module>
.view {
	display: grid;
	gap: 16px;
}
</style>
