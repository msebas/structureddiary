<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import {mdiArrowDown, mdiArrowUp, mdiDragVariant, mdiHistory} from '@mdi/js'
import {computed, ref, watch} from 'vue'
import {useRoute} from 'vue-router'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import type { Question } from '@/types/types'
import { formatDateTime } from '@/utils/format'
import { t } from '@nextcloud/l10n'

const store = useStructuredDiaryStore()
const route = useRoute()
const emit = defineEmits<{
	(event: 'open-center'): void
}>()
const expandedQuestionId = ref<number | null>(null)
const draftQuestionIds = ref<number[]>([])
const draggedQuestionId = ref<number | null>(null)
const dragOverQuestionId = ref<number | null>(null)
const reorderMode = ref(false)

const isDiaryMode = computed(() => route.name?.toString().startsWith('diar') === true)
const currentQuestionIds = computed(() => store.currentDiaryQuestions.map((question) => question.id))
const questionOrderDirty = computed(() =>
	draftQuestionIds.value.length === currentQuestionIds.value.length
	&& draftQuestionIds.value.some((questionId, index) => questionId !== currentQuestionIds.value[index]))
const changedQuestionIds = computed(() => new Set(
	draftQuestionIds.value.filter((questionId, index) => questionId !== currentQuestionIds.value[index]),
))
const reorderAvailable = computed(() => store.questionSearch.trim() === '')
const canDraftReorder = computed(() => reorderMode.value && reorderAvailable.value)
const displayedQuestions = computed(() => {
	if (!reorderAvailable.value) {
		return store.currentDiaryQuestions
	}

	const questionById = new Map(store.currentDiaryQuestions.map((question) => [question.id, question]))
	return draftQuestionIds.value
		.map((questionId) => questionById.get(questionId))
		.filter((question): question is Question => question !== undefined)
})

watch(() => store.selectedDiaryId, () => resetDraftOrder())
watch(isDiaryMode, (nextIsDiaryMode) => {
	if (!nextIsDiaryMode) {
		reorderMode.value = false
		resetDraftOrder()
	}
})
watch(() => store.questionSearch, (search) => {
	if (search.trim() !== '') {
		reorderMode.value = false
		resetDraftOrder()
	}
})
watch(currentQuestionIds, () => {
	if (!reorderMode.value || !questionOrderDirty.value) {
		resetDraftOrder()
	}
}, {immediate: true})

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
	if (reorderMode.value) {
		return
	}

	store.selectedQuestionId = questionId
	emit('open-center')
}

function resetDraftOrder(): void {
	draftQuestionIds.value = [...currentQuestionIds.value]
	draggedQuestionId.value = null
	dragOverQuestionId.value = null
}

function startReorderMode(): void {
	if (!guardCanReorder()) {
		return
	}

	reorderMode.value = true
}

function stopReorderMode(): void {
	if (questionOrderDirty.value) {
		resetDraftOrder()
	}
	reorderMode.value = false
}

function guardCanReorder(event?: Event): boolean {
	if (!isDiaryMode.value) {
		event?.preventDefault()
		store.warnQuestionReorderRequiresDiaryMode()
		return false
	}

	if (!reorderAvailable.value) {
		event?.preventDefault()
		return false
	}

	return true
}

function startDraggingQuestion(event: DragEvent, questionId: number): void {
	if (!guardCanReorder(event)) {
		return
	}

	draggedQuestionId.value = questionId
	event.dataTransfer?.setData('text/plain', String(questionId))
	if (event.dataTransfer) {
		event.dataTransfer.effectAllowed = 'move'
	}
}

function moveDraggedQuestion(event: DragEvent, targetQuestionId: number): void {
	if (draggedQuestionId.value === null || draggedQuestionId.value === targetQuestionId) {
		return
	}

	const nextOrder = [...draftQuestionIds.value]
	const sourceIndex = nextOrder.indexOf(draggedQuestionId.value)
	const targetIndex = nextOrder.indexOf(targetQuestionId)
	if (sourceIndex === -1 || targetIndex === -1) {
		return
	}

	const [questionId] = nextOrder.splice(sourceIndex, 1)
	const targetElement = event.currentTarget instanceof HTMLElement ? event.currentTarget : null
	const insertAfter = targetElement !== null
		&& event.clientY > targetElement.getBoundingClientRect().top + targetElement.getBoundingClientRect().height / 2
	const adjustedTargetIndex = nextOrder.indexOf(targetQuestionId)
	nextOrder.splice(adjustedTargetIndex + (insertAfter ? 1 : 0), 0, questionId)
	draftQuestionIds.value = nextOrder
	dragOverQuestionId.value = null
}

function moveQuestionByOffset(questionId: number, offset: -1 | 1): void {
	if (!guardCanReorder()) {
		return
	}

	const nextOrder = [...draftQuestionIds.value]
	const sourceIndex = nextOrder.indexOf(questionId)
	const targetIndex = sourceIndex + offset
	if (sourceIndex === -1 || targetIndex < 0 || targetIndex >= nextOrder.length) {
		return
	}

	const [movedQuestionId] = nextOrder.splice(sourceIndex, 1)
	nextOrder.splice(targetIndex, 0, movedQuestionId)
	draftQuestionIds.value = nextOrder
}

async function saveQuestionOrder(): Promise<void> {
	if (!guardCanReorder()) {
		return
	}

	await store.reorderQuestions(draftQuestionIds.value)
	resetDraftOrder()
	reorderMode.value = false
}
</script>

<template>
	<aside :class="$style.panel">
		<div :class="$style.actions">
			<div v-if="reorderMode || questionOrderDirty" :class="$style.reorderNotice">
				<span v-if="questionOrderDirty">
					{{ t('structureddiary', 'Question order changed.') }}
				</span>
				<span v-else>
					{{ t('structureddiary', 'Question reorder mode is active.') }}
				</span>
			</div>
			<NcButton v-if="!reorderMode" @click="startReorderMode()">
				{{ t('structureddiary', 'Reorder questions') }}
			</NcButton>
			<NcButton v-if="reorderMode" @click="stopReorderMode()">
				{{ t('structureddiary', 'Discard question order') }}
			</NcButton>
			<NcButton v-if="questionOrderDirty" variant="primary" @click="saveQuestionOrder()">
				{{ t('structureddiary', 'Save question order') }}
			</NcButton>
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
				v-for="question in displayedQuestions"
				:key="question.id"
				:class="$style.questionWrap"
				@dragover.prevent="dragOverQuestionId = question.id"
				@dragleave="dragOverQuestionId = null"
				@drop.prevent="moveDraggedQuestion($event, question.id)">
				<div :class="[
					$style.item,
					!reorderMode && question.id === store.selectedQuestionId && $style.itemActive,
					reorderMode && $style.itemReorderMode,
					changedQuestionIds.has(question.id) && $style.itemMoved,
					dragOverQuestionId === question.id && $style.itemDragOver,
				]"
             @click="selectQuestion(question.id)">
					<button
						type="button"
							v-if="reorderMode"
						:class="[$style.dragHandle, reorderMode && $style.dragHandleActive]"
						:draggable="canDraftReorder"
						:aria-label="t('structureddiary', 'Reorder question')"
						@pointerdown.stop="reorderMode ? guardCanReorder($event) : startReorderMode()"
						@dragstart.stop="startDraggingQuestion($event, question.id)"
						@dragend="draggedQuestionId = null; dragOverQuestionId = null">
						<NcIconSvgWrapper :path="mdiDragVariant" />
					</button>
					<span :class="$style.questionLabel">{{ question.label }}</span>
					<span v-if="changedQuestionIds.has(question.id)" :class="$style.movedBadge">
						{{ t('structureddiary', 'Moved') }}
					</span>
					<div :class="$style.itemActions">
						<NcButton
							v-if="reorderMode"
							variant="tertiary"
							size="small"
							:aria-label="t('structureddiary', 'Move question up')"
							:disabled="draftQuestionIds.indexOf(question.id) === 0"
							@click.stop="moveQuestionByOffset(question.id, -1)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiArrowUp" />
							</template>
						</NcButton>
						<NcButton
							v-if="reorderMode"
							variant="tertiary"
							size="small"
							:aria-label="t('structureddiary', 'Move question down')"
							:disabled="draftQuestionIds.indexOf(question.id) === draftQuestionIds.length - 1"
							@click.stop="moveQuestionByOffset(question.id, 1)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiArrowDown" />
							</template>
						</NcButton>
						<NcButton
							v-if="!reorderMode && hasMultipleVersions(question)"
							class="sd-mobile-icon-button"
							variant="secondary"
							size="small"
							:aria-label="t('structureddiary', 'Versions')"
							@click.stop="toggleVersions(question)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiHistory" />
							</template>
							<span class="sd-mobile-icon-button-label">{{ t('structureddiary', 'Versions') }}</span>
						</NcButton>
					</div>
				</div>
				<div
					v-if="!reorderMode && expandedQuestionId === question.id && store.questionVersionMap[question.id]?.length"
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
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.reorderNotice {
	margin-inline-end: auto;
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
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

.itemReorderMode {
	cursor: default;
	user-select: none;
	touch-action: none;
}

.itemMoved {
	border-color: var(--color-warning);
	background:
		linear-gradient(90deg, color-mix(in srgb, var(--color-warning) 18%, transparent), transparent 65%),
		var(--color-main-background);
	box-shadow: inset 3px 0 0 var(--color-warning);
}

.itemDragOver {
	outline: 2px dashed var(--color-primary-element);
	outline-offset: 3px;
}

.dragHandle {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 32px;
	border: 0;
	border-radius: var(--border-radius-pill);
	background: transparent;
	color: var(--color-text-maxcontrast);
	cursor: grab;
	transition: background var(--animation-quick), color var(--animation-quick), transform var(--animation-quick);
}

.dragHandle:active {
	cursor: grabbing;
}

.dragHandle.dragHandle > :global(span[class^="icon-"]),
.dragHandle.dragHandle > :global(span[class*=" icon-"]),
.dragHandle.dragHandle > :global(.material-design-icon),
.dragHandle.dragHandle > :global(.icon-vue) {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 20px;
	height: 20px;
	line-height: 0;
	vertical-align: middle;
	opacity: 1;
}

.dragHandle :global(svg),
.dragHandle :global(.material-design-icon__svg) {
	display: block;
}

.dragHandleActive {
	width: 44px;
	height: 44px;
	background: var(--color-background-hover);
	color: var(--color-primary-element);
	transform: scale(1.05);
}

.questionLabel {
	min-width: 0;
	flex: 1;
}

.movedBadge {
	border-radius: var(--border-radius-pill);
	padding: 2px 8px;
	background: color-mix(in srgb, var(--color-warning) 22%, transparent);
	color: var(--color-main-text);
	font-size: 0.78rem;
	font-weight: 700;
}

.itemActions {
	display: flex;
	align-items: center;
	gap: 6px;
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
