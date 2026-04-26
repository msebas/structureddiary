<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiPlus } from '@mdi/js'
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'

const route = useRoute()
const router = useRouter()
const store = useStructuredDiaryStore()

const diaryId = computed(() => toRouteNumber(route.params.diaryId))
const questionId = computed(() => toRouteNumber(route.params.questionId))

const diary = computed(() =>
	diaryId.value === null ? store.selectedDiary : store.diaries.find((item) => item.id === diaryId.value) ?? null)
const question = computed(() => {
	if (diaryId.value === null || questionId.value === null) {
		return null
	}

	return (store.questionsByDiary[diaryId.value] ?? []).find((item) => item.id === questionId.value)
		?? (store.questionVersionsById[questionId.value] ?? []).find((item) => item.id === questionId.value)
		?? null
})

function toRouteNumber(value: unknown): number | null {
	if (typeof value !== 'string' || value.trim() === '') {
		return null
	}

	const parsed = Number.parseInt(value, 10)
	return Number.isFinite(parsed) ? parsed : null
}

async function createQuestion(): Promise<void> {
	if (diaryId.value === null) {
		return
	}

	store.startCreatingQuestion()
	await router.push({ name: 'questionCreate', params: { diaryId: diaryId.value } })
}

async function editQuestion(): Promise<void> {
	if (diaryId.value === null || questionId.value === null) {
		return
	}

	store.cancelQuestionCreation()
	await router.push({ name: 'questionEdit', params: { diaryId: diaryId.value, questionId: questionId.value } })
}
</script>

<template>
	<header :class="$style.header">
		<div :class="$style.leading">
			<h1 :class="$style.title">
				{{ diary?.title ?? 'Structured Diary' }}
			</h1>
		</div>

		<div :class="$style.actions">
			<NcButton aria-label="Create new question" @click="createQuestion()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
			</NcButton>
			<NcButton
				v-if="question !== null"
				variant="secondary"
				@click="editQuestion()">
				Edit question
			</NcButton>
		</div>
	</header>
</template>

<style module>
.header {
	position: sticky;
	top: 0;
	z-index: 30;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 20px;
	padding: 18px 22px;
	background: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);
}

.leading {
	display: flex;
	align-items: center;
	gap: 14px;
	min-width: 0;
}

.title {
	margin: 0;
	font-size: clamp(1.2rem, 2vw, 1.8rem);
	line-height: 1.1;
	word-break: break-word;
}

.actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 10px;
}
</style>
