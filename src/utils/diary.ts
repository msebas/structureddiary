import type { Diary, DiaryGroupSet } from '@/types/types'

export function compareDiaryLabel(a: Diary, b: Diary): number {
	const left = `${a.title}\u0000${a.is_owner ? '' : a.user_id}`.toLocaleLowerCase()
	const right = `${b.title}\u0000${b.is_owner ? '' : b.user_id}`.toLocaleLowerCase()
	return left.localeCompare(right)
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

