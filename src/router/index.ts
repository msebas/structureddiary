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

function routerBase(): string {
    if (typeof window === 'undefined') {
        return '/index.php/apps/structureddiary/'
    }

    if (window.location.pathname.startsWith('/index.php/apps/structureddiary')) {
        return '/index.php/apps/structureddiary/'
    }

    return '/apps/structureddiary/'
}

export const router = createRouter({
    history: createWebHistory(routerBase()),
    routes: [
        {path: '/', redirect: {name: 'diaries'}},
        {
            path: '/entries/:diaryId(\\d+)', name: 'entries', components: {
                default: EntryDetailView,
                nav: HeaderEntries
            }
        },
        {
            path: '/entries/:diaryId(\\d+)/new', name: 'entryCreate', components: {
                default: EntryEditView,
                nav: HeaderEntries
            }
        },
        {
            path: '/entries/:diaryId(\\d+)/:entryId(\\d+)', name: 'entry', components: {
                default: EntryDetailView,
                nav: HeaderEntries
            }, props: true
        },
        {
            path: '/entries/:diaryId(\\d+)/:entryId(\\d+)/edit', name: 'entryEdit', components: {
                default: EntryEditView,
                nav: HeaderEntries
            }, props: true
        },
        {
            path: '/diaries', name: 'diaries', components: {
                default: DiaryDetailView,
                nav: HeaderDiaries
            }
        },
        {
            path: '/diaries/:diaryId(\\d+)', name: 'diary', components: {
                default: DiaryDetailView,
                nav: HeaderDiaries
            }, props: true
        },
        {
            path: '/diaries/new', name: 'diaryCreate', components: {
                default: DiaryEditView,
                nav: HeaderDiaries
            }
        },
        {
            path: '/diaries/:diaryId(\\d+)/edit', name: 'diaryEdit', components: {
                default: DiaryEditView,
                nav: HeaderDiaries
            }, props: true
        },
        {
            path: '/diaries/:diaryId(\\d+)/edit_share', name: 'diaryEditShare', components: {
                default: DiaryEditView,
                nav: HeaderDiaries
            }, props: true
        },
        {
            path: '/questions/:diaryId(\\d+)', name: 'questions', components: {
                default: QuestionDetailView,
                nav: HeaderQuestions
            }
        },
        {
            path: '/questions/:diaryId(\\d+)/new', name: 'questionCreate', components: {
                default: QuestionEditView,
                nav: HeaderQuestions
            }
        },
        {
            path: '/questions/:diaryId(\\d+)/:questionId(\\d+)', name: 'question', components: {
                default: QuestionDetailView,
                nav: HeaderQuestions
            }, props: true
        },
        {
            path: '/questions/:diaryId(\\d+)/:questionId(\\d+)/edit', name: 'questionEdit', components: {
                default: QuestionEditView,
                nav: HeaderQuestions
            }, props: true
        },
    ],
})
