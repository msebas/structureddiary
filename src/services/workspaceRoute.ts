import { t } from '@nextcloud/l10n'

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
			return t('structureddiary', 'Entry')
		case 'entryCreate':
		case 'entryEdit':
			return t('structureddiary', 'Edit entry')
		case 'diaries':
		case 'diary':
		case 'diaryCreate':
			return t('structureddiary', 'Diary')
		case 'diaryEdit':
		case 'diaryEditShare':
			return t('structureddiary', 'Edit diary')
		case 'questions':
		case 'question':
			return t('structureddiary', 'Question')
		case 'questionCreate':
		case 'questionEdit':
			return t('structureddiary', 'Edit question')
	}
}
