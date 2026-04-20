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

export interface DiaryShareInput {
	sharedWith: string
	permission: number
}

export interface DiaryEditSubmitPayload {
	diary: DiaryUpdatePayload
	shares: DiaryShareInput[]
}

export interface EntryEditSubmitPayload {
	title: string | null
	timestamp: number
	answers: Answer[]
}

function replaceById<T extends { id: number }>(items: T[], nextItem: T): T[] {
	const filtered = items.filter((item) => item.id !== nextItem.id)
	return [...filtered, nextItem]
}

function cloneQuestionAsCreatePayload(question: Question): QuestionCreatePayload {
	return {
		label: question.label,
		displayText: question.display_text,
		type: question.type,
		minimum: question.minimum,
		maximum: question.maximum,
		choices: question.choices,
		active: question.active,
		templateText: question.template_text,
	}
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
	const creatingEntry = ref(false)
	const creatingQuestion = ref(false)
	const duplicatedDiaryDraft = ref<DiaryUpdatePayload | null>(null)
	const duplicatedQuestions = ref<QuestionCreatePayload[]>([])

	const selectedDiaryId = ref<number | null>(null)
	const selectedEntryId = ref<number | null>(null)
	const selectedQuestionId = ref<number | null>(null)
	const diarySearch = ref('')
	const questionSearch = ref('')
	const entryFromTimestamp = ref<number | null>(null)
	const entryUntilTimestamp = ref<number | null>(null)

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
	const selectedDiaryShares = computed(() => selectedDiaryId.value === null ? [] : diaryShares.value[selectedDiaryId.value] ?? [])
	const selectedDiaryStats = computed(() => selectedDiaryId.value === null ? null : diaryStatsById.value[selectedDiaryId.value] ?? null)
	const selectedQuestionVersionChain = computed(() =>
		selectedQuestionId.value === null ? [] : questionVersionsById.value[selectedQuestionId.value] ?? [])

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
		if (selectedEntryId.value !== null && !entries.some((entry) => entry.id === selectedEntryId.value)) {
			selectedEntryId.value = !creatingEntry.value && entries.length > 0 ? entries[0].id : null
		}
		if (!creatingEntry.value && selectedEntryId.value === null && entries.length > 0) {
			selectedEntryId.value = entries[0].id
		}
	}

	async function loadQuestions(diaryId: number): Promise<void> {
		const questions = await runTask(() => questionService.list(diaryId))
		questionsByDiary.value[diaryId] = questions
		if (selectedQuestionId.value !== null && !questions.some((question) => question.id === selectedQuestionId.value)) {
			selectedQuestionId.value = !creatingQuestion.value && questions.length > 0 ? questions[0].id : null
		}
		if (!creatingQuestion.value && selectedQuestionId.value === null && questions.length > 0) {
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

	async function refreshSelectedDiaryWorkspace(): Promise<void> {
		if (selectedDiaryId.value === null) {
			return
		}

		await Promise.all([
			loadEntries(selectedDiaryId.value, entryFromTimestamp.value, entryUntilTimestamp.value),
			loadQuestions(selectedDiaryId.value),
			loadDiaryStats(selectedDiaryId.value).catch(() => undefined),
			loadDiaryShares(selectedDiaryId.value).catch(() => undefined),
		])
	}

	async function refreshSelectedEntryContext(): Promise<void> {
		if (selectedEntryId.value === null) {
			return
		}

		await loadAnswers(selectedEntryId.value)
		if (selectedDiaryId.value !== null && selectedEntry.value !== null) {
			await loadQuestionsForTimestamp(selectedDiaryId.value, selectedEntry.value.timestamp)
		}
	}

	function setSelectedDiary(id: number | null): void {
		selectedDiaryId.value = id
		selectedEntryId.value = null
		selectedQuestionId.value = null
		creatingEntry.value = false
		creatingQuestion.value = false
	}

	function setSelectedEntry(id: number | null): void {
		selectedEntryId.value = id
		creatingEntry.value = false
	}

	function setSelectedQuestion(id: number | null): void {
		selectedQuestionId.value = id
		creatingQuestion.value = false
	}

	function startCreatingDiary(): void {
		creatingDiary.value = true
		duplicatedDiaryDraft.value = null
		duplicatedQuestions.value = []
		setSelectedDiary(null)
	}

	function cancelDiaryCreation(): void {
		creatingDiary.value = false
		duplicatedDiaryDraft.value = null
		duplicatedQuestions.value = []
	}

	function prepareDiaryDuplicate(payload: DiaryUpdatePayload): void {
		const questionsToDuplicate = selectedDiaryId.value === null ? [] : questionsByDiary.value[selectedDiaryId.value] ?? []
		duplicatedDiaryDraft.value = {
			...payload,
			ownerUserId: '',
		}
		duplicatedQuestions.value = questionsToDuplicate.map(cloneQuestionAsCreatePayload)
		creatingDiary.value = true
	}

	function startCreatingEntry(): void {
		setSelectedEntry(null)
		creatingEntry.value = true
	}

	function startEditingEntry(): void {
		creatingEntry.value = false
	}

	function cancelEntryEditing(): void {
		creatingEntry.value = false
	}

	function startCreatingQuestion(): void {
		setSelectedQuestion(null)
		creatingQuestion.value = true
	}

	function cancelQuestionCreation(): void {
		creatingQuestion.value = false
	}

	function entryQuestionsForTimestamp(timestamp: number | null): Question[] {
		if (selectedDiaryId.value === null || timestamp === null) {
			return currentQuestions.value
		}
		return activeQuestionsByDiaryTimestamp.value[`${selectedDiaryId.value}:${timestamp}`] ?? currentQuestions.value
	}

	function currentEntryAnswer(questionId: number): Answer | undefined {
		return currentAnswers.value.find((answer) => answer.question_id === questionId)
	}

	async function saveDiary(payload: DiaryCreatePayload | DiaryUpdatePayload): Promise<Diary> {
		const saved = await runTask(async () => {
			if (creatingDiary.value || selectedDiaryId.value === null || selectedDiary.value === null) {
				return diaryService.create(payload as DiaryCreatePayload)
			}
			return diaryService.update(selectedDiaryId.value, payload as DiaryUpdatePayload)
		})
		diaries.value = replaceById(diaries.value, saved)
		selectedDiaryId.value = saved.id
		creatingDiary.value = false
		return saved
	}

	async function saveDiaryWithShares(payload: DiaryEditSubmitPayload): Promise<Diary> {
		const wasCreating = creatingDiary.value
		const ownerUserId = payload.diary.ownerUserId?.trim() ?? ''
		const savedDiary = await saveDiary(payload.diary)

		if (wasCreating && ownerUserId !== '' && ownerUserId !== savedDiary.user_id) {
			await diaryService.update(savedDiary.id, { ownerUserId })
			await loadDiary(savedDiary.id)
		}

		const currentByUser = new Map(selectedDiaryShares.value.map((share) => [share.shared_with, share]))
		for (const item of payload.shares) {
			const existing = currentByUser.get(item.sharedWith)
			if (existing) {
				await diaryService.updateShare(savedDiary.id, existing.id, item.permission)
				currentByUser.delete(item.sharedWith)
			} else {
				await diaryService.createShare(savedDiary.id, item.sharedWith, item.permission)
			}
		}
		for (const orphan of currentByUser.values()) {
			await diaryService.deleteShare(savedDiary.id, orphan.id)
		}

		if (wasCreating && duplicatedQuestions.value.length > 0) {
			for (const question of duplicatedQuestions.value) {
				await questionService.create(savedDiary.id, question)
			}
		}

		duplicatedDiaryDraft.value = null
		duplicatedQuestions.value = []
		await loadDiaryShares(savedDiary.id).catch(() => undefined)
		await loadQuestions(savedDiary.id).catch(() => undefined)
		await loadDiaryStats(savedDiary.id).catch(() => undefined)

		return savedDiary
	}

	async function saveEntry(payload: EntryCreatePayload | EntryUpdatePayload): Promise<Entry> {
		if (selectedDiaryId.value === null) {
			throw new Error('No diary selected.')
		}

		const saved = await runTask(async () => {
			if (!creatingEntry.value && selectedEntryId.value !== null) {
				return entryService.update(selectedEntryId.value, payload as EntryUpdatePayload)
			}
			return entryService.create(selectedDiaryId.value, payload as EntryCreatePayload)
		})

		entriesByDiary.value[selectedDiaryId.value] = replaceById(entriesByDiary.value[selectedDiaryId.value] ?? [], saved)
			.sort((left, right) => right.timestamp - left.timestamp)
		selectedEntryId.value = saved.id
		creatingEntry.value = false
		return saved
	}

	async function saveEntryWithAnswers(payload: EntryEditSubmitPayload): Promise<Entry> {
		const entry = await saveEntry({
			title: payload.title,
			timestamp: payload.timestamp,
		})

		for (const answer of payload.answers) {
			const existing = currentEntryAnswer(answer.question_id)
			if (answer.text_content === null && answer.numeric_content === null) {
				continue
			}
			if (existing && existing.text_content === answer.text_content && existing.numeric_content === answer.numeric_content) {
				continue
			}
			await saveAnswer(entry.id, existing
				? { textContent: answer.text_content, numericContent: answer.numeric_content }
				: { questionId: answer.question_id, textContent: answer.text_content, numericContent: answer.numeric_content }, existing?.id)
		}

		await loadAnswers(entry.id)
		return entry
	}

	async function saveQuestion(payload: QuestionCreatePayload | QuestionUpdatePayload): Promise<Question> {
		if (selectedDiaryId.value === null) {
			throw new Error('No diary selected.')
		}

		const saved = await runTask(async () => {
			if (!creatingQuestion.value && selectedQuestionId.value !== null) {
				return questionService.update(selectedQuestionId.value, payload as QuestionUpdatePayload)
			}
			return questionService.create(selectedDiaryId.value, payload as QuestionCreatePayload)
		})

		await loadQuestions(selectedDiaryId.value)
		selectedQuestionId.value = saved.id
		creatingQuestion.value = false
		return saved
	}

	async function saveQuestionAndReloadVersions(payload: QuestionCreatePayload | QuestionUpdatePayload): Promise<Question> {
		const saved = await saveQuestion(payload)
		await loadQuestionVersions(saved.id)
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

	async function deleteSelectedDiary(): Promise<void> {
		if (selectedDiaryId.value === null) {
			return
		}

		await runTask(() => diaryService.remove(selectedDiaryId.value as number))
		const removedDiaryId = selectedDiaryId.value
		cancelDiaryCreation()
		if (removedDiaryId !== null) {
			delete diaryShares.value[removedDiaryId]
			delete entriesByDiary.value[removedDiaryId]
			delete questionsByDiary.value[removedDiaryId]
			delete diaryStatsById.value[removedDiaryId]
		}
		selectedDiaryId.value = null
		selectedEntryId.value = null
		selectedQuestionId.value = null

		await loadDiaries()
		if (selectedDiaryId.value !== null) {
			await Promise.all([
				loadEntries(selectedDiaryId.value, entryFromTimestamp.value, entryUntilTimestamp.value),
				loadQuestions(selectedDiaryId.value),
				loadDiaryStats(selectedDiaryId.value).catch(() => undefined),
				loadDiaryShares(selectedDiaryId.value).catch(() => undefined),
			])
		}
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
		creatingEntry,
		creatingQuestion,
		duplicatedDiaryDraft,
		duplicatedQuestions,
		selectedDiaryId,
		selectedEntryId,
		selectedQuestionId,
		diarySearch,
		questionSearch,
		entryFromTimestamp,
		entryUntilTimestamp,
		selectedDiary,
		selectedEntry,
		selectedQuestion,
		diaryGroups,
		currentEntries,
		currentQuestions,
		currentAnswers,
		selectedDiaryShares,
		selectedDiaryStats,
		selectedQuestionVersionChain,
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
		refreshSelectedDiaryWorkspace,
		refreshSelectedEntryContext,
		setSelectedDiary,
		setSelectedEntry,
		setSelectedQuestion,
		startCreatingDiary,
		cancelDiaryCreation,
		prepareDiaryDuplicate,
		startCreatingEntry,
		startEditingEntry,
		cancelEntryEditing,
		startCreatingQuestion,
		cancelQuestionCreation,
		entryQuestionsForTimestamp,
		saveDiary,
		saveDiaryWithShares,
		saveEntry,
		saveEntryWithAnswers,
		saveQuestion,
		saveQuestionAndReloadVersions,
		saveAnswer,
		deleteAnswer,
		deleteSelectedDiary,
	}
})
