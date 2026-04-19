<script setup lang="ts">
import type { Answer, Question } from '@/types/types'
import { supportsNumericInput } from '@/utils/format'

const props = defineProps<{
	question: Question
	modelValue: Answer | undefined
	highlightEmpty: boolean
}>()

const emit = defineEmits<{
	(event: 'update:modelValue', value: Answer): void
}>()

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
</script>

<template>
	<div :class="[$style.field, props.highlightEmpty && $style.fieldEmpty]">
		<label :class="$style.label">{{ props.question.display_text }}</label>

		<textarea
			v-if="['text', 'select', 'editable_select', 'time'].includes(props.question.type)"
			:value="props.modelValue?.text_content ?? props.question.template_text"
			:class="$style.textarea"
			rows="4"
			@input="nextValue({ text_content: ($event.target as HTMLTextAreaElement).value })" />

		<input
			v-else-if="supportsNumericInput(props.question.type)"
			:value="props.modelValue?.numeric_content ?? ''"
			:type="props.question.type === 'integer' ? 'number' : 'number'"
			:min="props.question.minimum ?? undefined"
			:max="props.question.maximum ?? undefined"
			:step="props.question.type === 'integer' ? 1 : props.question.type === 'rating' ? 0.1 : 0.01"
			:class="$style.input"
			@input="nextValue({ numeric_content: ($event.target as HTMLInputElement).value === '' ? null : Number(($event.target as HTMLInputElement).value) })">

		<label v-else-if="props.question.type === 'boolean'" :class="$style.booleanRow">
			<input
				type="checkbox"
				:checked="props.modelValue?.numeric_content === 1"
				@change="nextValue({ numeric_content: ($event.target as HTMLInputElement).checked ? 1 : 0 })">
			<span>Yes / No</span>
		</label>

		<p v-if="props.question.template_text" :class="$style.template">
			{{ props.question.template_text }}
		</p>
	</div>
</template>

<style module>
.field {
	display: grid;
	gap: 10px;
	padding: 16px;
	border: 1px solid rgba(16, 37, 66, 0.08);
	border-radius: 18px;
	background: rgba(255, 255, 255, 0.8);
}

.fieldEmpty {
	border-color: rgba(217, 105, 65, 0.5);
	box-shadow: inset 0 0 0 1px rgba(217, 105, 65, 0.18);
}

.label {
	font-weight: 700;
	color: #13253d;
}

.textarea,
.input {
	width: 100%;
	border: 1px solid rgba(16, 37, 66, 0.15);
	border-radius: 14px;
	padding: 12px 14px;
	background: rgba(255, 255, 255, 0.96);
}

.booleanRow {
	display: inline-flex;
	align-items: center;
	gap: 10px;
}

.template {
	margin: 0;
	font-size: 0.88rem;
	color: #5f6e82;
	white-space: pre-wrap;
}
</style>

