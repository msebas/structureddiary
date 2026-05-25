<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {mdiPlus, mdiPencil, mdiShareVariant} from '@mdi/js'

import {computed} from 'vue'
import {useStructuredDiaryStore} from '@/stores/structuredDiary'
import {Permissions} from '@/types/types'
import '@/components/layout/workspaceHeader.css'
import {t} from '@nextcloud/l10n'
import {useRoute} from "vue-router";


const store = useStructuredDiaryStore()
const diary = computed(() => store.selectedDiary)
const route = useRoute()
const isDiaryEditFormRoute = computed(() => route.name === 'diaryCreate' || route.name === 'diaryEdit')

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
      <NcButton
          v-if="!isDiaryEditFormRoute"
          class="sd-mobile-icon-button sd-header-primary-action"
          :aria-label="t('structureddiary', 'Create new diary')"
          @click="store.startCreatingDiary()">
        <template #icon>
          <NcIconSvgWrapper :path="mdiPlus"/>
        </template>
        <span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Add diary') }}</span>
      </NcButton>
      <NcButton
          v-if="diary !== null && managePermissionsOnDiary && !isDiaryEditFormRoute"
          class="sd-mobile-icon-button sd-header-edit-action"
          variant="secondary"
          :aria-label="t('structureddiary', 'Edit diary')"
          @click="store.editDiary(diary.id)">
        <template #icon>
          <NcIconSvgWrapper :path="mdiPencil"/>
        </template>
        <span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Edit diary') }}</span>
      </NcButton>
    </div>
  </header>
</template>
