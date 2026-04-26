<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiPlus } from '@mdi/js'
import { computed } from 'vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import '@/components/layout/workspaceHeader.css'

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
</script>

<template>
	<header class="workspace-header">
		<div class="workspace-header-leading">
			<h1 class="workspace-header-title">
				{{ diary?.title ?? 'Structured Diary' }}
			</h1>
		</div>

		<div class="workspace-header-actions">
			<NcButton aria-label="Create new entry" @click="createEntry()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
			</NcButton>
			<NcButton
				v-if="entry !== null"
				variant="secondary"
				@click="editEntry()">
				Edit entry
			</NcButton>
		</div>
	</header>
</template>
