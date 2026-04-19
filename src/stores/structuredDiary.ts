import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { answerService, diaryService, entryService, questionService } from '@/services'
import { classifyDiaries } from '@/utils/diary'
import type {
	Answer,
	AnswerCreatePayload,
	AnswerUpdatePayload,
	Diary,
	DiaryCreatePayload,
	DiaryShare,
	DiaryStats,
	DiaryUpdatePayload,
	Entry,
	EntryCreatePayload,
	EntryUpdatePayload,
	Question,
	QuestionCreatePayload,
	QuestionTypeDefinition,
	QuestionUpdatePayload,
} from '@/types/types'

type CenterView = 'entry' | 'entry-edit' | 'diary' | 'diary-edit' | 'question' | 'question-edit'

function replaceById<T extends { id: number }>(items: T[], nextItem: T): T[] {
	const filtered = items.filter((item) => item.id !== nextItem.id)
	return [...filtered, nextItem]
}

export const useStructuredDiaryStore = defineStore('structuredDiary', () => {
	const diaries = ref<Diary[]>([])
	const diaryShares = ref<Record<number, DiaryShare[]>>({})
	const entriesByDiary = ref<Record<number, Entry[]>>({})
	const questionsByDiary = ref<Record<number, Question[]>>({})
	const questionVersionsById = ref<Record<number, Question[]>>({})
	const activeQuestionsByDiaryTimestamp = ref<Record<string, Question[]>>({})
	const answersByEntry = ref<Record<number, Answer[]>>({})
	const answerHistoryByEntryQuestion = ref<Record<string, Answer[]>>({})
	const diaryStatsById = ref<Record<number, DiaryStats>>({})
	const questionTypes = ref<QuestionTypeDefinition[]>([])
	const loading = ref(false)
	const error = ref<string | null>(null)
	const creatingDiary = ref(false)
	const creatingQuestion = ref(false)

	const selectedDiaryId = ref<number | null>(null)
	const selectedEntryId = ref<number | null>(null)
	const selectedQuestionId = ref<number | null>(null)
	const diarySearch = ref('')
	const questionSearch = ref('')
	const entryFromTimestamp = ref<number | null>(null)
	const entryUntilTimestamp = ref<number | null>(null)
	const centerView = ref<CenterView>('entry')

	const selectedDiary = computed(() => diaries.value.find((diary) => diary.id === selectedDiaryId.value) ?? null)
	const selectedEntry = computed(() => {
		if (selectedDiaryId.value === null || selectedEntryId.value === null) {
			return null
		}
		return (entriesByDiary.value[selectedDiaryId.value] ?? []).find((entry) => entry.id === selectedEntryId.value) ?? null
	})
	const selectedQuestion = computed(() => {
		if (selectedDiaryId.value === null || selectedQuestionId.value === null) {
			return null
		}
		return (questionsByDiary.value[selectedDiaryId.value] ?? []).find((question) => question.id === selectedQuestionId.value)
			?? Object.values(questionVersionsById.value).flat().find((question) => question.id === selectedQuestionId.value)
			?? null
	})

	const diaryGroups = computed(() => classifyDiaries(diaries.value, diarySearch.value))
	const currentEntries = computed(() => selectedDiaryId.value === null ? [] : entriesByDiary.value[selectedDiaryId.value] ?? [])
	const currentQuestions = computed(() => {
		if (selectedDiaryId.value === null) {
			return []
		}
		const questions = questionsByDiary.value[selectedDiaryId.value] ?? []
		if (questionSearch.value.trim() === '') {
			return questions
		}
		const query = questionSearch.value.toLocaleLowerCase()
		return questions.filter((question) =>
			question.label.toLocaleLowerCase().includes(query)
			|| question.display_text.toLocaleLowerCase().includes(query))
	})
	const currentAnswers = computed(() => selectedEntryId.value === null ? [] : answersByEntry.value[selectedEntryId.value] ?? [])

	async function runTask<T>(task: () => Promise<T>): Promise<T> {
		loading.value = true
		error.value = null
		try {
			return await task()
		} catch (taskError) {
			error.value = taskError instanceof Error ? taskError.message : 'Unknown error'
			throw taskError
		} finally {
			loading.value = false
		}
	}

	async function loadDiaries(): Promise<void> {
		await runTask(async () => {
			diaries.value = await diaryService.list()
			if (selectedDiaryId.value === null && diaries.value.length > 0) {
				selectedDiaryId.value = diaries.value[0].id
			}
		})
	}

	async function ensureQuestionTypes(): Promise<void> {
		if (questionTypes.value.length > 0) {
			return
		}
		questionTypes.value = await runTask(() => questionService.types())
	}

	async function loadDiary(id: number): Promise<void> {
		await runTask(async () => {
			const diary = await diaryService.get(id)
			diaries.value = replaceById(diaries.value, diary)
			selectedDiaryId.value = id
		})
	}

	async function loadDiaryShares(id: number): Promise<void> {
		diaryShares.value[id] = await runTask(() => diaryService.shares(id))
	}

	async function loadDiaryStats(id: number): Promise<void> {
		diaryStatsById.value[id] = await runTask(() => diaryService.stats(id))
	}

	async function loadEntries(diaryId: number, fromTimestamp?: number | null, untilTimestamp?: number | null): Promise<void> {
		const entries = await runTask(() => entryService.list(diaryId, fromTimestamp, untilTimestamp))
		entriesByDiary.value[diaryId] = entries
		if (selectedEntryId.value === null && entries.length > 0) {
			selectedEntryId.value = entries[0].id
		}
	}

	async function loadQuestions(diaryId: number): Promise<void> {
		const questions = await runTask(() => questionService.list(diaryId))
		questionsByDiary.value[diaryId] = questions
		if (selectedQuestionId.value === null && questions.length > 0) {
			selectedQuestionId.value = questions[0].id
		}
	}

	async function loadQuestionVersions(questionId: number): Promise<void> {
		questionVersionsById.value[questionId] = await runTask(() => questionService.versions(questionId))
	}

	async function loadQuestionsForTimestamp(diaryId: number, timestamp: number): Promise<void> {
		activeQuestionsByDiaryTimestamp.value[`${diaryId}:${timestamp}`] = await runTask(() =>
			questionService.listActive(diaryId, timestamp))
	}

	async function loadAnswers(entryId: number): Promise<void> {
		answersByEntry.value[entryId] = await runTask(() => answerService.list(entryId))
	}

	async function loadAnswerHistory(entryId: number, questionId: number): Promise<void> {
		answerHistoryByEntryQuestion.value[`${entryId}:${questionId}`] = await runTask(() =>
			answerService.history(entryId, questionId))
	}

	function setSelectedDiary(id: number | null): void {
		selectedDiaryId.value = id
		selectedEntryId.value = null
		selectedQuestionId.value = null
	}

	function setSelectedEntry(id: number | null): void {
		selectedEntryId.value = id
		if (id !== null) {
			centerView.value = 'entry'
		}
	}

	function setSelectedQuestion(id: number | null): void {
		selectedQuestionId.value = id
		if (id !== null) {
			centerView.value = 'question'
		}
	}

	async function saveDiary(payload: DiaryCreatePayload | DiaryUpdatePayload): Promise<Diary> {
		const saved = await runTask(async () => {
			if (creatingDiary.value || selectedDiaryId.value === null || centerView.value === 'diary-edit' && !selectedDiary.value) {
				return diaryService.create(payload as DiaryCreatePayload)
			}
			return diaryService.update(selectedDiaryId.value, payload as DiaryUpdatePayload)
		})
		diaries.value = replaceById(diaries.value, saved)
		selectedDiaryId.value = saved.id
		creatingDiary.value = false
		centerView.value = 'diary'
		return saved
	}

	async function saveEntry(payload: EntryCreatePayload | EntryUpdatePayload): Promise<Entry> {
		if (selectedDiaryId.value === null) {
			throw new Error('No diary selected.')
		}

		const saved = await runTask(async () => {
			if (centerView.value === 'entry-edit' && selectedEntryId.value !== null) {
				return entryService.update(selectedEntryId.value, payload as EntryUpdatePayload)
			}
			return entryService.create(selectedDiaryId.value, payload as EntryCreatePayload)
		})

		entriesByDiary.value[selectedDiaryId.value] = replaceById(entriesByDiary.value[selectedDiaryId.value] ?? [], saved)
			.sort((left, right) => right.timestamp - left.timestamp)
		selectedEntryId.value = saved.id
		centerView.value = 'entry'
		return saved
	}

	async function saveQuestion(payload: QuestionCreatePayload | QuestionUpdatePayload): Promise<Question> {
		if (selectedDiaryId.value === null) {
			throw new Error('No diary selected.')
		}

		const saved = await runTask(async () => {
			if (!creatingQuestion.value && centerView.value === 'question-edit' && selectedQuestionId.value !== null) {
				return questionService.update(selectedQuestionId.value, payload as QuestionUpdatePayload)
			}
			return questionService.create(selectedDiaryId.value, payload as QuestionCreatePayload)
		})

		await loadQuestions(selectedDiaryId.value)
		selectedQuestionId.value = saved.id
		creatingQuestion.value = false
		centerView.value = 'question'
		return saved
	}

	async function saveAnswer(entryId: number, payload: AnswerCreatePayload | AnswerUpdatePayload, currentAnswerId?: number | null): Promise<Answer> {
		const saved = await runTask(async () => {
			if (currentAnswerId) {
				return answerService.update(currentAnswerId, payload as AnswerUpdatePayload)
			}
			return answerService.create(entryId, payload as AnswerCreatePayload)
		})
		await loadAnswers(entryId)
		return saved
	}

	async function deleteAnswer(answerId: number, entryId: number): Promise<void> {
		await runTask(() => answerService.remove(answerId))
		await loadAnswers(entryId)
	}

	async function initialize(): Promise<void> {
		await ensureQuestionTypes()
		await loadDiaries()
		if (selectedDiaryId.value !== null) {
			await Promise.all([
				loadDiary(selectedDiaryId.value),
				loadEntries(selectedDiaryId.value, entryFromTimestamp.value, entryUntilTimestamp.value),
				loadQuestions(selectedDiaryId.value),
				loadDiaryShares(selectedDiaryId.value).catch(() => undefined),
			])
		}
	}

	return {
		diaries,
		diaryShares,
		entriesByDiary,
		questionsByDiary,
		questionVersionsById,
		activeQuestionsByDiaryTimestamp,
		answersByEntry,
		answerHistoryByEntryQuestion,
		diaryStatsById,
		questionTypes,
		loading,
		error,
		creatingDiary,
		creatingQuestion,
		selectedDiaryId,
		selectedEntryId,
		selectedQuestionId,
		diarySearch,
		questionSearch,
		entryFromTimestamp,
		entryUntilTimestamp,
		centerView,
		selectedDiary,
		selectedEntry,
		selectedQuestion,
		diaryGroups,
		currentEntries,
		currentQuestions,
		currentAnswers,
		initialize,
		loadDiaries,
		loadDiary,
		loadDiaryShares,
		loadDiaryStats,
		loadEntries,
		loadQuestions,
		loadQuestionVersions,
		loadQuestionsForTimestamp,
		loadAnswers,
		loadAnswerHistory,
		setSelectedDiary,
		setSelectedEntry,
		setSelectedQuestion,
		saveDiary,
		saveEntry,
		saveQuestion,
		saveAnswer,
		deleteAnswer,
	}
})
