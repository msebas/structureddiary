import type { Diary, DiaryGroupSet } from '@/types/types'

function compareDiaryLabel(a: Diary, b: Diary): number {
	const left = `${a.title}\u0000${a.is_owner ? '' : a.user_id}`.toLocaleLowerCase()
	const right = `${b.title}\u0000${b.is_owner ? '' : b.user_id}`.toLocaleLowerCase()
	return left.localeCompare(right)
}

export function classifyDiaries(diaries: Diary[], search = ''): DiaryGroupSet {
	const normalizedSearch = search.trim().toLocaleLowerCase()
	const visible = diaries.filter((diary) => {
		if (normalizedSearch === '') {
			return true
		}

		return diary.title.toLocaleLowerCase().includes(normalizedSearch)
			|| diary.user_id.toLocaleLowerCase().includes(normalizedSearch)
	})

	const groups: DiaryGroupSet = {
		owned: [],
		managed: [],
		writable: [],
		readable: [],
	}

	for (const diary of visible) {
		if (diary.is_owner) {
			groups.owned.push(diary)
		} else if ((diary.access_level & 8) === 8) {
			groups.managed.push(diary)
		} else if ((diary.access_level & 2) === 2) {
			groups.writable.push(diary)
		} else {
			groups.readable.push(diary)
		}
	}

	for (const key of Object.keys(groups) as Array<keyof DiaryGroupSet>) {
		groups[key] = [...groups[key]].sort(compareDiaryLabel)
	}

	return groups
}

export function secondsToDayTime(seconds: number): string {
	const hours = Math.floor(seconds / 3600).toString().padStart(2, '0')
	const minutes = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0')
	return `${hours}:${minutes}`
}

export function dayTimeToSeconds(value: string): number {
	const [hours = '0', minutes = '0'] = value.split(':')
	return (Number(hours) * 3600) + (Number(minutes) * 60)
}

export function scheduleSecondsToDays(schedule: number): number {
	return schedule / 86400
}

export function daysToScheduleSeconds(days: number): number {
	return Math.round(days * 86400)
}

