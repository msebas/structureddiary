<script setup lang="ts">
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {emit as emitNextcloudEvent} from '@nextcloud/event-bus'
import { mdiBookOpenPageVariant, mdiPlus } from '@mdi/js'
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue'
import {useRoute} from 'vue-router'
import StructuredDiaryNavigation from '@/components/layout/StructuredDiaryNavigation.vue'
import EntryListPanel from '@/components/layout/EntryListPanel.vue'
import QuestionListPanel from '@/components/layout/QuestionListPanel.vue'
import OverlayPanel from '@/components/common/OverlayPanel.vue'
import {useStructuredDiaryStore} from '@/stores/structuredDiary'
import type {WorkspaceRouteName} from '@/services/workspaceRoute'
import {mobileOverlayTitleForRoute} from '@/services/workspaceRoute'
import { t } from '@nextcloud/l10n'

const store = useStructuredDiaryStore()
const route = useRoute()
const appNavigationMobileQuery = '(max-width: 1024px)'
const isCompact = ref(false)
const isAppNavigationMobile = ref(false)
const mobileCenterOpen = ref(false)

const currentRouteName = computed<WorkspaceRouteName>(() => {
  const routeName = route.name
  return typeof routeName === 'string' ? routeName as WorkspaceRouteName : 'diaries'
})
const mobileOverlayTitle = computed(() => mobileOverlayTitleForRoute(currentRouteName.value))
const latestError = computed(() => store.errors.at(-1) ?? null)

function updateCompactState(): void {
  isCompact.value = window.matchMedia('(max-width: 1080px)').matches
  isAppNavigationMobile.value = window.matchMedia(appNavigationMobileQuery).matches
  if (!isCompact.value) {
    mobileCenterOpen.value = false
  }
}

function closeMobileCenter(): void {
  mobileCenterOpen.value = false
}

function openMobileCenter(): void {
  if (isCompact.value) {
    mobileCenterOpen.value = true
  }
}

async function openSelectedDiaryInMobileCenter(): Promise<void> {
  if (store.selectedDiaryId === null) {
    return
  }

  await store.pushWorkspaceRoute({
    name: 'diary',
    params: { diaryId: store.selectedDiaryId },
  })
  openMobileCenter()
}

async function createDiary(): Promise<void> {
  await store.startCreatingDiary()
}

function closeDiarySelectionAfterSelection(): void {
  if (isAppNavigationMobile.value) {
    emitNextcloudEvent('toggle-navigation', {open: false})
  }
}


onMounted(async () => {
  await store.initialize()
  updateCompactState()
  window.addEventListener('resize', updateCompactState)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateCompactState)
})

watch(() => route.fullPath, () => {
  if (isCompact.value) {
    mobileCenterOpen.value = currentRouteName.value !== 'entries' && currentRouteName.value !== 'questions' && currentRouteName.value !== 'diaries'
  }
}, {immediate: true})

watch(() => store.selectedDiaryId, async () => {
  await store.refreshSelectedDiaryWorkspace()
}, {immediate: true})

watch(() => store.selectedEntryId, async (entryId) => {
  if (entryId !== null) {
    await store.loadEntry(entryId)
  }
})
</script>

<template>
  <NcContent app-name="structureddiary">
    <StructuredDiaryNavigation @diary-selected="closeDiarySelectionAfterSelection()"/>
    <NcAppContent :class="$style.content">
      <div :class="$style.workspace">
        <div :class="$style.columns">
          <section v-if="!isCompact" :class="$style.centerColumn">
            <router-view name="nav"/>

            <main :class="[$style.center, $style.centerPadded]">
              <div v-if="store.errors.length > 0" :class="$style.error">
                <div v-for="error in store.errors" :key="error.id" :class="$style.errorItem">
                  <span>{{ error.message }}</span>
                  <NcButton
                      :aria-label="t('structureddiary', 'Dismiss error')"
                      :text="t('structureddiary', 'Dismiss')"
                      variant="tertiary"
                      @click="store.removeError(error.id)"/>
                </div>
              </div>
              <router-view/>
            </main>
          </section>

          <aside :class="$style.right">
            <div v-if="isCompact && !mobileCenterOpen" :class="$style.mobileSidebarHeader">
              <NcButton
                  :aria-label="t('structureddiary', 'Create new diary')"
                  variant="secondary"
                  @click="createDiary()">
                <template #icon>
                  <NcIconSvgWrapper :path="mdiPlus"/>
                </template>
                {{ t('structureddiary', 'New diary') }}
              </NcButton>
              <NcButton
                  :aria-label="t('structureddiary', 'Open diary')"
                  variant="secondary"
                  :disabled="store.selectedDiary === null"
                  @click="openSelectedDiaryInMobileCenter()">
                <template #icon>
                  <NcIconSvgWrapper :path="mdiBookOpenPageVariant"/>
                </template>
                {{ t('structureddiary', 'Diary') }}
              </NcButton>
            </div>
            <router-view name="sidebar" v-slot="{ Component }">
              <component
                  :is="Component"
                  @open-center="openMobileCenter()"/>
            </router-view>
          </aside>
        </div>

        <OverlayPanel
            :open="isCompact && mobileCenterOpen"
            :title="String(mobileOverlayTitle)"
            @close="closeMobileCenter()">
          <div :class="$style.mobileCenter">
            <router-view name="nav"/>

            <main :class="$style.center">
              <div v-if="store.errors.length > 0" :class="$style.error">
                <div v-if="latestError !== null" :class="$style.errorItem">
                  <span>{{ latestError.message }}</span>
                  <NcButton
                      :aria-label="t('structureddiary', 'Dismiss error')"
                      :text="t('structureddiary', 'Dismiss')"
                      variant="tertiary"
                      @click="store.removeError(latestError.id)"/>
                </div>
              </div>
              <router-view/>
            </main>
          </div>
        </OverlayPanel>

      </div>
    </NcAppContent>
  </NcContent>
</template>

<style module>
.content {
  min-height: 100%;
}

.workspace {
  display: grid;
  min-height: 100%;
}

.columns {
  display: grid;
  grid-template-columns: minmax(420px, 1fr) minmax(300px, 390px);
  min-height: 100%;
}

.centerColumn {
  min-width: 0;
  display: grid;
  grid-template-rows: auto 1fr;
}

.center {
  min-width: 0;
}

.centerPadded {
  padding: 20px;
}

.right {
  min-width: 0;
  border-inline-start: 1px solid var(--color-border);
}

.mobileSidebarHeader {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 18px 0;
}

.mobileCenter {
  display: grid;
  grid-template-rows: auto 1fr;
  min-height: 0;
}

.mobileCenter :global(.sd-header-primary-action) {
  display: none;
}

.error {
  display: grid;
  gap: 8px;
  margin-bottom: 16px;
  padding: 12px 14px;
  border-radius: 14px;
  background: rgba(176, 0, 32, 0.12);
  color: #8c1024;
  font-weight: 600;
}

.errorItem {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.errorItem span {
  min-width: 0;
}

@media (max-width: 1080px) {
  .columns {
    grid-template-columns: minmax(0, 1fr);
  }

  .right {
    border-inline-start: 0;
  }
}

@media (min-width: 1081px) {
  .mobileSidebarHeader {
    display: none;
  }
}
</style>
