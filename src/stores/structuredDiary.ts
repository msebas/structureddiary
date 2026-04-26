import {computed, ref} from 'vue'
import {defineStore} from 'pinia'
import {answerService, diaryService, entryService, questionService} from '@/services'
import {compareDiaryLabel} from '@/utils/diary'
import type {
    Answer,
    AnswerCreatePayload,
    AnswerUpdatePayload,
    Diary,
    DiaryCreatePayload, DiaryGroupSet,
    DiaryShare,
    DiaryStats,
    DiaryUpdatePayload,
    Entry,
    Question,
    QuestionCreatePayload,
    QuestionTypeDefinition,
    QuestionUpdatePayload,
} from '@/types/types'
import {getCurrentUser} from '@nextcloud/auth'
import {useRoute, useRouter} from "vue-router";
import {Permissions} from '@/types/types'

export interface DiaryShareInput {
    sharedWith: string
    permission: number
}

type DuplicatedQuestionPayload = Required<Omit<QuestionCreatePayload, 'diaryId'>>

export interface DiaryEditSubmitPayload {
    diaryId: number | null
    diary: DiaryUpdatePayload | DiaryCreatePayload
    shares?: DiaryShareInput[] | null
    questions?: DuplicatedQuestionPayload[] | null
}

export interface EntryEditSubmitPayload {
    entryId: number | null
    diaryId: number
    title: string | null
    timestamp: number
    answers: Answer[]
}

function cloneQuestionAsCreatePayload(question: Question): DuplicatedQuestionPayload {
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


function toRouteNumber(value: unknown): number | null {
    if (typeof value !== 'string' || value.trim() === '') {
        return null
    }

    const parsed = Number.parseInt(value, 10)
    return Number.isFinite(parsed) ? parsed : null
}


type DiaryError = {
    message: string
    type: 'error' | 'warning' | 'info' | 'success'
    timestamp: number
}

export const useStructuredDiaryStore = defineStore('structuredDiary', () => {
    const route = useRoute()
    const router = useRouter()

    const diaries = ref<Record<number, Diary>>({})
    const diaryShares = ref<Record<number, Record<string, DiaryShare>>>({}) // <diary.id, <user_id, DiaryShare>>
    const diaryStatsById = ref<Record<number, DiaryStats>>({})

    const entriesByDiary = ref<Record<number, Record<number, Entry>>>({})

    const questionById = ref<Record<number, Question>>({})
    const questionIdsByDiary = ref<Record<number, number[]>>({})
    // Reversed order (newest element is at index 0, oldest at index n-1)
    const questionVersionIdsByChainId = ref<Record<number, number[]>>({})
    const activeQuestionIdsByEntry = ref<Record<number, number[]>>({})

    const answersByEntryByQuestion = ref<Record<number, Record<number, Answer>>>({})
    const answerHistoryByEntryQuestion = ref<Record<number, Record<number, Answer[]>>>({})

    const questionTypes = ref<QuestionTypeDefinition[]>([])
    const loading = ref(0)
    const errors = ref<DiaryError[]>([])

    // reworked
    const selectedDiaryId = computed<number | null>({
        get: () => toRouteNumber(route.params.diaryId),
        set: async (diaryId: number | null) => {
            if (diaryId !== selectedDiaryId.value) {
                if (diaryId === null) {
                    await router.push({name: 'diaries'})
                    return
                }
                if (String(route.name).startsWith('entr')) {
                    await router.push({name: 'entries', params: {diaryId}})
                } else if (String(route.name).startsWith('quest')) {
                    await router.push({name: 'questions', params: {diaryId}})
                } else {
                    await router.push({name: 'diary', params: {diaryId}})
                }
            }
            await refreshSelectedDiaryWorkspace()
        }
    })
    const selectedEntryId = computed<number | null>({
        get: () => toRouteNumber(route.params.entryId),
        set: async (entryId: number | null) => {
            if (entryId === null) {
                const diaryId = selectedDiaryId.value
                if (diaryId != null) {
                    await router.push({name: 'entries', params: {diaryId}})
                }
                return
            }
            if (String(route.name).startsWith('entr')) {
                await router.push({name: 'entry', params: {diaryId: selectedDiaryId.value, entryId}})
                await loadEntry(entryId)
            }
        }
    })
    const selectedQuestionId = computed<number | null>({
        get: () => toRouteNumber(route.params.questionId),
        set: async (questionId: number | null) => {
            if (questionId === null) {
                const diaryId = selectedDiaryId.value
                if (diaryId != null) {
                    await router.push({name: 'questions', params: {diaryId}})
                }
                return
            }
            await router.push({name: 'question', params: {diaryId: selectedDiaryId.value, questionId}})
            await loadQuestion(questionId)
        }
    })
    const creatingDiary = computed(() => {
        return route.name === 'diaryCreate'
    })
    const creatingEntry = computed(() => {
        return route.name === 'entryCreate'
    })
    const creatingQuestion = computed(() => {
        return route.name === 'questionCreate'
    })


    const diarySearch = ref('')
    const questionSearch = ref('')
    const entryFromTimestamp = ref<number | null>(null)
    const entryUntilTimestamp = ref<number | null>(null)

    const selectedDiary = computed(() => selectedDiaryId.value === null ? null : diaries.value[selectedDiaryId.value] ?? null)
    const selectedDiaryShares = computed(() => selectedDiaryId.value === null ? [] : diaryShares.value[selectedDiaryId.value] ?? [])
    const selectedDiaryStats = computed(() => selectedDiaryId.value === null ? null : diaryStatsById.value[selectedDiaryId.value] ?? null)

    const selectedEntry = computed(() => {
        if (selectedDiaryId.value == null || selectedEntryId.value == null) return null;
        return (entriesByDiary.value[selectedDiaryId.value] ?? {})[selectedEntryId.value] ?? null
    })
    const currentEntries = computed(() => {
        if (selectedDiaryId.value == null || entriesByDiary.value[selectedDiaryId.value] == null) return []
        return Object.values(entriesByDiary.value[selectedDiaryId.value]).filter(
            (i: Entry) => i.timestamp >= (entryFromTimestamp.value ?? 0) &&
                i.timestamp <= (entryUntilTimestamp.value ?? Infinity)).sort(
            (a, b) => a.timestamp - b.timestamp)
    })
    const currentAnswers = computed<Record<number, Answer>>(() =>
        selectedEntryId.value === null ? {} : answersByEntryByQuestion.value[selectedEntryId.value] ?? {})

    const selectedQuestion = computed(() => {
        if (selectedDiaryId.value == null || selectedQuestionId.value === null) return null
        return questionById.value[selectedQuestionId.value]
    })
    const currentDiaryQuestions = computed(() => {
        if (selectedDiaryId.value == null) return []
        const questionIds = questionIdsByDiary.value[selectedDiaryId.value] ?? {}
        const question_list = Object.values(questionIds).map(i => questionById.value[i]).sort(
            (a, b) => a.diary_question_order - b.diary_question_order)

        if (questionSearch.value.trim() === '') return question_list
        const query = questionSearch.value.toLocaleLowerCase()
        return question_list.filter((question) =>
            question.label.toLocaleLowerCase().includes(query)
            || question.display_text.toLocaleLowerCase().includes(query))
    })
    const _currentEntryQuestions = computed(() => {
        if (selectedEntry.value == null) return []
        if (activeQuestionIdsByEntry.value[selectedEntry.value.id] != null)
            return activeQuestionIdsByEntry.value[selectedEntry.value.id].map(i => questionById.value[i])

        const current_date = selectedEntry.value.timestamp
        const base_array = currentDiaryQuestions.value
        return base_array.map(i => {
            if (i.created_at < current_date) return i
            if (questionVersionIdsByChainId.value[i.chain_id] == null) return null
            return questionVersionIdsByChainId.value[i.chain_id].map(i => questionById.value[i]).find(j => j.created_at <= current_date)
        })
    })
    const currentEntryQuestions = computed(() => _currentEntryQuestions.value.filter(i => i != null && i.active))

    const selectedQuestionVersionChain = computed(() =>
        (selectedQuestionId.value === null ? [] : questionVersionIdsByChainId.value[selectedQuestionId.value] ?? []).map(
            i => questionById.value[i]))

    const user_permissions = computed(() => {
        const currentUserId = getCurrentUser()?.uid ?? null
        const diary_id = selectedDiaryId.value
        if (diary_id == null || selectedDiary.value === null || currentUserId === null) {
            return 0
        }
        if (selectedDiary.value.is_owner) {
            return 0xffffffff
        }

        const share = (diaryShares.value[diary_id] ?? {})[currentUserId]
        return share?.permission ?? 0
    })

    const diaryGroups = computed(() => {
        const normalizedSearch = diarySearch.value.trim().toLocaleLowerCase()
        const user = getCurrentUser()?.uid ?? ""
        const visible = Object.values(diaries).filter((diary: Diary) => {
            if (normalizedSearch === '') return true
            return diary.title.toLocaleLowerCase().includes(normalizedSearch)
                || (diary.user_id != user && diary.user_id.toLocaleLowerCase().includes(normalizedSearch))
        }).sort(compareDiaryLabel)

        const groups: DiaryGroupSet = {
            owned: [],
            managed: [],
            writable: [],
            readable: [],
        }

        for (const diary of visible) {
            if (diary.is_owner) {
                groups.owned.push(diary)
                continue
            }
            const share = diaryShares.value[diary.id]?.[user]
            if (!share) continue

            if ((share.permission & Permissions.MANAGE) != 0) {
                groups.managed.push(diary)
            } else if ((diary.access_level & Permissions.WRITE) != 0) {
                groups.writable.push(diary)
            } else if ((diary.access_level & Permissions.READ) != 0) {
                groups.readable.push(diary)
            }
        }

        return groups
    })

    async function runTask<T>(task: () => Promise<T>): Promise<T> {
        loading.value += 1
        try {
            return await task()
        } catch (taskError) {
            errors.value.push({
                message: taskError instanceof Error ? taskError.message : 'Unknown error',
                type: 'error',
                timestamp: Date.now(),
            })
            throw taskError
        } finally {
            loading.value -= 1
        }
    }

    // Reworked functions
    async function loadDiaries(): Promise<void> {
        await runTask(async () => {
            diaries.value = Object.fromEntries((await diaryService.list()).map(i => [i.id, i]))
            const shares = await runTask(() => diaryService.shares())
            const res: Record<number, Record<string, DiaryShare>> = {}
            shares.forEach(i => {
                if (res[i.diary_id] === undefined) res[i.diary_id] = {}
                res[i.diary_id][i.shared_with] = i
            })
            Object.keys(res).forEach(id => res[Number(id)] = Object.freeze(res[Number(id)]))
            diaryShares.value = res
        })
    }

    async function loadDiary(id: number): Promise<void> {
        await runTask(async () => {
            const res = await diaryService.get(id)
            if (!res) return
            diaries.value[id] = await diaryService.get(id)
            diaryShares.value[id] = Object.freeze(Object.fromEntries((await runTask(() => diaryService.diary_shares(id))).map(
                i => [i.shared_with, i])))
        })
    }

    async function loadDiaryStats(id: number): Promise<void> {
        diaryStatsById.value[id] = await runTask(() => diaryService.stats(id))
    }

    async function loadEntries(diaryId: number, fromTimestamp?: number | null, untilTimestamp?: number | null): Promise<void> {
        const entries = await runTask(() => entryService.list(diaryId, fromTimestamp, untilTimestamp))
        entriesByDiary.value[diaryId] = Object.freeze({
            ...(entriesByDiary.value[diaryId] ?? {}), ...Object.fromEntries(entries.map(i => [i.id, i]))
        })
    }

    async function loadEntry(entryId: number): Promise<void> {
        const entry_promise = runTask(() => entryService.get(entryId))
        const answer_promise = runTask(() => answerService.list(entryId))

        const entry = await entry_promise
        const question_promise = runTask(() => questionService.listActive(entry.diary_id, entry.timestamp))

        if (entriesByDiary.value[entry.diary_id] == null) entriesByDiary.value[entry.diary_id] = {}
        entriesByDiary.value[entry.diary_id][entryId] = entry
        const questions = await question_promise
        activeQuestionIdsByEntry.value[entryId] = questions.map((question) => {
            questionById.value[question.id] = question
            return question.id
        })
        await answer_promise
    }

    async function loadAnswers(entryId: number): Promise<Answer[]> {
        const answer_promise = runTask(() => answerService.list(entryId))
        answersByEntryByQuestion.value[entryId] = Object.fromEntries((await answer_promise).map(i => [i.question_id, i]))
        return answer_promise
    }

    async function loadQuestions(diaryId: number): Promise<void> {
        const questions = await runTask(() => questionService.list(diaryId))
        questionIdsByDiary.value[diaryId] = questions.map((question) => {
            questionById.value[question.id] = question
            return question.id
        })
    }

    async function loadQuestion(questionId: number): Promise<void> {
        const question = await runTask(() => questionService.get(questionId))
        questionById.value[question.id] = question
    }

    async function loadQuestionVersions(questionId: number): Promise<void> {
        const questions = await runTask(() => questionService.versions(questionId))
        if (questions.length === 0) return
        questionVersionIdsByChainId.value[questions[0].chain_id] = questions.map((question) => {
            questionById.value[question.id] = question
            return question.id
        }).sort((a, b) => b - a) // sort by version number descending => oldest comes last
    }

    async function refreshSelectedDiaryWorkspace(): Promise<void> {
        if (selectedDiaryId.value === null) {
            return
        }

        await Promise.all([
            loadEntries(selectedDiaryId.value, entryFromTimestamp.value, entryUntilTimestamp.value),
            loadQuestions(selectedDiaryId.value),
            loadDiaryStats(selectedDiaryId.value).catch(() => undefined),
            loadDiary(selectedDiaryId.value).catch(() => undefined),
        ])
    }

    async function startCreatingDiary(): Promise<void> {
        await router.push({name: 'diaryCreate'})
    }

    async function editDiary(diaryId: number): Promise<void> {
        await router.push({name: 'diaryEdit', params: {diaryId}})
    }

    async function editDiaryShares(diaryId: number): Promise<void> {
        await router.push({name: 'diaryEditShare', params: {diaryId}})
    }

    async function cancelCreateDiary(): Promise<void> {
        await router.push({name: 'diaries'})
    }

    async function startCreatingEntry(diaryId: number | null): Promise<void> {
        if (diaryId === null && selectedDiaryId.value == null) return

        await router.push({name: 'entryCreate', params: {diaryId: diaryId ?? selectedDiaryId.value}})
    }

    async function startEditingEntry(entryId: number | null, diaryId: number | null): Promise<void> {
        if (entryId === null && selectedEntryId.value == null) return
        if (diaryId === null && selectedDiaryId.value == null) return

        await router.push({
            name: 'entryEdit',
            params: {diaryId: diaryId ?? selectedDiaryId.value, entryId: entryId ?? selectedEntryId.value}
        })
    }

    async function cancelEditingEntry(): Promise<void> {
        await router.push({name: 'entries', params: {diaryId: selectedDiaryId.value}})
    }

    async function startCreatingQuestion(entryId: number | null, diaryId: number | null): Promise<void> {
        if (entryId === null && selectedQuestionId.value == null) return
        if (diaryId === null && selectedDiaryId.value == null) return

        await router.push({name: 'questionCreate', params: {diaryId: diaryId ?? selectedDiaryId.value}})
    }

    async function startEditingQuestion(questionId: number | null, diaryId: number | null): Promise<void> {
        if (questionId === null && selectedQuestionId.value == null) return
        if (diaryId === null && selectedDiaryId.value == null) return

        await router.push({
            name: 'questionEdit',
            params: {diaryId: diaryId ?? selectedDiaryId.value, questionId: questionId ?? selectedQuestionId.value}
        })
    }

    async function cancelEditingQuestion(): Promise<void> {
        await router.push({name: 'questions', params: {diaryId: selectedDiaryId.value}})
    }


    async function ensureQuestionTypes(): Promise<void> {
        if (questionTypes.value.length > 0) {
            return
        }
        questionTypes.value = await runTask(() => questionService.types())
    }

    async function loadAnswerHistory(entryId: number, questionId: number): Promise<void> {
        if (answerHistoryByEntryQuestion.value[entryId] == null)
            answerHistoryByEntryQuestion.value[entryId] = {}
        answerHistoryByEntryQuestion.value[entryId][questionId] = await runTask(() =>
            answerService.history(entryId, questionId))
    }

    async function copyDiary(diaryId: number): Promise<DiaryEditSubmitPayload> {
        const diary = diaries.value[diaryId]
        if (!diary) {
            throw new Error(`Diary with ID ${diaryId} not found`)
        }
        const questionsToDuplicate = (questionIdsByDiary.value[diaryId] ?? [])
            .map((id) => questionById.value[id])
            .filter((question): question is Question => question !== undefined)
        await router.push({name: 'diaryCreate'})
        return {
            diaryId: null,
            diary: {
                title: diary.title,
                description: diary.description,
                ownerUserId: diary.user_id,
                reminderActive: diary.reminder_active,
                reminderTime: diary.reminder_time,
                reminderCount: diary.reminder_count,
                reminderDelay: diary.reminder_delay,
                reminderSignalFirst: diary.reminder_signal_first,
                reminderSignalRepeat: diary.reminder_signal_repeat,
                entrySchedule: diary.entry_schedule,
            },
            shares: [], // Shares shall not be copied
            questions: questionsToDuplicate.map(cloneQuestionAsCreatePayload)
        }
    }


    function initialize(): Promise<void> {
        const promises = [
            loadDiaries()
        ]
        if (questionTypes.value.length === 0)
            promises.push(ensureQuestionTypes())

        if (selectedDiaryId.value !== null) {
            promises.push(loadDiary(selectedDiaryId.value))
            promises.push(loadEntries(selectedDiaryId.value, entryFromTimestamp.value, entryUntilTimestamp.value))
            promises.push((loadQuestions(selectedDiaryId.value)))
            if (selectedEntryId.value !== null) {
                promises.push(loadEntry(selectedEntryId.value))
            }
            if (selectedQuestionId.value !== null) {
                promises.push(loadQuestion(selectedQuestionId.value))
            }
        }

        return Promise.all(promises).then()
    }

    async function saveQuestion(payload: QuestionCreatePayload | QuestionUpdatePayload): Promise<Question> {

        const saved = await runTask(async () => {
            if (payload.diaryId != null) {
                return questionService.create(payload.diaryId, payload as QuestionCreatePayload)
            }
            const update = payload as QuestionUpdatePayload
            return questionService.update(update.questionId, update)
        })

        if (selectedDiaryId.value !== null) {
            await loadQuestions(selectedDiaryId.value)
        }
        selectedQuestionId.value = saved.id
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
        answersByEntryByQuestion.value[saved.entry_id][saved.question_id] = saved
        return saved
    }

    async function deleteAnswer(answerId: number): Promise<void> {
        const res = await runTask(() => answerService.remove(answerId))
        if (!res) return
        if (answersByEntryByQuestion.value[res.entry_id]?.[res.question_id] != null)
            delete answersByEntryByQuestion.value[res.entry_id][res.question_id]

        answersByEntryByQuestion.value[res.entry_id] = await runTask(() => answerService.list(res.entry_id))
    }

    async function deleteDiary(diaryId: number | null = null): Promise<void> {
        if (diaryId === null) diaryId = selectedDiaryId.value
        if (selectedDiaryId.value === null) return

        const removedDiaryId = (await runTask(() => diaryService.remove(diaryId as number)))?.id
        cancelCreateDiary()
        if (removedDiaryId !== null) {
            delete diaries.value[removedDiaryId]
            delete diaryShares.value[removedDiaryId]
            delete diaryStatsById.value[removedDiaryId]
            delete entriesByDiary.value[removedDiaryId]
            delete questionIdsByDiary.value[removedDiaryId]
            delete diaryStatsById.value[removedDiaryId]

            if (diaryId == selectedDiaryId.value) {
                selectedDiaryId.value = null
                selectedEntryId.value = null
                selectedQuestionId.value = null
            }
            await initialize()
        }
    }

    async function saveEntry(payload: EntryEditSubmitPayload, setEntry: boolean = true): Promise<Entry> {
        if (!payload.diaryId) {
            const msg = 'No diary selected.'
            errors.value.push({message: msg, type: 'error', timestamp: Date.now()})
            throw new Error()
        }

        // TODO Do this only if there is any change to the entry...
        const saved_entry = await runTask(async () => {
            if (payload.entryId != null) {
                return entryService.update(payload.entryId, {title: payload.title, timestamp: payload.timestamp})
            }
            return entryService.create(payload.diaryId, {title: payload.title, timestamp: payload.timestamp})
        })

        if (entriesByDiary.value[saved_entry.diary_id] == null) {
            entriesByDiary.value[saved_entry.diary_id] = {}
        }

        entriesByDiary.value[saved_entry.diary_id][saved_entry.id] = saved_entry

        // This does not load answers in all cases.
        if (payload.entryId != null && answersByEntryByQuestion.value[payload.entryId] == null) {
            await loadAnswers(saved_entry.id)
        }

        const promises: Promise<any>[] = []
        const entryId = saved_entry.id || payload.entryId
        if (entryId == null) {
            throw new Error('Unable to determine saved entry id.')
        }
        // This assumes that answers for this entry are loaded now.
        for (const answer of payload.answers) {
            if (answer.text_content === null && answer.numeric_content === null) continue

            const existing = answersByEntryByQuestion.value[entryId]?.[answer.question_id]
            if (existing && existing.text_content === answer.text_content && existing.numeric_content === answer.numeric_content) {
                continue
            }
            promises.push(saveAnswer(entryId, {
                questionId: answer.question_id,
                textContent: answer.text_content,
                numericContent: answer.numeric_content
            }, existing?.id))
        }

        await Promise.all(promises)
        await loadAnswers(entryId)

        if (setEntry && saved_entry?.id && selectedEntryId.value !== saved_entry.id) {
            if (selectedDiaryId.value !== saved_entry.diary_id) {
                selectedDiaryId.value = saved_entry.diary_id
            }
            selectedEntryId.value = saved_entry.id
        }

        return saved_entry
    }

    async function saveDiary(payload: DiaryEditSubmitPayload, setDiary = true): Promise<Diary> {
        const savedDiary = await runTask(async () => {
            if (payload.diaryId == null) {
                return diaryService.create(payload.diary as DiaryCreatePayload)
            }
            return diaryService.update(payload.diaryId, payload.diary as DiaryUpdatePayload)
        })
        diaries.value[savedDiary.id] = savedDiary
        payload.diaryId = savedDiary.id

        if (payload.shares != null) {
            const ownerUserId = payload.diary?.ownerUserId?.trim() ?? ''

            const newShares: Record<string, DiaryShare> = {}
            const promises = []
            const currentByUser = diaryShares.value[payload.diaryId]
            for (const item of payload.shares) {
                const existing = currentByUser[item.sharedWith]
                if (item.sharedWith == ownerUserId) continue
                if (existing) {
                    promises.push(diaryService.updateShare(savedDiary.id, existing.id, item.permission))
                } else {
                    promises.push(diaryService.createShare(savedDiary.id, item.sharedWith, item.permission))
                }
            }
            for (const promise of promises) {
                const share = await promise
                newShares[share.shared_with] = share
            }
            diaryShares.value[payload.diaryId] = newShares
            const del_promises = []
            for (const orphan of Object.values(currentByUser)) {
                if (newShares[orphan.shared_with] == null) {
                    del_promises.push(diaryService.deleteShare(savedDiary.id, orphan.id))
                }
            }
            await Promise.all(del_promises)
        }

        if (payload.questions != null) {
            if (questionIdsByDiary.value[payload.diaryId] == null)
                await loadQuestions(payload.diaryId)

            const newQuestions: Record<number, Question> = {}
            let promises = []

            for (const item of payload.questions) {
                const question = item as QuestionUpdatePayload | QuestionCreatePayload
                const questionId = (item as QuestionUpdatePayload).questionId
                question.diaryId = payload.diaryId

                // TODO We should filter here if there was even any change to the question.
                //  If not we could just add a promise to the queue returning the current question.
                if (questionId) {
                    promises.push(questionService.update(questionId, question as QuestionUpdatePayload))
                } else {
                    promises.push(questionService.create(questionId, question as QuestionCreatePayload))
                }
            }
            while (promises.length > 0) {
                const question = await promises.pop()
                if (question == null) {
                    continue
                }
                questionById.value[question.id] = question
                newQuestions[question.chain_id] = question
            }

            // We do not delete removed questions, because this would dropt the corresponding answers. We only disable them.
            for (const questionId of questionIdsByDiary.value[payload.diaryId]) {
                if (newQuestions[questionId] == null) {
                    questionById.value[questionId].active = false
                    promises.push(questionService.update(questionId, {active: false} as QuestionUpdatePayload))
                }
            }

            await Promise.all(promises)
        }


        if (setDiary && selectedDiaryId.value !== savedDiary.id)
            await router.push({name: 'diary', params: {diaryId: savedDiary.id}})

        await loadDiary(savedDiary.id).catch(() => undefined)
        await loadQuestions(savedDiary.id).catch(() => undefined)
        await loadDiaryStats(savedDiary.id).catch(() => undefined)

        return savedDiary
    }

    return {
        diaries,
        diaryShares,
        entriesByDiary,
        answerHistoryByEntryQuestion,
        user_permissions,
        diaryStatsById,
        questionTypes,
        loading,
        errors,
        creatingDiary,
        creatingEntry,
        creatingQuestion,
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
        currentAnswers,
        selectedDiaryShares,
        selectedDiaryStats,
        selectedQuestionVersionChain,
        currentEntryQuestions,
        initialize,
        loadDiaries,
        loadDiary,
        loadDiaryStats,
        loadEntries,
        loadQuestions,
        loadQuestionVersions,
        loadAnswerHistory,
        refreshSelectedDiaryWorkspace,
        startCreatingDiary,
        editDiary,
        editDiaryShares,
        copyDiary,
        startCreatingEntry,
        startEditingEntry,
        startCreatingQuestion,
        saveDiary,
        saveEntry,
        saveQuestion,
        saveQuestionAndReloadVersions,
        saveAnswer,
        deleteAnswer,
        deleteDiary,
        startEditingQuestion,
        cancelEditingQuestion,
        cancelEditingEntry,
    }
})
