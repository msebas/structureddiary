import {createRouter, createWebHistory} from 'vue-router'
import EntryDetailView from '@/views/EntryDetailView.vue'
import EntryEditView from '@/views/EntryEditView.vue'
import DiaryDetailView from '@/views/DiaryDetailView.vue'
import DiaryEditView from '@/views/DiaryEditView.vue'
import QuestionDetailView from '@/components/questions/QuestionDetailView.vue'
import QuestionEditView from '@/components/questions/QuestionEditView.vue'
import HeaderEntries from "@/components/layout/HeaderEntries.vue";
import HeaderDiaries from "@/components/layout/HeaderDiaries.vue";
import HeaderQuestions from "@/components/layout/HeaderQuestions.vue";
import {generateUrl} from "@nextcloud/router";
import EntryListPanel from "@/components/layout/EntryListPanel.vue";
import QuestionListPanel from "@/components/layout/QuestionListPanel.vue";


export const router = createRouter({
    history: createWebHistory(generateUrl("/apps/structureddiary/")),
    routes: [
        {path: '/', redirect: (to) => ({name: 'entriesAllDiaries', query: to.query})},
        {
            path: '/entries', name: 'entriesAllDiaries', components: {
                default: EntryDetailView,
                nav: HeaderEntries,
                sidebar: EntryListPanel,
            }
        },
        {
            path: '/entries/:diaryId(\\d+)', name: 'entries', components: {
                default: EntryDetailView,
                nav: HeaderEntries,
                sidebar: EntryListPanel,
            }
        },
        {
            path: '/entries/:diaryId(\\d+)/new', name: 'entryCreate', components: {
                default: EntryEditView,
                nav: HeaderEntries,
                sidebar: EntryListPanel,
            }
        },
        {
            path: '/entries/:diaryId(\\d+)/:entryId(\\d+)', name: 'entry', components: {
                default: EntryDetailView,
                nav: HeaderEntries,
                sidebar: EntryListPanel,
            },
        },
        {
            path: '/entries/:diaryId(\\d+)/:entryId(\\d+)/edit', name: 'entryEdit', components: {
                default: EntryEditView,
                nav: HeaderEntries,
                sidebar: EntryListPanel,
            },
        },
        {
            path: '/diaries', name: 'diaries', components: {
                default: DiaryDetailView,
                nav: HeaderDiaries,
                sidebar: QuestionListPanel,
            }
        },
        {
            path: '/diaries/:diaryId(\\d+)', name: 'diary', components: {
                default: DiaryDetailView,
                nav: HeaderDiaries,
                sidebar: QuestionListPanel,
            },
        },
        {
            path: '/diaries/new', name: 'diaryCreate', components: {
                default: DiaryEditView,
                nav: HeaderDiaries,
                sidebar: QuestionListPanel,
            }
        },
        {
            path: '/diaries/:diaryId(\\d+)/edit', name: 'diaryEdit', components: {
                default: DiaryEditView,
                nav: HeaderDiaries,
                sidebar: QuestionListPanel,
            },
        },
        {
            path: '/diaries/:diaryId(\\d+)/edit_share', name: 'diaryEditShare', components: {
                default: DiaryEditView,
                nav: HeaderDiaries,
                sidebar: QuestionListPanel,
            },
        },
        {
            path: '/questions/:diaryId(\\d+)', name: 'questions', components: {
                default: QuestionDetailView,
                nav: HeaderQuestions,
                sidebar: QuestionListPanel,
            }
        },
        {
            path: '/questions/:diaryId(\\d+)/new', name: 'questionCreate', components: {
                default: QuestionEditView,
                nav: HeaderQuestions,
                sidebar: QuestionListPanel,
            }
        },
        {
            path: '/questions/:diaryId(\\d+)/:questionId(\\d+)', name: 'question', components: {
                default: QuestionDetailView,
                nav: HeaderQuestions,
                sidebar: QuestionListPanel,
            },
        },
        {
            path: '/questions/:diaryId(\\d+)/:questionId(\\d+)/edit', name: 'questionEdit', components: {
                default: QuestionEditView,
                nav: HeaderQuestions,
                sidebar: QuestionListPanel,
            },
        },
    ],
})

if (import.meta.env.DEV) {
    let stateBackup: History['state'] | null = null
    router.beforeEach(() => {
        if (!history.state && stateBackup) {
            history.replaceState(stateBackup, '', location.href)
        }
    })

    router.afterEach(() => {
        if (history.state)
            stateBackup = history.state
    })
}
