<script setup lang="ts">
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
		<button type="button" :class="$style.createButton" @click="emit('create')">
			New question
		</button>

		<input
			:value="props.search"
			type="search"
			placeholder="Search questions"
			:class="$style.search"
			@input="emit('update:search', ($event.target as HTMLInputElement).value)">

		<div :class="$style.list">
			<div
				v-for="question in props.questions"
				:key="question.id"
				:class="$style.questionWrap">
				<div :class="[$style.item, question.id === props.selectedQuestionId && $style.itemActive]">
					<span>{{ question.label }}</span>
					<button
						v-if="hasMultipleVersions(question)"
						type="button"
						:class="$style.versionsButton"
						@click.stop="emit('toggleVersions', question)">
						Versions
					</button>
				</div>
				<button
					type="button"
					:class="$style.selectButton"
					@click="emit('select', question)">
					Open question
				</button>
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
	background:
		radial-gradient(circle at top right, rgba(246, 216, 155, 0.22), transparent 40%),
		linear-gradient(180deg, #fbfbfc, #eef1f6);
	border-left: 1px solid rgba(27, 41, 58, 0.12);
}

.search {
	width: 100%;
	border: 1px solid rgba(16, 37, 66, 0.15);
	border-radius: 14px;
	padding: 12px 14px;
	background: rgba(255, 255, 255, 0.92);
}

.createButton {
	border: 0;
	border-radius: 16px;
	padding: 12px 14px;
	background: #102542;
	color: white;
	font-weight: 700;
	cursor: pointer;
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
	border: 1px solid transparent;
	border-radius: 16px;
	padding: 12px 14px;
	background: rgba(255, 255, 255, 0.75);
	text-align: left;
}

.itemActive {
	border-color: rgba(16, 37, 66, 0.18);
	background: white;
}

.selectButton {
	border: 0;
	border-radius: 12px;
	padding: 9px 12px;
	background: rgba(217, 105, 65, 0.12);
	color: #8d3c20;
	text-align: left;
	font-weight: 700;
	cursor: pointer;
}

.versionsButton {
	border: 0;
	border-radius: 999px;
	padding: 6px 10px;
	background: rgba(16, 37, 66, 0.08);
	font-size: 0.78rem;
	font-weight: 700;
	cursor: pointer;
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
	background: rgba(16, 37, 66, 0.05);
	text-align: left;
	cursor: pointer;
}

.versionLabel {
	font-size: 0.8rem;
	color: #5d6d81;
}
</style>
