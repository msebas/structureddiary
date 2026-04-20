import { createRouter, createWebHistory } from 'vue-router'
import EntryDetailView from '@/views/EntryDetailView.vue'
import EntryEditView from '@/views/EntryEditView.vue'
import DiaryDetailView from '@/views/DiaryDetailView.vue'
import DiaryEditView from '@/views/DiaryEditView.vue'
import QuestionDetailView from '@/views/QuestionDetailView.vue'
import QuestionEditView from '@/views/QuestionEditView.vue'

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
		{ path: '/', redirect: { name: 'entriesIndex' } },
		{ path: '/entries/:diaryId(\\d+)', name: 'entriesIndex', component: EntryDetailView },
		{ path: '/entries/new', name: 'entryCreate', component: EntryEditView },
		{ path: '/entries/:diaryId(\\d+)/:entryId(\\d+)', name: 'entries', component: EntryDetailView , props: true},
		{ path: '/entries/:diaryId(\\d+)/:entryId(\\d+)/edit', name: 'entryEdit', component: EntryEditView, props: true },
		{ path: '/diaries', name: 'diaries', component: DiaryDetailView },
		{ path: '/diaries/new', name: 'diaryCreate', component: DiaryEditView },
		{ path: '/diaries/:diaryId(\\d+)/edit', name: 'diaryEdit', component: DiaryEditView, props: true},
		{ path: '/diaries/:diaryId(\\d+)/edit_share', name: 'diaryEditShare', component: DiaryEditView, props: true},
		{ path: '/questions/:diaryId(\\d+)', name: 'questionsIndex', component: QuestionDetailView },
		{ path: '/questions/:diaryId(\\d+)/new', name: 'questionCreate', component: QuestionEditView },
		{ path: '/questions/:diaryId(\\d+)/:questionId(\\d+)', name: 'questions', component: QuestionDetailView, props: true },
		{ path: '/questions/:diaryId(\\d+)/:questionId(\\d+)/edit', name: 'questionEdit', component: QuestionEditView, props: true },
	],
})
