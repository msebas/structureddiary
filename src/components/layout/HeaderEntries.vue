<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {mdiContentSave, mdiDeleteOutline, mdiPencil, mdiPlus} from '@mdi/js'
import {computed, ref} from 'vue'
import {useRoute} from 'vue-router'
import {useStructuredDiaryStore} from '@/stores/structuredDiary'
import '@/components/layout/workspaceHeader.css'
import { n, t } from '@nextcloud/l10n'

const store = useStructuredDiaryStore()
const route = useRoute()
const diary = computed(() => store.selectedDiary)
const entry = computed(() => store.selectedEntry)
const deleteDialogOpen = ref(false)
const deleteAnswerLabel = ref('')
const isEntryEditFormRoute = computed(() => route.name === 'entryCreate' || route.name === 'entryEdit')

async function createEntry(): Promise<void> {
	await store.startCreatingEntry(store.selectedDiaryId)
}

async function editEntry(): Promise<void> {
	if (store.selectedEntryId === null || store.selectedDiaryId === null) {
		return
	}

	await store.startEditingEntry(store.selectedEntryId, store.selectedDiaryId)
}

function saveEntryForm(): void {
	document.getElementById('structured-diary-entry-edit-form')?.requestSubmit()
}

async function deleteEntry(): Promise<void> {
	if (store.selectedEntryId === null) {
		return
	}

	const answerCount = await store.countEntryAnswers(store.selectedEntryId)
	deleteAnswerLabel.value = n('structureddiary', '%n answer', '%n answers', answerCount)
	deleteDialogOpen.value = true
}

async function confirmDeleteEntry(): Promise<void> {
	if (store.selectedEntryId === null) {
		return
	}

	await store.deleteEntry(store.selectedEntryId)
}

const deleteEntryDialogButtons = computed(() => [
	{
		label: t('structureddiary', 'Cancel'),
		callback: () => undefined,
	},
	{
		label: t('structureddiary', 'Delete entry'),
		variant: 'error' as const,
		callback: () => {
			void confirmDeleteEntry()
		},
	},
])
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
				v-if="isEntryEditFormRoute"
				class="sd-mobile-icon-button sd-header-primary-action"
				variant="primary"
				:aria-label="t('structureddiary', 'Save entry')"
				@click="saveEntryForm()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiContentSave" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Save entry') }}</span>
			</NcButton>
			<NcButton
				v-else
				class="sd-mobile-icon-button sd-header-primary-action"
				:aria-label="t('structureddiary', 'Create new entry')"
				@click="createEntry()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Add entry') }}</span>
			</NcButton>
			<NcButton
				v-if="entry !== null && !isEntryEditFormRoute"
				class="sd-mobile-icon-button sd-header-edit-action"
				variant="secondary"
				:aria-label="t('structureddiary', 'Edit entry')"
				@click="editEntry()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPencil" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Edit entry') }}</span>
			</NcButton>
			<NcButton
				v-if="entry !== null"
				class="sd-mobile-icon-button"
				variant="error"
				:aria-label="t('structureddiary', 'Delete entry')"
				@click="deleteEntry()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiDeleteOutline" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Delete entry') }}</span>
			</NcButton>
		</div>

		<NcDialog
			v-model:open="deleteDialogOpen"
			:name="t('structureddiary', 'Delete entry')"
			:message="t('structureddiary', 'Delete this entry? This will delete {answerLabel}, including all answer history.', {answerLabel: deleteAnswerLabel})"
			:buttons="deleteEntryDialogButtons"
			size="small" />
	</header>
</template>
