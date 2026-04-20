<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import type { Question } from '@/types/types'
import { formatDateTime } from '@/utils/format'

const props = defineProps<{
	questions: Question[]
	selectedQuestionId: number | null
	versionMap: Record<number, Question[]>
	expandedQuestionId: number | null
	search: string
}>()

const emit = defineEmits<{
	(event: 'update:search', value: string): void
	(event: 'create'): void
	(event: 'select', question: Question): void
	(event: 'toggleVersions', question: Question): void
}>()

function hasMultipleVersions(question: Question): boolean {
	const versions = props.versionMap[question.id] ?? []
	return versions.length > 1 || question.previous_version_id !== null || question.next_version_id !== null
}
</script>

<template>
	<aside :class="$style.panel">
		<div :class="$style.actions">
			<NcButton @click="emit('create')">
				New question
			</NcButton>
		</div>

		<NcTextField
			:model-value="props.search"
			type="search"
			label="Search questions"
			placeholder="Search questions"
			@update:model-value="emit('update:search', String($event))" />

		<div :class="$style.list">
			<div
				v-for="question in props.questions"
				:key="question.id"
				:class="$style.questionWrap">
				<div :class="[$style.item, question.id === props.selectedQuestionId && $style.itemActive]">
					<span>{{ question.label }}</span>
					<NcButton
						v-if="hasMultipleVersions(question)"
						variant="tertiary"
						size="small"
						@click.stop="emit('toggleVersions', question)">
						Versions
					</NcButton>
				</div>
				<NcButton
					variant="secondary"
					:class="$style.selectButton"
					@click="emit('select', question)">
					Open question
				</NcButton>
				<div
					v-if="props.expandedQuestionId === question.id && props.versionMap[question.id]?.length"
					:class="$style.versionList">
					<button
						v-for="version in props.versionMap[question.id]"
						:key="version.id"
						type="button"
						:class="$style.versionItem"
						@click="emit('select', version)">
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

.selectButton {
	justify-self: start;
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
