<script setup lang="ts">
import { reactive, watch } from 'vue'
import type { Question, QuestionType, QuestionTypeDefinition, QuestionUpdatePayload } from '@/types/types'

const props = defineProps<{
	question: Question | null
	types: QuestionTypeDefinition[]
}>()

const emit = defineEmits<{
	(event: 'save', payload: QuestionUpdatePayload): void
	(event: 'cancel'): void
}>()

const form = reactive({
	label: '',
	displayText: '',
	type: 'text' as QuestionType,
	minimum: '' as string,
	maximum: '' as string,
	choices: '' as string,
	active: true,
	templateText: '',
})

watch(() => props.question, (question) => {
	form.label = question?.label ?? ''
	form.displayText = question?.display_text ?? ''
	form.type = question?.type ?? 'text'
	form.minimum = question?.minimum?.toString() ?? ''
	form.maximum = question?.maximum?.toString() ?? ''
	form.choices = question?.choices?.join(', ') ?? ''
	form.active = question?.active ?? true
	form.templateText = question?.template_text ?? ''
}, { immediate: true })

function submit(): void {
	emit('save', {
		label: form.label,
		displayText: form.displayText,
		type: form.type,
		minimum: form.minimum === '' ? null : Number(form.minimum),
		maximum: form.maximum === '' ? null : Number(form.maximum),
		choices: form.choices.trim() === '' ? null : form.choices.split(',').map((value) => value.trim()).filter(Boolean),
		active: form.active,
		templateText: form.templateText,
	})
}
</script>

<template>
	<section :class="$style.form">
		<h2>{{ props.question ? 'Edit question' : 'Create question' }}</h2>
		<label :class="$style.field">
			<span>Label</span>
			<input v-model="form.label" type="text">
		</label>
		<label :class="$style.field">
			<span>Display text</span>
			<textarea v-model="form.displayText" rows="3" />
		</label>
		<div :class="$style.grid">
			<label :class="$style.field">
				<span>Type</span>
				<select v-model="form.type">
					<option v-for="definition in props.types" :key="definition.value" :value="definition.value">
						{{ definition.id }}
					</option>
				</select>
			</label>
			<label :class="$style.field">
				<span>Active</span>
				<input v-model="form.active" type="checkbox">
			</label>
		</div>
		<div :class="$style.grid">
			<label :class="$style.field">
				<span>Minimum</span>
				<input v-model="form.minimum" type="number" step="0.1">
			</label>
			<label :class="$style.field">
				<span>Maximum</span>
				<input v-model="form.maximum" type="number" step="0.1">
			</label>
		</div>
		<label :class="$style.field">
			<span>Choices</span>
			<input v-model="form.choices" type="text" placeholder="a, b, c">
		</label>
		<label :class="$style.field">
			<span>Template text</span>
			<textarea v-model="form.templateText" rows="4" />
		</label>
		<div :class="$style.actions">
			<button type="button" :class="$style.secondaryButton" @click="emit('cancel')">
				Cancel
			</button>
			<button type="button" :class="$style.primaryButton" @click="submit()">
				Save question
			</button>
		</div>
	</section>
</template>

<style module>
.form {
	display: grid;
	gap: 16px;
	padding: 22px;
	border-radius: 24px;
	background: rgba(255, 255, 255, 0.98);
	box-shadow: 0 20px 48px rgba(12, 25, 46, 0.09);
}

.field {
	display: grid;
	gap: 8px;
}

.field input,
.field textarea,
.field select {
	border: 1px solid rgba(16, 37, 66, 0.15);
	border-radius: 14px;
	padding: 12px 14px;
}

.grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 14px;
}

.actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
}

.primaryButton,
.secondaryButton {
	border: 0;
	border-radius: 999px;
	padding: 10px 14px;
	font-weight: 700;
	cursor: pointer;
}

.primaryButton {
	background: #d96941;
	color: white;
}

.secondaryButton {
	background: rgba(16, 37, 66, 0.08);
}

@media (max-width: 720px) {
	.grid {
		grid-template-columns: 1fr;
	}
}
</style>

