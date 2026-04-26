export type WorkspaceRouteName =
	| 'entriesIndex'
	| 'entries'
	| 'entryCreate'
	| 'entryEdit'
	| 'diaries'
	| 'diaryCreate'
	| 'diaryEdit'
	| 'diaryEditShare'
	| 'questionsIndex'
	| 'questions'
	| 'questionCreate'
	| 'questionEdit'

export function isManagementRoute(routeName: string | null | undefined): boolean {
	return routeName === 'diaries'
		|| routeName === 'diaryCreate'
		|| routeName === 'diaryEdit'
		|| routeName === 'diaryEditShare'
		|| routeName === 'questionsIndex'
		|| routeName === 'questions'
		|| routeName === 'questionCreate'
		|| routeName === 'questionEdit'
}

export function mobileOverlayTitleForRoute(routeName: WorkspaceRouteName): string {
	switch (routeName) {
		case 'entriesIndex':
		case 'entries':
			return 'Entry'
		case 'entryCreate':
		case 'entryEdit':
			return 'Edit entry'
		case 'diaries':
		case 'diaryCreate':
			return 'Diary'
		case 'diaryEdit':
		case 'diaryEditShare':
			return 'Edit diary'
		case 'questionsIndex':
		case 'questions':
			return 'Question'
		case 'questionCreate':
		case 'questionEdit':
			return 'Edit question'
	}
}
