<script setup lang="ts">
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import StructuredDiaryNavigation from '@/components/layout/StructuredDiaryNavigation.vue'
import HeaderDiaries from '@/components/layout/HeaderDiaries.vue'
import HeaderEntries from '@/components/layout/HeaderEntries.vue'
import HeaderQuestions from '@/components/layout/HeaderQuestions.vue'
import EntryListPanel from '@/components/layout/EntryListPanel.vue'
import QuestionListPanel from '@/components/layout/QuestionListPanel.vue'
import OverlayPanel from '@/components/common/OverlayPanel.vue'
import AnswerHistoryList from '@/components/answers/AnswerHistoryList.vue'
import DiaryDetailView from '@/views/DiaryDetailView.vue'
import { useStructuredDiaryStore, type DiaryEditSubmitPayload, type EntryEditSubmitPayload } from '@/stores/structuredDiary'
import type { DiaryUpdatePayload, QuestionCreatePayload, QuestionUpdatePayload } from '@/types/types'
import type { WorkspaceRouteName } from '@/services/workspaceRoute'
import { mobileOverlayTitleForRoute } from '@/services/workspaceRoute'

const store = useStructuredDiaryStore()
const route = useRoute()
const router = useRouter()
const diaryOverlayOpen = ref(false)
const isCompact = ref(false)
const mobileCenterOpen = ref(false)
const fromValue = ref(formatDateInputValue(daysAgo(7)))
const untilValue = ref(formatDateInputValue(new Date()))
const expandedQuestionId = ref<number | null>(null)
const answerHistoryQuestionId = ref<number | null>(null)

const currentRouteName = computed<WorkspaceRouteName>(() => {
	const routeName = route.name
	return typeof routeName === 'string' ? routeName as WorkspaceRouteName : 'entriesIndex'
})
const routeEntryId = computed(() => routeParamAsNumber(route.params.entryId))
const routeQuestionId = computed(() => routeParamAsNumber(route.params.questionId))
const currentHeader = computed<'entries' | 'questions' | 'diaries'>(() => {
	if (currentRouteName.value === 'diaries' || currentRouteName.value === 'diaryCreate' || currentRouteName.value === 'diaryEdit' || currentRouteName.value === 'diaryEditShare') {
		return 'diaries'
	}

	return currentSidebar.value
})
const currentSidebar = computed<'entries' | 'questions'>(() =>
	currentRouteName.value === 'entriesIndex'
		|| currentRouteName.value === 'entries'
		|| currentRouteName.value === 'entryCreate'
		|| currentRouteName.value === 'entryEdit'
		? 'entries'
		: 'questions')
const mobileOverlayTitle = computed(() => mobileOverlayTitleForRoute(currentRouteName.value))
const entryQuestions = computed(() => store.entryQuestionsForTimestamp(store.selectedEntry?.timestamp ?? null))
const entryEditorTimestamp = computed(() =>
	store.creatingEntry ? Math.floor(Date.now() / 1000) : (store.selectedEntry?.timestamp ?? Math.floor(Date.now() / 1000)))
const entryEditorQuestions = computed(() => store.entryQuestionsForTimestamp(entryEditorTimestamp.value))
const diaryEntryCount = computed(() => store.selectedDiaryStats?.entry_count ?? null)
const canChangeDiaryOwner = computed(() => store.creatingDiary || diaryEntryCount.value === 0)
type CenterListeners = Record<string, (...args: unknown[]) => unknown>
const centerProps = computed<Record<string, unknown>>(() => {
	switch (currentRouteName.value) {
		case 'entriesIndex':
		case 'entries':
			return {
				entry: store.selectedEntry,
				questions: entryQuestions.value,
				answers: store.currentAnswers,
				answerHistories: store.answerHistoryByEntryQuestion,
			}
		case 'entryCreate':
		case 'entryEdit':
			return {
				entry: store.creatingEntry ? null : store.selectedEntry,
				questions: entryEditorQuestions.value,
				answers: store.creatingEntry ? [] : store.currentAnswers,
			}
		case 'diaries':
			return {
				diary: store.selectedDiary,
				shares: store.selectedDiaryShares,
				stats: store.selectedDiaryStats,
			}
		case 'diaryCreate':
		case 'diaryEdit':
		case 'diaryEditShare':
			return {
				diary: store.creatingDiary ? null : store.selectedDiary,
				isCreating: store.creatingDiary,
				initialDraft: store.duplicatedDiaryDraft,
				entryCount: diaryEntryCount.value,
				canChangeOwner: canChangeDiaryOwner.value,
				shares: store.selectedDiaryShares,
			}
		case 'questionsIndex':
		case 'questions':
			return {
				question: store.selectedQuestion,
				versionChain: store.selectedQuestionVersionChain,
			}
		case 'questionCreate':
		case 'questionEdit':
			return {
				question: store.selectedQuestion,
				types: store.questionTypes,
			}
	}
})
const centerListeners = computed<CenterListeners>(() => {
	switch (currentRouteName.value) {
		case 'entriesIndex':
		case 'entries':
			return {
				loadHistory: openAnswerHistory,
				deleteAnswer: deleteCurrentAnswer,
			}
		case 'entryCreate':
		case 'entryEdit':
			return {
				save: saveEntry,
				cancel: cancelEntryEdit,
			}
		case 'diaryCreate':
		case 'diaryEdit':
		case 'diaryEditShare':
			return {
				save: saveDiary,
				duplicate: duplicateDiary,
				delete: deleteCurrentDiary,
				cancel: cancelDiaryEdit,
			}
		case 'questionCreate':
		case 'questionEdit':
			return {
				save: saveQuestion,
				cancel: cancelQuestionEdit,
			}
		default:
			return {}
	}
})

store.entryFromTimestamp = timestampFromDateInput(fromValue.value, false)
store.entryUntilTimestamp = timestampFromDateInput(untilValue.value, true)

function daysAgo(days: number): Date {
	const value = new Date()
	value.setHours(0, 0, 0, 0)
	value.setDate(value.getDate() - days)
	return value
}

function formatDateInputValue(date: Date): string {
	const year = date.getFullYear()
	const month = String(date.getMonth() + 1).padStart(2, '0')
	const day = String(date.getDate()).padStart(2, '0')
	return `${year}-${month}-${day}`
}

function timestampFromDateInput(value: string, endOfDay: boolean): number | null {
	if (value === '') {
		return null
	}

	return Math.floor(new Date(`${value}T${endOfDay ? '23:59:59' : '00:00:00'}`).getTime() / 1000)
}

function routeParamAsNumber(value: unknown): number | null {
	if (typeof value !== 'string' || value.trim() === '') {
		return null
	}

	const parsed = Number.parseInt(value, 10)
	return Number.isFinite(parsed) ? parsed : null
}

function updateCompactState(): void {
	isCompact.value = window.matchMedia('(max-width: 1080px)').matches
	if (!isCompact.value) {
		mobileCenterOpen.value = false
	}
}

function closeMobileCenter(): void {
	mobileCenterOpen.value = false
}

async function routeTo(routeName: WorkspaceRouteName): Promise<void> {
	await router.push({ name: routeName })
}

async function routeToEntriesIndex(): Promise<void> {
	if (store.selectedDiaryId === null) {
		await router.push({ name: 'diaries' })
		return
	}

	await router.push({ name: 'entriesIndex', params: { diaryId: store.selectedDiaryId } })
}

async function routeToEntry(entryId: number | null): Promise<void> {
	if (entryId === null) {
		await routeToEntriesIndex()
		return
	}

	if (store.selectedDiaryId === null) {
		return
	}

	await router.push({ name: 'entries', params: { diaryId: store.selectedDiaryId, entryId } })
}

async function routeToQuestionsIndex(): Promise<void> {
	if (store.selectedDiaryId === null) {
		await router.push({ name: 'diaries' })
		return
	}

	await router.push({ name: 'questionsIndex', params: { diaryId: store.selectedDiaryId } })
}

async function routeToQuestion(questionId: number | null): Promise<void> {
	if (questionId === null) {
		await routeToQuestionsIndex()
		return
	}

	if (store.selectedDiaryId === null) {
		return
	}

	await router.push({ name: 'questions', params: { diaryId: store.selectedDiaryId, questionId } })
}

async function openCenter(routeName: WorkspaceRouteName): Promise<void> {
	if (isCompact.value) {
		mobileCenterOpen.value = true
	}
	await routeTo(routeName)
}

async function applyEntryFilter(): Promise<void> {
	if (store.selectedDiaryId === null) {
		return
	}

	store.entryFromTimestamp = timestampFromDateInput(fromValue.value, false)
	store.entryUntilTimestamp = timestampFromDateInput(untilValue.value, true)
	await store.loadEntries(store.selectedDiaryId, store.entryFromTimestamp, store.entryUntilTimestamp)
}

async function openVersionChain(questionId: number): Promise<void> {
	expandedQuestionId.value = expandedQuestionId.value === questionId ? null : questionId
	await store.loadQuestionVersions(questionId)
}

async function openAnswerHistory(questionId: number): Promise<void> {
	if (store.selectedEntryId === null) {
		return
	}
	answerHistoryQuestionId.value = questionId
	await store.loadAnswerHistory(store.selectedEntryId, questionId)
}

async function deleteCurrentAnswer(answerId: number): Promise<void> {
	if (store.selectedEntryId === null) {
		return
	}
	await store.deleteAnswer(answerId, store.selectedEntryId)
}

async function createEntry(): Promise<void> {
	store.startCreatingEntry()
	await openCenter('entryCreate')
}

async function createQuestion(): Promise<void> {
	if (store.selectedDiaryId === null) {
		return
	}

	store.startCreatingQuestion()
	if (isCompact.value) {
		mobileCenterOpen.value = true
	}
	await router.push({ name: 'questionCreate', params: { diaryId: store.selectedDiaryId } })
}

async function selectEntry(entryId: number): Promise<void> {
	if (isCompact.value) {
		mobileCenterOpen.value = true
	}
	await routeToEntry(entryId)
}

async function selectQuestion(questionId: number): Promise<void> {
	if (isCompact.value) {
		mobileCenterOpen.value = true
	}
	await routeToQuestion(questionId)
}

async function saveEntry(payload: EntryEditSubmitPayload): Promise<void> {
	const entry = await store.saveEntryWithAnswers(payload)
	await routeToEntry(entry.id)
}

async function cancelEntryEdit(): Promise<void> {
	store.cancelEntryEditing()
	closeMobileCenter()
	await routeToEntry(store.selectedEntryId)
}

async function saveDiary(payload: DiaryEditSubmitPayload): Promise<void> {
	await store.saveDiaryWithShares(payload)
	await routeTo('diaries')
}

async function duplicateDiary(payload: DiaryUpdatePayload): Promise<void> {
	store.prepareDiaryDuplicate(payload)
	await openCenter('diaryEdit')
}

async function cancelDiaryEdit(): Promise<void> {
	store.cancelDiaryCreation()
	closeMobileCenter()
	await routeTo('diaries')
}

async function deleteCurrentDiary(): Promise<void> {
	await store.deleteSelectedDiary()
	await router.push({ name: 'diaries' })
}

async function saveQuestion(payload: QuestionCreatePayload | QuestionUpdatePayload): Promise<void> {
	const question = await store.saveQuestionAndReloadVersions(payload)
	await routeToQuestion(question.id)
}

async function cancelQuestionEdit(): Promise<void> {
	store.cancelQuestionCreation()
	closeMobileCenter()
	await routeToQuestion(store.selectedQuestionId)
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
		mobileCenterOpen.value = routeName !== 'entriesIndex' && routeName !== 'questionsIndex'
	}
}, { immediate: true })

watch([currentRouteName, routeEntryId], ([routeName, entryId]) => {
	if (routeName === 'entryCreate') {
		store.startCreatingEntry()
		return
	}

	if (routeName === 'entryEdit' || routeName === 'entries') {
		if (entryId !== store.selectedEntryId) {
			store.setSelectedEntry(entryId)
		}
		store.startEditingEntry()
		return
	}

	if (routeName === 'entriesIndex') {
		store.setSelectedEntry(null)
		return
	}

	store.setSelectedEntry(null)
}, { immediate: true })

watch([currentRouteName, routeQuestionId], ([routeName, questionId]) => {
	if (routeName === 'questionCreate') {
		store.startCreatingQuestion()
		return
	}

	if (routeName === 'questionEdit' || routeName === 'questions') {
		if (questionId !== store.selectedQuestionId) {
			store.setSelectedQuestion(questionId)
		}
		store.cancelQuestionCreation()
		return
	}

	if (routeName === 'questionsIndex') {
		store.setSelectedQuestion(null)
		return
	}

	store.setSelectedQuestion(null)
}, { immediate: true })

watch(() => store.selectedDiaryId, async () => {
	await store.refreshSelectedDiaryWorkspace()
}, { immediate: true })

watch(() => store.selectedEntryId, async () => {
	await store.refreshSelectedEntryContext()
})

watch([currentRouteName, () => store.selectedEntryId], async ([routeName, entryId]) => {
	if (routeName === 'entriesIndex' && entryId !== null) {
		await routeToEntry(entryId)
		return
	}

	if (routeName === 'entries' && routeEntryId.value !== entryId) {
		await routeToEntry(entryId)
	}
})

watch([currentRouteName, () => store.selectedQuestionId], async ([routeName, questionId]) => {
	if (routeName === 'questionsIndex' && questionId !== null) {
		await routeToQuestion(questionId)
		return
	}

	if (routeName === 'questions' && routeQuestionId.value !== questionId) {
		await routeToQuestion(questionId)
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
            <router-view name="nav">

						<main :class="$style.center">
							<div v-if="store.error" :class="$style.error">
								{{ store.error }}
							</div>
							<router-view v-slot="{ Component }">
								<component :is="Component" v-bind="centerProps" v-on="centerListeners" />
							</router-view>
						</main>
					</section>

					<aside :class="$style.right">
						<EntryListPanel
							v-if="currentSidebar === 'entries'"
							:entries="store.currentEntries"
							:selected-entry-id="store.selectedEntryId"
							:from-value="fromValue"
							:until-value="untilValue"
							:show-create-button="isCompact"
							@select="selectEntry($event.id)"
							@create="createEntry()"
							@update:from-value="fromValue = $event"
							@update:until-value="untilValue = $event"
							@apply-filter="applyEntryFilter" />

						<QuestionListPanel
							v-else
							:questions="store.currentQuestions"
							:selected-question-id="store.selectedQuestionId"
							:version-map="store.questionVersionsById"
							:expanded-question-id="expandedQuestionId"
							:search="store.questionSearch"
							@update:search="store.questionSearch = $event"
							@create="createQuestion()"
							@select="selectQuestion($event.id)"
							@toggle-versions="openVersionChain($event.id)" />
					</aside>
				</div>

				<OverlayPanel
					:open="isCompact && mobileCenterOpen"
					:title="mobileOverlayTitle"
					@close="closeMobileCenter()">
					<div :class="$style.mobileCenter">
						<HeaderEntries v-if="currentHeader === 'entries'" />
						<HeaderQuestions v-else-if="currentHeader === 'questions'" />
						<HeaderDiaries v-else />

						<main :class="$style.center">
							<div v-if="store.error" :class="$style.error">
								{{ store.error }}
							</div>
							<router-view v-slot="{ Component }">
								<component :is="Component" v-bind="centerProps" v-on="centerListeners" />
							</router-view>
						</main>
					</div>
				</OverlayPanel>

				<OverlayPanel :open="diaryOverlayOpen" title="Diary overview" @close="diaryOverlayOpen = false">
					<DiaryDetailView
						:diary="store.selectedDiary"
						:shares="store.selectedDiaryShares"
						:stats="store.selectedDiaryStats"
						:hide-stats="true" />
				</OverlayPanel>

				<OverlayPanel
					:open="answerHistoryQuestionId !== null"
					title="Answer versions"
					@close="answerHistoryQuestionId = null">
					<AnswerHistoryList
						:question="store.currentQuestions.find((question) => question.id === answerHistoryQuestionId) ?? null"
						:answers="answerHistoryQuestionId === null || store.selectedEntryId === null
							? []
							: store.answerHistoryByEntryQuestion[`${store.selectedEntryId}:${answerHistoryQuestionId}`] ?? []"
						@delete="deleteCurrentAnswer" />
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
