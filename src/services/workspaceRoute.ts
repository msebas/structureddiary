export type WorkspaceRouteName =
	| 'entries'
	| 'entry'
	| 'entryCreate'
	| 'entryEdit'
	| 'diaries'
	| 'diary'
	| 'diaryCreate'
	| 'diaryEdit'
	| 'diaryEditShare'
	| 'questions'
	| 'question'
	| 'questionCreate'
	| 'questionEdit'

export function isManagementRoute(routeName: string | null | undefined): boolean {
	return routeName === 'diaries'
		|| routeName === 'diary'
		|| routeName === 'diaryCreate'
		|| routeName === 'diaryEdit'
		|| routeName === 'diaryEditShare'
		|| routeName === 'questions'
		|| routeName === 'question'
		|| routeName === 'questionCreate'
		|| routeName === 'questionEdit'
}

export function mobileOverlayTitleForRoute(routeName: WorkspaceRouteName): string {
	switch (routeName) {
		case 'entries':
		case 'entry':
			return 'Entry'
		case 'entryCreate':
		case 'entryEdit':
			return 'Edit entry'
		case 'diaries':
		case 'diary':
		case 'diaryCreate':
			return 'Diary'
		case 'diaryEdit':
		case 'diaryEditShare':
			return 'Edit diary'
		case 'questions':
		case 'question':
			return 'Question'
		case 'questionCreate':
		case 'questionEdit':
			return 'Edit question'
	}
}
