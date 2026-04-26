<script setup lang="ts">
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import StructuredDiaryNavigation from '@/components/layout/StructuredDiaryNavigation.vue'
import EntryListPanel from '@/components/layout/EntryListPanel.vue'
import QuestionListPanel from '@/components/layout/QuestionListPanel.vue'
import OverlayPanel from '@/components/common/OverlayPanel.vue'
import AnswerHistoryList from '@/components/answers/AnswerHistoryList.vue'
import DiaryDetailView from '@/views/DiaryDetailView.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { WorkspaceRouteName } from '@/services/workspaceRoute'
import { mobileOverlayTitleForRoute } from '@/services/workspaceRoute'

const store = useStructuredDiaryStore()
const route = useRoute()
const diaryOverlayOpen = ref(false)
const isCompact = ref(false)
const mobileCenterOpen = ref(false)

const currentRouteName = computed<WorkspaceRouteName>(() => {
	const routeName = route.name
	return typeof routeName === 'string' ? routeName as WorkspaceRouteName : 'diaries'
})
const currentSidebar = computed<'entries' | 'questions'>(() =>
	currentRouteName.value === 'entries'
		|| currentRouteName.value === 'entry'
		|| currentRouteName.value === 'entryCreate'
		|| currentRouteName.value === 'entryEdit'
		? 'entries'
		: 'questions')
const mobileOverlayTitle = computed(() => mobileOverlayTitleForRoute(currentRouteName.value))

function updateCompactState(): void {
	isCompact.value = window.matchMedia('(max-width: 1080px)').matches
	if (!isCompact.value) {
		mobileCenterOpen.value = false
	}
}

function closeMobileCenter(): void {
	mobileCenterOpen.value = false
}


onMounted(async () => {
	await store.initialize()
	updateCompactState()
	window.addEventListener('resize', updateCompactState)
})

onBeforeUnmount(() => {
	window.removeEventListener('resize', updateCompactState)
})

watch(currentRouteName, (routeName) => {
	if (isCompact.value) {
		mobileCenterOpen.value = routeName !== 'entries' && routeName !== 'questions' && routeName !== 'diaries'
	}
}, { immediate: true })

watch(() => store.selectedDiaryId, async () => {
	await store.refreshSelectedDiaryWorkspace()
}, { immediate: true })

watch(() => store.selectedEntryId, async (entryId) => {
	if (entryId !== null) {
		await store.loadEntry(entryId)
	}
})
</script>

<template>
	<NcContent app-name="structureddiary">
		<StructuredDiaryNavigation />
		<NcAppContent :class="$style.content">
			<div :class="$style.workspace">
				<div :class="$style.columns">
					<section v-if="!isCompact" :class="$style.centerColumn">
						<router-view name="nav" />

						<main :class="$style.center">
							<div v-if="store.errors.length > 0" :class="$style.error">
								{{ store.errors[store.errors.length - 1].message }}
							</div>
							<router-view />
						</main>
					</section>

					<aside :class="$style.right">
						<EntryListPanel v-if="currentSidebar === 'entries'" />

						<QuestionListPanel v-else />
					</aside>
				</div>

				<OverlayPanel
					:open="isCompact && mobileCenterOpen"
					:title="mobileOverlayTitle"
					@close="closeMobileCenter()">
					<div :class="$style.mobileCenter">
						<router-view name="nav" />

						<main :class="$style.center">
							<div v-if="store.errors.length > 0" :class="$style.error">
								{{ store.errors[store.errors.length - 1].message }}
							</div>
							<router-view />
						</main>
					</div>
				</OverlayPanel>

				<OverlayPanel :open="diaryOverlayOpen" title="Diary overview" @close="diaryOverlayOpen = false">
					<DiaryDetailView :hide-stats="true" />
				</OverlayPanel>

			</div>
		</NcAppContent>
	</NcContent>
</template>

<style module>
.content {
	min-height: 100vh;
}

.workspace {
	display: grid;
	min-height: 100%;
}

.columns {
	display: grid;
	grid-template-columns: minmax(420px, 1fr) minmax(300px, 390px);
	min-height: 100vh;
}

.centerColumn {
	min-width: 0;
	display: grid;
	grid-template-rows: auto 1fr;
}

.center {
	min-width: 0;
	padding: 20px;
}

.right {
	min-width: 0;
	border-inline-start: 1px solid var(--color-border);
}

.mobileCenter {
	display: grid;
	grid-template-rows: auto 1fr;
	min-height: 0;
}

.error {
	margin-bottom: 16px;
	padding: 12px 14px;
	border-radius: 14px;
	background: rgba(176, 0, 32, 0.12);
	color: #8c1024;
	font-weight: 600;
}

@media (max-width: 1080px) {
	.columns {
		grid-template-columns: minmax(0, 1fr);
	}

	.right {
		border-inline-start: 0;
	}
}
</style>
