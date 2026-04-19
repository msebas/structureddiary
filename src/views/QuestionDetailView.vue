<script setup lang="ts">
import type { Question } from '@/types/types'
import QuestionDisplayCard from '@/components/questions/QuestionDisplayCard.vue'

defineProps<{
	question: Question | null
	versionChain: Question[]
}>()
</script>

<template>
	<QuestionDisplayCard :question="question" :version-chain="versionChain" />
</template>
