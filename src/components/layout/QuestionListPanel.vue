<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { ref } from 'vue'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { Question } from '@/types/types'
import { formatDateTime } from '@/utils/format'
import { t } from '@nextcloud/l10n'

const store = useStructuredDiaryStore()
const emit = defineEmits<{
	(event: 'open-center'): void
}>()
const expandedQuestionId = ref<number | null>(null)

function hasMultipleVersions(question: Question): boolean {
	const versions = store.questionVersionMap[question.id] ?? []
	return versions.length > 1 || question.previous_version_id !== null || question.next_version_id !== null
}

async function toggleVersions(question: Question): Promise<void> {
	expandedQuestionId.value = expandedQuestionId.value === question.id ? null : question.id
	await store.loadQuestionVersions(question.id)
}

async function createQuestion(): Promise<void> {
	await store.startCreatingQuestion(null, store.selectedDiaryId)
	emit('open-center')
}

function selectQuestion(questionId: number): void {
	store.selectedQuestionId = questionId
	emit('open-center')
}
</script>

<template>
	<aside :class="$style.panel">
		<div :class="$style.actions">
			<NcButton @click="createQuestion()"
                :disabled="store.selectedDiaryId === null">
				{{ t('structureddiary', 'New question') }}
			</NcButton>
		</div>

		<NcTextField
			:model-value="store.questionSearch"
			type="search"
			:label="t('structureddiary', 'Search questions')"
			:placeholder="t('structureddiary', 'Search questions')"
			@update:model-value="store.questionSearch = String($event)" />

		<div :class="$style.list">
			<div
				v-for="question in store.currentDiaryQuestions"
				:key="question.id"
				:class="$style.questionWrap">
				<div :class="[$style.item, question.id === store.selectedQuestionId && $style.itemActive]"
             @click="selectQuestion(question.id)">
					<span >{{ question.label }}</span>
					<NcButton
						v-if="hasMultipleVersions(question)"
						variant="tertiary"
						size="small"
						@click.stop="toggleVersions(question)">
						{{ t('structureddiary', 'Versions') }}
					</NcButton>
				</div>
				<div
					v-if="expandedQuestionId === question.id && store.questionVersionMap[question.id]?.length"
					:class="$style.versionList">
					<button
						v-for="version in store.questionVersionMap[question.id]"
						:key="version.id"
						type="button"
						:class="$style.versionItem"
						@click="selectQuestion(version.id)">
						<div>{{ formatDateTime(version.created_at) }}</div>
						<div v-if="version.label !== question.label" :class="$style.versionLabel">
							{{ version.label }}
						</div>
					</button>
				</div>
			</div>
		</div>
	</aside>
</template>

<style module>
.panel {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-height: 0;
	padding: 18px;
	background: var(--color-main-background);
}

.actions {
	display: flex;
	justify-content: flex-end;
}

.list {
	display: grid;
	gap: 10px;
	overflow: auto;
}

.questionWrap {
	display: grid;
	gap: 6px;
}

.item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 12px 14px;
	background: var(--color-main-background);
	text-align: left;
}

.itemActive {
	border-color: var(--color-primary-element);
	background: var(--color-background-hover);
}

.versionList {
	display: grid;
	gap: 4px;
	padding-left: 10px;
}

.versionItem {
	border: 0;
	border-radius: 12px;
	padding: 9px 12px;
	background: var(--color-background-hover);
	text-align: left;
	cursor: pointer;
}

.versionLabel {
	font-size: 0.8rem;
	color: var(--color-text-maxcontrast);
}
</style>
