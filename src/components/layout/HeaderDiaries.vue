<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiPlus, mdiPencil, mdiShareVariant } from '@mdi/js'

import { computed } from 'vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import { Permissions } from '@/types/types'
import '@/components/layout/workspaceHeader.css'
import { t } from '@nextcloud/l10n'


const store = useStructuredDiaryStore()
const diary = computed(() => store.selectedDiary)

const managePermissionsOnDiary = computed(() => ((store.user_permissions & Permissions.MANAGE) !== 0))

</script>

<template>
	<header class="workspace-header">
		<div class="workspace-header-leading">
			<h1 class="workspace-header-title">
				{{ diary?.title ?? t('structureddiary', 'Structured Diary') }}
			</h1>
		</div>

		<div class="workspace-header-actions">
			<NcButton :aria-label="t('structureddiary', 'Create new diary')" @click="store.startCreatingDiary()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
				{{ t('structureddiary', 'Add diary') }}
			</NcButton>
			<NcButton
				v-if="diary !== null && managePermissionsOnDiary"
				variant="secondary"
				@click="store.editDiary(diary.id)">
        <template #icon>
          <NcIconSvgWrapper :path="mdiPencil" />
        </template>
				{{ t('structureddiary', 'Edit diary') }}
			</NcButton>
			<NcButton
				v-if="diary !== null && managePermissionsOnDiary"
				variant="secondary"
				@click="store.editDiaryShares(diary.id)">
        <template #icon>
          <NcIconSvgWrapper :path="mdiShareVariant" />
        </template>
				{{ t('structureddiary', 'Edit diary share') }}
			</NcButton>
		</div>
	</header>
</template>
