<script setup lang="ts">
import { computed } from 'vue'
import DiaryDisplayCard from '@/components/diaries/DiaryDisplayCard.vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import { Permissions, type Diary, type DiaryShare, type DiaryStats } from '@/types/types'

const props = defineProps<{
	diary?: Diary | null
	shares?: DiaryShare[]
	stats?: DiaryStats | null
	hideStats?: boolean
}>()

const store = useStructuredDiaryStore()

const diary = computed(() => props.diary ?? store.selectedDiary)
const shares = computed(() => props.shares ?? Object.values(store.selectedDiaryShares))
const stats = computed(() => props.stats ?? store.selectedDiaryStats)
const diaryCanAnalyze = computed(() => props.diary != null
	? props.diary.is_owner || (props.diary.access_level & Permissions.ANALYZE) !== 0
	: store.selectedDiaryCanAnalyze)
const hideStats = computed(() => props.hideStats === true || !diaryCanAnalyze.value)
</script>

<template>
	<DiaryDisplayCard
		:diary="diary"
		:shares="shares"
		:stats="stats"
		:hide-stats="hideStats" />
</template>
