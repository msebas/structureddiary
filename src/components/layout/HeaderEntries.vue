<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {mdiDeleteOutline, mdiPencil, mdiPlus} from '@mdi/js'
import {computed} from 'vue'
import {useStructuredDiaryStore} from '@/stores/structuredDiary'
import '@/components/layout/workspaceHeader.css'
import { n, t } from '@nextcloud/l10n'

const store = useStructuredDiaryStore()
const diary = computed(() => store.selectedDiary)
const entry = computed(() => store.selectedEntry)

async function createEntry(): Promise<void> {
	await store.startCreatingEntry(store.selectedDiaryId)
}

async function editEntry(): Promise<void> {
	if (store.selectedEntryId === null || store.selectedDiaryId === null) {
		return
	}

	await store.startEditingEntry(store.selectedEntryId, store.selectedDiaryId)
}

async function deleteEntry(): Promise<void> {
	if (store.selectedEntryId === null) {
		return
	}

	const answerCount = await store.countEntryAnswers(store.selectedEntryId)
	const answerLabel = n('structureddiary', '%n answer', '%n answers', answerCount)
	if (!window.confirm(t('structureddiary', 'Delete this entry? This will delete {answerLabel}, including all answer history.', {answerLabel}))) {
		return
	}

	await store.deleteEntry(store.selectedEntryId)
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
				:aria-label="t('structureddiary', 'Create new entry')"
				@click="createEntry()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Add entry') }}</span>
			</NcButton>
			<NcButton
				v-if="entry !== null"
				class="sd-mobile-icon-button"
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
	</header>
</template>
