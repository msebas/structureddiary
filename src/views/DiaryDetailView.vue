<script setup lang="ts">
import type { Diary, DiaryShare, DiaryStats } from '@/types/types'
import DiaryDisplayCard from '@/components/diaries/DiaryDisplayCard.vue'

defineProps<{
	diary: Diary | null
	shares: DiaryShare[]
	stats: DiaryStats | null
	hideStats?: boolean
}>()
</script>

<template>
	<DiaryDisplayCard
		:diary="diary"
		:shares="shares"
		:stats="stats"
		:hide-stats="hideStats" />
</template>
