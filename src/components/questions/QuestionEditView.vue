<script setup lang="ts">
import {useRoute} from 'vue-router'
import {useStructuredDiaryStore} from '@/stores/structuredDiary'
import type {
  QuestionCreatePayload,
  QuestionUpdatePayload,
  QuestionType,
} from '@/types/types'
import {computed, reactive, watch} from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import {VueEasyMDE} from 'vue3-easymde'
import {ensureQuestionEditorToolbarStyles, questionEditorToolbar} from '@/components/questions/easymdeToolbar'

const store = useStructuredDiaryStore()
const route = useRoute()

ensureQuestionEditorToolbarStyles()


async function cancelQuestionEdit(): Promise<void> {
  if (store.selectedDiaryId === null) {
    return
  }

  if (route.name === 'questionEdit' && store.selectedQuestion !== null) {
    await store.pushWorkspaceRoute({
      name: 'question',
      params: {diaryId: store.selectedDiaryId, questionId: store.selectedQuestion.id},
    })
    return
  }

  await store.pushWorkspaceRoute({
    name: 'questions',
    params: {diaryId: store.selectedDiaryId},
  })
}

const typeOptions = computed(() => store.questionTypes.map((definition) => ({
  id: definition.id,
  value: definition.value,
})))

const displayTextEditorOptions = {
  autofocus: false,
  autoDownloadFontAwesome: false,
  forceSync: true,
  spellChecker: false,
  status: false,
  minHeight: '180px',
  toolbar: questionEditorToolbar,
}

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


watch(
    () => [store.creatingQuestion, store.selectedQuestion] as const,
    ([creatingQuestion, question]) => {
      if (!creatingQuestion && question === null) return
      form.label = question?.label ?? ''
      form.displayText = question?.display_text ?? ''
      form.type = question?.type ?? 'text'
      form.minimum = String(question?.minimum ?? '')
      form.maximum = String(question?.maximum ?? '')
      form.choices = question?.choices?.join(', ') ?? ''
      form.active = question?.active ?? true
      form.templateText = question?.template_text ?? ''

    },
    {immediate: true},
)

const rangeStep = computed<'1' | '0.01' | null>(() => {
  switch (form.type) {
    case 'text':
    case 'integer':
    case 'editable_select':
      return '1'
    case 'time':
    case 'number':
    case 'rating':
      return '0.01'
    default:
      return null
  }
})

const showsRangeFields = computed(() => rangeStep.value !== null)
const rangeHelperText = computed(() =>
    rangeStep.value === '1'
        ? 'Whole numbers only.'
        : 'Use numbers in 0.01 increments.',
)

function isValidRangeValue(value: string): boolean {
  if (value.trim() === '' || rangeStep.value === null) {
    return true
  }

  const parsed = Number(value)
  if (!Number.isFinite(parsed)) {
    return false
  }

  if (rangeStep.value === '1') {
    return Number.isInteger(parsed)
  }

  return Math.abs(parsed * 100 - Math.round(parsed * 100)) < 1e-9
}

const minimumHasError = computed(() => showsRangeFields.value && !isValidRangeValue(form.minimum))
const maximumHasError = computed(() => showsRangeFields.value && !isValidRangeValue(form.maximum))

function parsedRangeValue(value: string): number | null {
  if (value.trim() === '') {
    return null
  }

  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

const rangeOrderError = computed(() => {
  if (minimumHasError.value || maximumHasError.value) {
    return false
  }

  const minimum = parsedRangeValue(form.minimum)
  const maximum = parsedRangeValue(form.maximum)
  if (minimum === null || maximum === null) {
    return false
  }

  return minimum > maximum
})

const hasRangeError = computed(() => minimumHasError.value || maximumHasError.value || rangeOrderError.value)


async function saveQuestion(): Promise<void> {
  if (hasRangeError.value) {
    return
  }

  const payload = {
    label: form.label ?? null,
    displayText: form.displayText || form.label,
    type: form.type ?? 'text',
    minimum: form.minimum === '' ? null : Number(form.minimum),
    maximum: form.maximum === '' ? null : Number(form.maximum),
    choices: form.choices.trim() === '' ? null : form.choices.split(',').map((value) => value.trim()).filter(Boolean),
    active: form.active ?? true,
    templateText: form.templateText,
  }

  if (store.creatingQuestion) {
    if (store.selectedDiaryId === null) return;
    try {
      await store.saveQuestionAndReloadVersions({...payload, diaryId: store.selectedDiaryId,} as QuestionCreatePayload)
    } catch (error) {
      console.error('Failed to save question:', error)
    }
  } else {
    if (store.selectedQuestion === null) return;

    await store.saveQuestionAndReloadVersions({
      ...payload,
      questionId: store.selectedQuestion.id,
      chainId: store.selectedQuestion.chain_id,
    } as QuestionUpdatePayload)
  }
}

const minimumHelperText = computed<string | undefined>(() => {
  if (form.minimum === '') return undefined
  if (minimumHasError.value) return `Invalid value. ${rangeHelperText.value}`
  if (rangeOrderError.value) return 'Minimum must be smaller than or equal to maximum.'
  return rangeHelperText.value
})
const maximumHelperText = computed<string | undefined>(() => {
  if (form.maximum === '') return undefined
  if (maximumHasError.value) return `Invalid value. ${rangeHelperText.value}`
  if (rangeOrderError.value) return 'Maximum must be greater than or equal to minimum.'
  return rangeHelperText.value
})

</script>

<template>
  <section :class="$style.form">
    <h2 :class="$style.heading">{{ store.selectedQuestionId != null ? 'Edit question' : 'Create question' }}</h2>
    <NcTextField
        v-model="form.label"
        label="Label"
        type="text"/>
    <div :class="$style.field">
      <label :class="$style.editorLabel" for="question-display-text">Display text</label>
      <div :class="$style.editorWrap">
        <VueEasyMDE
            id="question-display-text"
            v-model="form.displayText"
            :options="displayTextEditorOptions"
            :class="$style.editor"/>
      </div>
    </div>
    <div :class="$style.grid">
      <NcSelect
          v-model="form.type"
          input-label="Type"
          label="id"
          :clearable="false"
          :options="typeOptions"
          :reduce="(option: { id: string, value: QuestionType }) => option.value"/>
      <NcCheckboxRadioSwitch
          v-model="form.active"
          type="switch">
        Active
      </NcCheckboxRadioSwitch>
    </div>
    <div v-if="showsRangeFields" :class="$style.grid">
      <NcTextField
          v-model="form.minimum"
          :error="minimumHasError || rangeOrderError"
          :helper-text="minimumHelperText"
          label="Minimum"
          inputmode="decimal"
          type="number"/>
      <NcTextField
          v-model="form.maximum"
          :error="maximumHasError || rangeOrderError"
          :helper-text="maximumHelperText"
          label="Maximum"
          inputmode="decimal"
          type="number"/>
    </div>
    <NcTextArea
        v-model="form.choices"
        helper-text="Comma-separated values, for example: a, b, c"
        label="Choices"
        resize="vertical"/>
    <NcTextArea
        v-model="form.templateText"
        label="Template text"
        resize="vertical"/>
    <div :class="$style.actions">
      <NcButton variant="secondary" @click="cancelQuestionEdit">
        Cancel
      </NcButton>
      <NcButton variant="primary" @click="saveQuestion">
        Save question
      </NcButton>
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

.heading {
  margin: 0;
}

.field {
  display: grid;
  gap: 8px;
}

.editorLabel {
  font-weight: 600;
}

.editorWrap :global(.EasyMDEContainer) {
  border-radius: 14px;
}

.editorWrap :global(.EasyMDEContainer .CodeMirror) {
  border: 1px solid var(--color-border-maxcontrast);
  border-radius: 0 0 14px 14px;
  min-height: 180px;
}

.editorWrap :global(.EasyMDEContainer .editor-toolbar) {
  border: 1px solid var(--color-border-maxcontrast);
  border-bottom: 0;
  border-radius: 14px 14px 0 0;
}

.editorWrap :global(.EasyMDEContainer .CodeMirror-sided) {
  border-inline-start: 1px solid var(--color-border-maxcontrast);
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

@media (max-width: 720px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>
