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
		{ path: '/entries', name: 'entriesIndex', component: EntryDetailView },
		{ path: '/entries/new', name: 'entryCreate', component: EntryEditView },
		{ path: '/entries/:entryId(\\d+)', name: 'entries', component: EntryDetailView },
		{ path: '/entries/:entryId(\\d+)/edit', name: 'entryEdit', component: EntryEditView },
		{ path: '/diaries', name: 'diaries', component: DiaryDetailView },
		{ path: '/diaries/edit', name: 'diaryEdit', component: DiaryEditView },
		{ path: '/questions', name: 'questionsIndex', component: QuestionDetailView },
		{ path: '/questions/new', name: 'questionCreate', component: QuestionEditView },
		{ path: '/questions/:questionId(\\d+)', name: 'questions', component: QuestionDetailView },
		{ path: '/questions/:questionId(\\d+)/edit', name: 'questionEdit', component: QuestionEditView },
	],
})
