<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import WorkspaceHeader from '@/components/layout/WorkspaceHeader.vue'
import DiarySidebar from '@/components/layout/DiarySidebar.vue'
import EntryListPanel from '@/components/layout/EntryListPanel.vue'
import QuestionListPanel from '@/components/layout/QuestionListPanel.vue'
import DiaryShareEditor from '@/components/diaries/DiaryShareEditor.vue'
import OverlayPanel from '@/components/common/OverlayPanel.vue'
import EntryDetailView from '@/views/EntryDetailView.vue'
import EntryEditView from '@/views/EntryEditView.vue'
import DiaryDetailView from '@/views/DiaryDetailView.vue'
import DiaryEditView from '@/views/DiaryEditView.vue'
import QuestionDetailView from '@/views/QuestionDetailView.vue'
import QuestionEditView from '@/views/QuestionEditView.vue'
import AnswerHistoryList from '@/components/answers/AnswerHistoryList.vue'
import { diaryService } from '@/services'
import type { Answer } from '@/types/types'

const store = useStructuredDiaryStore()
const diaryOverlayOpen = ref(false)
const shareEditorOpen = ref(false)
const fromValue = ref('')
const untilValue = ref('')
const expandedQuestionId = ref<number | null>(null)
const answerHistoryQuestionId = ref<number | null>(null)

const visibleCenter = computed(() => store.centerView)

onMounted(async () => {
	await store.initialize()
})

watch(() => store.selectedDiaryId, async (diaryId) => {
	if (diaryId === null) {
		return
	}
	await Promise.all([
		store.loadEntries(diaryId, store.entryFromTimestamp, store.entryUntilTimestamp),
		store.loadQuestions(diaryId),
		store.loadDiaryStats(diaryId).catch(() => undefined),
		store.loadDiaryShares(diaryId).catch(() => undefined),
	])
}, { immediate: true })

watch(() => store.selectedEntryId, async (entryId) => {
	if (entryId !== null) {
		await store.loadAnswers(entryId)
		if (store.selectedDiaryId !== null && store.selectedEntry !== null) {
			await store.loadQuestionsForTimestamp(store.selectedDiaryId, store.selectedEntry.timestamp)
		}
	}
})

async function selectDiary(id: number): Promise<void> {
	store.setSelectedDiary(id)
	store.creatingDiary = false
	store.creatingQuestion = false
	await store.loadDiary(id)
	await Promise.all([
		store.loadEntries(id, store.entryFromTimestamp, store.entryUntilTimestamp),
		store.loadQuestions(id),
		store.loadDiaryStats(id).catch(() => undefined),
		store.loadDiaryShares(id).catch(() => undefined),
	])
	store.centerView = 'diary'
}

function createDiary(): void {
	store.creatingDiary = true
	store.setSelectedDiary(null)
	store.centerView = 'diary-edit'
}

async function applyEntryFilter(): Promise<void> {
	if (store.selectedDiaryId === null) {
		return
	}

	store.entryFromTimestamp = fromValue.value === '' ? null : Math.floor(new Date(`${fromValue.value}T00:00:00`).getTime() / 1000)
	store.entryUntilTimestamp = untilValue.value === '' ? null : Math.floor(new Date(`${untilValue.value}T23:59:59`).getTime() / 1000)
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

function createQuestion(): void {
	store.creatingQuestion = true
	store.setSelectedQuestion(null)
	store.centerView = 'question-edit'
}

function entryAnswer(questionId: number): Answer | undefined {
	return store.currentAnswers.find((answer) => answer.question_id === questionId)
}

async function saveEntry(payload: { title: string | null, timestamp: number, answers: Answer[] }): Promise<void> {
	const entry = await store.saveEntry({
		title: payload.title,
		timestamp: payload.timestamp,
	})
	for (const answer of payload.answers) {
		const existing = entryAnswer(answer.question_id)
		if (answer.text_content === null && answer.numeric_content === null) {
			continue
		}
		if (existing && existing.text_content === answer.text_content && existing.numeric_content === answer.numeric_content) {
			continue
		}
		await store.saveAnswer(entry.id, existing
			? { textContent: answer.text_content, numericContent: answer.numeric_content }
			: { questionId: answer.question_id, textContent: answer.text_content, numericContent: answer.numeric_content }, existing?.id)
	}
	await store.loadAnswers(entry.id)
}

async function saveDiaryShares(payload: Array<{ sharedWith: string, permission: number }>): Promise<void> {
	if (store.selectedDiaryId === null) {
		return
	}

	const currentShares = store.diaryShares[store.selectedDiaryId] ?? []
	const currentByUser = new Map(currentShares.map((share) => [share.shared_with, share]))
	for (const item of payload) {
		const existing = currentByUser.get(item.sharedWith)
		if (existing) {
			await diaryService.updateShare(store.selectedDiaryId, existing.id, item.permission)
			currentByUser.delete(item.sharedWith)
		} else {
			await diaryService.createShare(store.selectedDiaryId, item.sharedWith, item.permission)
		}
	}
	for (const orphan of currentByUser.values()) {
		await diaryService.deleteShare(store.selectedDiaryId, orphan.id)
	}
	await store.loadDiaryShares(store.selectedDiaryId)
	shareEditorOpen.value = false
}

async function saveDiary(payload: any): Promise<void> {
	await store.saveDiary(payload)
	if (store.selectedDiaryId !== null) {
		await store.loadDiaryShares(store.selectedDiaryId).catch(() => undefined)
	}
}

async function saveQuestion(payload: any): Promise<void> {
	await store.saveQuestion(payload)
	if (store.selectedQuestionId !== null) {
		await store.loadQuestionVersions(store.selectedQuestionId)
	}
}

async function deleteCurrentAnswer(answerId: number): Promise<void> {
	if (store.selectedEntryId === null) {
		return
	}
	await store.deleteAnswer(answerId, store.selectedEntryId)
}
</script>

<template>
	<div :class="$style.workspace">
		<WorkspaceHeader
			:diary="store.selectedDiary"
			:entry="store.selectedEntry"
			:question="store.selectedQuestion"
			:view="visibleCenter"
			@open-diary="diaryOverlayOpen = true"
			@new-diary="createDiary()"
			@new-entry="store.centerView = 'entry-edit'"
			@edit-entry="store.centerView = 'entry-edit'"
			@edit-diary="store.centerView = 'diary-edit'"
			@edit-question="store.centerView = 'question-edit'" />

		<div :class="$style.columns">
			<DiarySidebar
				:groups="store.diaryGroups"
				:search="store.diarySearch"
				:selected-diary-id="store.selectedDiaryId"
				@update:search="store.diarySearch = $event"
				@select="selectDiary($event.id)"
				@create="createDiary()"
				@manage="store.centerView = 'diary-edit'" />

			<main :class="$style.center">
				<div v-if="store.error" :class="$style.error">
					{{ store.error }}
				</div>

				<EntryDetailView
					v-if="visibleCenter === 'entry'"
					:entry="store.selectedEntry"
					:questions="store.activeQuestionsByDiaryTimestamp[`${store.selectedDiaryId ?? 0}:${store.selectedEntry?.timestamp ?? 0}`] ?? store.currentQuestions"
					:answers="store.currentAnswers"
					:answer-histories="store.answerHistoryByEntryQuestion"
					@load-history="openAnswerHistory"
					@delete-answer="deleteCurrentAnswer" />

				<EntryEditView
					v-else-if="visibleCenter === 'entry-edit'"
					:entry="store.selectedEntry"
					:questions="store.activeQuestionsByDiaryTimestamp[`${store.selectedDiaryId ?? 0}:${store.selectedEntry?.timestamp ?? Math.floor(Date.now() / 1000)}`] ?? store.currentQuestions"
					:answers="store.currentAnswers"
					@save="saveEntry"
					@cancel="store.centerView = 'entry'" />

				<DiaryDetailView
					v-else-if="visibleCenter === 'diary'"
					:diary="store.selectedDiary"
					:shares="store.selectedDiaryId === null ? [] : store.diaryShares[store.selectedDiaryId] ?? []"
					:stats="store.selectedDiaryId === null ? null : store.diaryStatsById[store.selectedDiaryId] ?? null" />

				<DiaryEditView
					v-else-if="visibleCenter === 'diary-edit'"
					:diary="store.selectedDiary"
					:can-change-owner="(store.diaryStatsById[store.selectedDiaryId ?? 0]?.entry_count ?? 0) === 0"
					:shares="store.selectedDiaryId === null ? [] : store.diaryShares[store.selectedDiaryId] ?? []"
					@save-diary="saveDiary"
					@save-shares="saveDiaryShares"
					@cancel="store.creatingDiary = false; store.centerView = 'diary'" />

				<QuestionDetailView
					v-else-if="visibleCenter === 'question'"
					:question="store.selectedQuestion"
					:version-chain="store.selectedQuestionId === null ? [] : store.questionVersionsById[store.selectedQuestionId] ?? []" />

				<QuestionEditView
					v-else-if="visibleCenter === 'question-edit'"
					:question="store.selectedQuestion"
					:types="store.questionTypes"
					@save="saveQuestion"
					@cancel="store.creatingQuestion = false; store.centerView = 'question'" />
			</main>

			<div :class="$style.right">
				<EntryListPanel
					v-if="visibleCenter === 'entry' || visibleCenter === 'entry-edit'"
					:entries="store.currentEntries"
					:selected-entry-id="store.selectedEntryId"
					:from-value="fromValue"
					:until-value="untilValue"
					@select="store.setSelectedEntry($event.id)"
					@create="store.centerView = 'entry-edit'"
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
					@select="store.setSelectedQuestion($event.id)"
					@toggle-versions="openVersionChain($event.id)" />
			</div>
		</div>

		<OverlayPanel :open="diaryOverlayOpen" title="Diary overview" @close="diaryOverlayOpen = false">
			<DiaryDetailView
				:diary="store.selectedDiary"
				:shares="store.selectedDiaryId === null ? [] : store.diaryShares[store.selectedDiaryId] ?? []"
				:stats="store.selectedDiaryId === null ? null : store.diaryStatsById[store.selectedDiaryId] ?? null"
				:hide-stats="true" />
		</OverlayPanel>

		<OverlayPanel :open="shareEditorOpen" title="Share diary" @close="shareEditorOpen = false">
			<DiaryShareEditor
				:shares="store.selectedDiaryId === null ? [] : store.diaryShares[store.selectedDiaryId] ?? []"
				@save="saveDiaryShares"
				@cancel="shareEditorOpen = false" />
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
</template>

<style module>
.workspace {
	display: grid;
	min-height: 100%;
}

.columns {
	display: grid;
	grid-template-columns: minmax(220px, 280px) minmax(420px, 1fr) minmax(280px, 360px);
	min-height: calc(100vh - 88px);
}

.center {
	min-width: 0;
	padding: 20px;
	background:
		radial-gradient(circle at top center, rgba(255, 237, 213, 0.35), transparent 40%),
		linear-gradient(180deg, #fff8f3, #f4f7fb);
}

.right {
	min-width: 0;
}

.error {
	margin-bottom: 16px;
	border-radius: 18px;
	padding: 14px 16px;
	background: rgba(217, 105, 65, 0.12);
	color: #9d3f1e;
	font-weight: 600;
}

@media (max-width: 1180px) {
	.columns {
		grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
	}

	.right {
		display: none;
	}
}

@media (max-width: 860px) {
	.columns {
		grid-template-columns: 1fr;
	}
}
</style>
