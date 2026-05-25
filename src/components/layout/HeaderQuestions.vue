<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiContentSave, mdiDeleteOutline, mdiPencil, mdiPlus } from '@mdi/js'
import { computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import '@/components/layout/workspaceHeader.css'
import { t } from '@nextcloud/l10n'

const store = useStructuredDiaryStore()
const route = useRoute()
const diary = computed(() => store.selectedDiary)
const question = computed(() => store.selectedQuestion)
const isQuestionEditFormRoute = computed(() => route.name === 'questionCreate' || route.name === 'questionEdit')
const canDeleteQuestion = computed(() => {
	if (question.value === null || store.selectedQuestionAnswerCount === null) {
		return false
	}

	return store.selectedQuestionAnswerCount === 0 || store.selectedQuestionVersionChain.length > 1
})

watch(() => store.selectedQuestionId, async (questionId) => {
	if (questionId === null) {
		return
	}

	await Promise.all([
		store.loadQuestionAnswerCount(questionId),
		store.loadQuestionVersions(questionId),
	]).catch(() => undefined)
}, { immediate: true })

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

function saveQuestionForm(): void {
	document.getElementById('structured-diary-question-edit-form')?.requestSubmit()
}

async function deleteQuestion(): Promise<void> {
	if (question.value === null) {
		return
	}

	await store.deleteQuestion(question.value.id).catch(() => undefined)
}
</script>

<template>
	<header class="workspace-header">
		<div class="workspace-header-leading">
			<h1 class="workspace-header-title">
				{{ diary?.title ?? t('structureddiary', 'Structured Diary') }}
			</h1>
		</div>

		<div class="workspace-header-actions">
			<NcButton
				v-if="isQuestionEditFormRoute"
				class="sd-mobile-icon-button sd-header-primary-action"
				variant="primary"
				:aria-label="t('structureddiary', 'Save question')"
				@click="saveQuestionForm()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiContentSave" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Save question') }}</span>
			</NcButton>
			<NcButton
				v-else-if="canDeleteQuestion"
				class="sd-mobile-icon-button"
				variant="error"
				:aria-label="t('structureddiary', 'Delete question')"
				@click="deleteQuestion()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiDeleteOutline" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Delete question') }}</span>
			</NcButton>
			<NcButton
				v-else
				class="sd-mobile-icon-button sd-header-primary-action"
				:aria-label="t('structureddiary', 'Create new question')"
				@click="createQuestion()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'New question') }}</span>
			</NcButton>
			<NcButton
				v-if="question !== null && !isQuestionEditFormRoute"
				class="sd-mobile-icon-button sd-header-edit-action"
				variant="secondary"
				:aria-label="t('structureddiary', 'Edit question')"
				@click="editQuestion()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPencil" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Edit question') }}</span>
			</NcButton>
		</div>
	</header>
</template>
