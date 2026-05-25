<script setup lang="ts">
import {computed} from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import FloatLabel from 'primevue/floatlabel'
import Rating from 'primevue/rating'
import Select from 'primevue/select'
import {VueEasyMDE} from 'vue3-easymde'
import type { Answer, Question } from '@/types/types'
import {ensureQuestionEditorToolbarStyles, questionEditorToolbar} from '@/components/questions/easymdeToolbar'
import { t } from '@nextcloud/l10n'

const props = defineProps<{
	question: Question
	modelValue: Answer | undefined
	highlightEmpty: boolean
}>()

const emit = defineEmits<{
	(event: 'update:modelValue', value: Answer): void
}>()

ensureQuestionEditorToolbarStyles()

const FLOATING_LABEL_MAX_LENGTH = 42

const markdownEditorOptions = {
	autofocus: false,
	autoDownloadFontAwesome: false,
	forceSync: true,
	spellChecker: false,
	status: false,
	minHeight: '120px',
	toolbar: questionEditorToolbar,
}

const textValue = computed({
	get: () => props.modelValue?.text_content ?? props.question.template_text ?? '',
	set: (value: string) => nextValue({text_content: value}),
})

const selectValue = computed({
	get: () => props.modelValue?.text_content ?? (props.question.type === 'editable_select' ? props.question.template_text : ''),
	set: (value: string | null) => nextValue({text_content: value}),
})

const selectOptions = computed(() => props.question.choices ?? [])
const selectInputId = computed(() => `answer-select-${props.question.id}`)
const useOutsideLabel = computed(() => props.question.display_text.length > FLOATING_LABEL_MAX_LENGTH)
const showsInlineTemplate = computed(() => props.question.template_text.trim() !== '' && (props.question.type === 'integer' || props.question.type === 'number'))

const timeValue = computed(() => parseTimeValue(props.modelValue?.text_content ?? ''))

const numericValue = computed({
	get: () => props.modelValue?.numeric_content ?? '',
	set: (value: string | number) => {
		nextValue({numeric_content: value === '' ? null : Number(value)})
	},
})

const ratingValue = computed({
	get: () => props.modelValue?.numeric_content ?? 0,
	set: (value: number) => nextValue({numeric_content: value}),
})

function nextValue(patch: Partial<Answer>): void {
	emit('update:modelValue', {
		id: props.modelValue?.id ?? 0,
		diary_id: props.modelValue?.diary_id ?? props.question.diary_id,
		entry_id: props.modelValue?.entry_id ?? 0,
		question_id: props.question.id,
		created_at: props.modelValue?.created_at ?? 0,
		text_content: props.modelValue?.text_content ?? null,
		numeric_content: props.modelValue?.numeric_content ?? null,
		previous_version_id: props.modelValue?.previous_version_id ?? null,
		next_version_id: props.modelValue?.next_version_id ?? null,
		...patch,
	})
}

function parseTimeValue(value: string): Date | null {
	const match = /^(\d{1,2}):(\d{2})(?::(\d{2}))?$/.exec(value.trim())
	if (match === null) {
		return null
	}

	const hours = Number(match[1])
	const minutes = Number(match[2])
	const seconds = Number(match[3] ?? 0)
	if (hours > 23 || minutes > 59 || seconds > 59) {
		return null
	}

	return new Date(1970, 0, 1, hours, minutes, seconds)
}

function formatTimeValue(value: Date): string {
	const hours = value.getHours().toString().padStart(2, '0')
	const minutes = value.getMinutes().toString().padStart(2, '0')
	return `${hours}:${minutes}`
}

function updateTimeValue(value: Date | [Date, Date] | null): void {
	if (value instanceof Date && Number.isFinite(value.getTime())) {
		nextValue({text_content: formatTimeValue(value)})
	}
}

</script>

<template>
	<div :class="[$style.field, props.highlightEmpty && $style.fieldEmpty]">
		<label v-if="props.question.type === 'text' || props.question.type === 'rating' || props.question.type === 'boolean'" :class="$style.label">
			{{ props.question.display_text }}
		</label>

		<VueEasyMDE
			v-if="props.question.type === 'text'"
			v-model="textValue"
			:options="markdownEditorOptions"
			:class="$style.markdownEditor" />

		<div
			v-else-if="props.question.type === 'select' || props.question.type === 'editable_select'"
			:class="$style.selectField">
			<label v-if="useOutsideLabel" :for="selectInputId" :class="$style.label">
				{{ props.question.display_text }}
			</label>
			<FloatLabel v-if="!useOutsideLabel" variant="on" :class="$style.selectLabel">
				<Select
					v-model="selectValue"
					:input-id="selectInputId"
					:options="selectOptions"
					:editable="props.question.type === 'editable_select'"
					:class="['structured-diary-select', $style.selectInput]" />
				<label :for="selectInputId">{{ props.question.display_text }}</label>
			</FloatLabel>
			<Select
				v-else
				v-model="selectValue"
				:input-id="selectInputId"
				:options="selectOptions"
				:editable="props.question.type === 'editable_select'"
				:class="['structured-diary-select', $style.selectInput]" />
		</div>

		<div v-else-if="props.question.type === 'time'" :class="$style.dateTimeField">
			<label v-if="useOutsideLabel" :class="$style.label">
				{{ props.question.display_text }}
			</label>
			<NcDateTimePicker
				:model-value="timeValue"
				format="HH:mm"
				:placeholder="useOutsideLabel ? undefined : props.question.display_text"
				type="time"
				:minute-step="1"
				@update:model-value="updateTimeValue" />
		</div>

		<div v-else-if="props.question.type === 'integer' || props.question.type === 'number'" :class="$style.numericRow">
			<NcTextField
				v-model="numericValue"
				:class="$style.numericField"
				:input-class="$style.numericInput"
				:label="props.question.display_text"
				:label-outside="useOutsideLabel"
				type="number" />
			<span v-if="showsInlineTemplate" :class="$style.inlineTemplate">
				{{ props.question.template_text }}
			</span>
		</div>

		<Rating
			v-else-if="props.question.type === 'rating'"
			v-model="ratingValue"
			:stars="10"
			:cancel="false" />

		<NcCheckboxRadioSwitch
			v-else-if="props.question.type === 'boolean'"
			:model-value="props.modelValue?.numeric_content === 1"
			type="switch"
			@update:model-value="nextValue({numeric_content: $event ? 1 : 0})">
			{{ t('structureddiary', 'Yes / No') }}
		</NcCheckboxRadioSwitch>

	</div>
</template>

<style module>
.field {
	display: grid;
	gap: 10px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-container);
	background: var(--color-main-background);
}

.fieldEmpty {
	border-color: var(--color-warning);
	box-shadow: inset 0 0 0 1px var(--color-warning-hover);
}

.label {
	font-weight: 700;
	color: var(--color-main-text);
}

.markdownEditor {
	min-width: 0;
}

.markdownEditor :global(.CodeMirror textarea) {
	width: 1px !important;
	min-width: 1px !important;
	max-width: 1px !important;
	height: 1em !important;
	min-height: 1em !important;
	padding: 0 !important;
	border: 0 !important;
	border-radius: 0 !important;
	background: transparent !important;
	box-shadow: none !important;
	color: transparent !important;
	resize: none !important;
	appearance: none !important;
}

.markdownEditor :global(.CodeMirror textarea:focus) {
	outline: 0 !important;
	box-shadow: none !important;
}

.selectField,
.dateTimeField {
	display: grid;
	gap: 6px;
	min-width: 25%;
	max-width: 100%;
}

.selectInput {
	width: min(100%, 28rem);
	min-width: 25%;
	max-width: 100%;
	border-color: var(--color-border);
	background: var(--color-main-background);
	box-shadow: none;
}

:global(.structured-diary-select.p-select),
:global(.structured-diary-select.p-focus),
:global(.structured-diary-select.p-inputwrapper-focus),
:global(.structured-diary-select.p-select-open),
:global(.structured-diary-select.p-select:not(.p-disabled):hover) {
	border-color: var(--color-border);
	box-shadow: none;
	outline: none;
}

:global(.structured-diary-select input),
:global(.structured-diary-select .p-select-label) {
	border: 0;
	background: transparent;
	outline: none;
	box-shadow: none;
}

.selectLabel {
	width: min(100%, 28rem);
	min-width: 25%;
	max-width: 100%;
}

.selectLabel label {
	max-width: calc(100% - 1rem);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	background: var(--color-main-background);
	color: var(--color-text-maxcontrast);
}

.numericRow {
	display: inline-flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}

.numericField {
	max-width: 12rem;
}

.numericInput {
	max-width: 9ch;
}

.inlineTemplate {
	color: var(--color-text-maxcontrast);
	white-space: pre-wrap;
}

</style>
