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
		case 'entryEdit':
			return t('structureddiary', 'Edit entry')
		case 'entryCreate':
			return t('structureddiary', 'Create entry')
		case 'diaries':
		case 'diary':
			return t('structureddiary', 'Diary')
		case 'diaryCreate':
			return t('structureddiary', 'Create diary')
		case 'diaryEdit':
		case 'diaryEditShare':
			return t('structureddiary', 'Edit diary')
		case 'questions':
		case 'question':
			return t('structureddiary', 'Question')
		case 'questionEdit':
			return t('structureddiary', 'Edit question')
		case 'questionCreate':
			return t('structureddiary', 'Create question')
	}
}
