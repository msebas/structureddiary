<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiPencil, mdiPlus } from '@mdi/js'
import { computed } from 'vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import '@/components/layout/workspaceHeader.css'
import { t } from '@nextcloud/l10n'

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
				{{ diary?.title ?? t('structureddiary', 'Structured Diary') }}
			</h1>
		</div>

		<div class="workspace-header-actions">
			<NcButton
				class="sd-mobile-icon-button"
				:aria-label="t('structureddiary', 'Create new question')"
				@click="createQuestion()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'New question') }}</span>
			</NcButton>
			<NcButton
				v-if="question !== null"
				class="sd-mobile-icon-button"
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
