<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiPlus } from '@mdi/js'
import { computed } from 'vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import '@/components/layout/workspaceHeader.css'

const store = useStructuredDiaryStore()
const diary = computed(() => store.selectedDiary)
const question = computed(() => store.selectedQuestion)

async function createQuestion(): Promise<void> {
	if (store.selectedDiaryId === null) {
		return
	}

	await store.startCreatingQuestion(null, store.selectedDiaryId)
}

async function editQuestion(): Promise<void> {
	if (store.selectedQuestionId === null || store.selectedDiaryId === null) {
		return
	}

	await store.startEditingQuestion(store.selectedQuestionId, store.selectedDiaryId)
}
</script>

<template>
	<header class="workspace-header">
		<div class="workspace-header-leading">
			<h1 class="workspace-header-title">
				{{ diary?.title ?? 'Structured Diary' }}
			</h1>
		</div>

		<div class="workspace-header-actions">
			<NcButton aria-label="Create new question" @click="createQuestion()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
			</NcButton>
			<NcButton
				v-if="question !== null"
				variant="secondary"
				@click="editQuestion()">
				Edit question
			</NcButton>
		</div>
	</header>
</template>
