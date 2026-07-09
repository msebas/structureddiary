<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiCancel, mdiContentSave, mdiDeleteOutline, mdiPencil, mdiPlay, mdiPlus } from '@mdi/js'
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { analysisService } from '@/services'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { AnalysisJob } from '@/types/types'
import { canCancelAnalysis, canDeleteAnalysis, canStartAnalysis } from '@/utils/analysis'
import '@/components/layout/workspaceHeader.css'
import { t } from '@nextcloud/l10n'

const store = useStructuredDiaryStore()
const route = useRoute()
const headerJob = ref<AnalysisJob | null>(null)
const isCreateRoute = computed(() => route.name === 'analysisCreate')
const selectedJobId = computed(() => {
	const value = route.params.jobId
	return typeof value === 'string' ? Number.parseInt(value, 10) : null
})

watch(selectedJobId, async (jobId) => {
	headerJob.value = null
	if (jobId === null) {
		return
	}
	const jobs = await analysisService.list().catch(() => [])
	headerJob.value = jobs.find((job) => job.id === jobId) ?? null
}, { immediate: true })

function emitAction(action: 'start' | 'cancel' | 'delete' | 'edit'): void {
	document.dispatchEvent(new CustomEvent('structured-diary-analysis-action', { detail: action }))
}

async function createAnalysis(): Promise<void> {
	if (store.selectedDiaryId === null) {
		return
	}
	await store.pushWorkspaceRoute({ name: 'analysisCreate', params: { diaryId: store.selectedDiaryId } })
}

function createDraft(): void {
	document.getElementById('structured-diary-analysis-create-form')?.dispatchEvent(new SubmitEvent('submit', {
		cancelable: true,
		bubbles: true,
		submitter: document.getElementById('structured-diary-analysis-create-draft') as HTMLElement | null,
	}))
}
</script>

<template>
	<header class="workspace-header">
		<div class="workspace-header-leading">
			<h1 class="workspace-header-title">
				{{ store.selectedDiary?.title ?? t('structureddiary', 'Analyses') }}
			</h1>
		</div>

		<div class="workspace-header-actions">
			<NcButton
				v-if="isCreateRoute"
				class="sd-mobile-icon-button sd-header-primary-action"
				variant="primary"
				:aria-label="t('structureddiary', 'Save draft')"
				@click="createDraft()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiContentSave" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Save draft') }}</span>
			</NcButton>
			<NcButton
				v-else-if="store.selectedDiaryCanAnalyze"
				class="sd-mobile-icon-button sd-header-primary-action"
				:aria-label="t('structureddiary', 'Create analysis')"
				@click="createAnalysis()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Create analysis') }}</span>
			</NcButton>
			<NcButton
				v-if="headerJob?.status === 'DRAFT'"
				class="sd-mobile-icon-button"
				variant="secondary"
				:aria-label="t('structureddiary', 'Edit analysis')"
				@click="emitAction('edit')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPencil" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Edit analysis') }}</span>
			</NcButton>
			<NcButton
				v-if="canStartAnalysis(headerJob)"
				class="sd-mobile-icon-button"
				variant="primary"
				:aria-label="t('structureddiary', 'Start')"
				@click="emitAction('start')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlay" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Start') }}</span>
			</NcButton>
			<NcButton
				v-if="canCancelAnalysis(headerJob)"
				class="sd-mobile-icon-button"
				variant="secondary"
				:aria-label="t('structureddiary', 'Cancel')"
				@click="emitAction('cancel')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiCancel" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Cancel') }}</span>
			</NcButton>
			<NcButton
				v-if="canDeleteAnalysis(headerJob)"
				class="sd-mobile-icon-button"
				variant="error"
				:aria-label="t('structureddiary', 'Delete')"
				@click="emitAction('delete')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiDeleteOutline" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Delete') }}</span>
			</NcButton>
		</div>
	</header>
</template>
