<script setup lang="ts">
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationSearch from '@nextcloud/vue/components/NcAppNavigationSearch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {mdiCogOutline, mdiShareVariantOutline} from '@mdi/js'
import {computed} from 'vue'
import {useRoute} from 'vue-router'
import {useStructuredDiaryStore} from '@/stores/structuredDiary'
import type {Diary, DiaryGroupSet} from '@/types/types'
import { t } from '@nextcloud/l10n'

const labels: Record<keyof DiaryGroupSet, string> = {
  owned: t('structureddiary', 'Owned diaries'),
  managed: t('structureddiary', 'Shared with full access'),
  writable: t('structureddiary', 'Shared with write access'),
  readable: t('structureddiary', 'Shared with read access'),
}

const store = useStructuredDiaryStore()
const route = useRoute()
const emit = defineEmits<{
  (event: 'diary-selected'): void
}>()
const inManagement = computed(() => route.name?.toString()?.startsWith("diar") || route.name?.toString()?.startsWith("question"))
const selectedQuestionRoute = computed(() => route.name === 'question' || route.name === 'questionEdit')

const visibleGroupEntries = computed(() =>
    Object.entries(store.diaryGroups).filter(([, items]) => items.length > 0) as Array<[keyof DiaryGroupSet, Diary[]]>)

function diaryLabel(diary: Diary): string {
  return diary.is_owner ? diary.title : `${diary.title} (${diary.user_id})`
}

function diaryIcon(): string {
  return mdiShareVariantOutline
}

function selectDiary(diary: Diary): void {
  if (selectedQuestionRoute.value) {
    store.pushWorkspaceRoute({name: 'diary', params: {diaryId: diary.id}})
    emit('diary-selected')
    return
  }
  store.selectedDiaryId = diary.id
  emit('diary-selected')
}

function openManagement(): void {
  const diaryId = store.selectedDiaryId
  if (inManagement.value) {
    if (diaryId !== null) {
      store.pushWorkspaceRoute({name: 'entries', params: {diaryId}})
      return
    }
    store.pushWorkspaceRoute({name: 'entriesAllDiaries'})
    return
  }

  if (diaryId !== null) {
    store.pushWorkspaceRoute({name: 'diary', params: {diaryId}})
    return
  }
  store.pushWorkspaceRoute({name: 'diaries'})
}
</script>

<template>
  <NcAppNavigation :aria-label="t('structureddiary', 'Structured Diary navigation')">
    <template #search>
      <div :class="$style.search">
        <NcAppNavigationSearch v-model="store.diarySearch" :label="t('structureddiary', 'Search diaries')"/>
      </div>
    </template>

    <template #list>
      <div :class="$style.sections">
        <section
            v-for="[key, items] in visibleGroupEntries"
            :key="key"
            :class="$style.section">
          <h3 :class="$style.sectionTitle">
            {{ labels[key] }}
          </h3>
          <NcAppNavigationItem
              v-for="diary in items"
              :key="diary.id"
              :name="diaryLabel(diary)"
              :active="diary.id === store.selectedDiaryId"
              @click="selectDiary(diary)">
            <template v-if="!diary.is_owner" #icon>
              <NcIconSvgWrapper :path="diaryIcon()"/>
            </template>
          </NcAppNavigationItem>
        </section>
      </div>
    </template>

    <template #footer>
      <div :class="$style.footer">
        <NcAppNavigationItem
            :name="inManagement ? t('structureddiary', 'Entries') : t('structureddiary', 'Management')"
            @click="openManagement()">
          <template #icon>
            <NcIconSvgWrapper :path="mdiCogOutline"/>
          </template>
        </NcAppNavigationItem>
      </div>
    </template>
  </NcAppNavigation>
</template>

<style module>
.search {
  padding: 4px;
}

.sections {
  padding: 4px 0;
}

.section {
  margin-bottom: 12px;
}

.sectionTitle {
  margin: 0;
  padding: 8px 14px 6px;
  color: var(--color-text-maxcontrast);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.footer {
  display: grid;
  gap: 4px;
  padding: 4px 0;
}
</style>
