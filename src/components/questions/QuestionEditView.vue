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
import NcTextField from '@nextcloud/vue/components/NcTextField'
import {VueEasyMDE} from 'vue3-easymde'
import {ensureQuestionEditorToolbarStyles, questionEditorToolbar} from '@/components/questions/easymdeToolbar'
import { t } from '@nextcloud/l10n'

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
    name: 'diary',
    params: {diaryId: store.selectedDiaryId},
  })
}

const typeOptions = computed(() => store.questionTypes.map((definition) => ({
  id: definition.id,
  value: definition.value,
})))

const baseDisplayTextEditorOptions = {
  autofocus: false,
  autoDownloadFontAwesome: false,
  forceSync: true,
  spellChecker: false,
  status: false,
  minHeight: '180px',
  toolbar: questionEditorToolbar,
}

const displayTextEditorOptions = computed(() => ({
  ...baseDisplayTextEditorOptions,
  readOnly: form.displayTextSynced ? 'nocursor' : false,
}))

const form = reactive({
  label: '',
  displayText: '',
  displayTextSynced: true,
  type: 'text' as QuestionType,
  minimum: '' as string,
  maximum: '' as string,
  choices: [] as string[],
  choiceDraft: '',
  active: true,
  templateText: '',
})


watch(
    () => [store.creatingQuestion, store.selectedQuestion?.id ?? null] as const,
    ([creatingQuestion]) => {
      const question = store.selectedQuestion
      if (!creatingQuestion && question === null) return
      form.label = question?.label ?? ''
      form.displayTextSynced = question?.display_text == null || question.display_text === '' || question.display_text === (question?.label ?? '')
      form.displayText = form.displayTextSynced ? form.label : question?.display_text ?? ''
      form.type = question?.type ?? 'text'
      form.minimum = String(question?.minimum ?? '')
      form.maximum = String(question?.maximum ?? '')
      form.choices = question?.choices ?? []
      form.choiceDraft = ''
      form.active = question?.active ?? true
      form.templateText = question?.template_text ?? ''

    },
    {immediate: true},
)

watch(
    () => form.label,
    (label) => {
      if (form.displayTextSynced) {
        form.displayText = label
      }
    },
)

watch(
    () => form.displayTextSynced,
    (synced) => {
      if (synced) {
        form.displayText = form.label
      }
    },
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
const showsChoiceFields = computed(() => form.type === 'select' || form.type === 'editable_select')
const showsTemplateTextField = computed(() => ['text', 'editable_select', 'number', 'integer'].includes(form.type))
const showsTemplateMarkdownEditor = computed(() => form.type === 'text')
const showsTemplateSingleLineInput = computed(() => ['editable_select', 'number', 'integer'].includes(form.type))
const rangeHelperText = computed(() =>
    rangeStep.value === '1'
        ? t('structureddiary', 'Whole numbers only.')
        : t('structureddiary', 'Use numbers in 0.01 increments.'),
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

function normalizedChoice(value: string): string {
  return value.trim()
}

function addChoice(): void {
  const choice = normalizedChoice(form.choiceDraft)
  if (choice === '') {
    return
  }

  if (!form.choices.includes(choice)) {
    form.choices.push(choice)
  }
  form.choiceDraft = ''
}

function removeChoice(choice: string): void {
  form.choices = form.choices.filter((currentChoice) => currentChoice !== choice)
}

function currentChoicesPayload(): string[] | null {
  if (!showsChoiceFields.value) {
    return null
  }

  addChoice()
  return form.choices.length === 0 ? null : [...form.choices]
}


async function saveQuestion(): Promise<void> {
  if (hasRangeError.value) {
    return
  }

  const payload = {
    label: form.label ?? null,
    displayText: form.displayTextSynced ? form.label : form.displayText || form.label,
    type: form.type ?? 'text',
    minimum: form.minimum === '' ? null : Number(form.minimum),
    maximum: form.maximum === '' ? null : Number(form.maximum),
    choices: currentChoicesPayload(),
    active: form.active ?? true,
    templateText: showsTemplateTextField.value ? form.templateText : '',
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
  if (minimumHasError.value) return t('structureddiary', 'Invalid value. {helperText}', {helperText: rangeHelperText.value})
  if (rangeOrderError.value) return t('structureddiary', 'Minimum must be smaller than or equal to maximum.')
  return rangeHelperText.value
})
const maximumHelperText = computed<string | undefined>(() => {
  if (form.maximum === '') return undefined
  if (maximumHasError.value) return t('structureddiary', 'Invalid value. {helperText}', {helperText: rangeHelperText.value})
  if (rangeOrderError.value) return t('structureddiary', 'Maximum must be greater than or equal to minimum.')
  return rangeHelperText.value
})

</script>

<template>
  <form id="structured-diary-question-edit-form" :class="$style.form" @submit.prevent="saveQuestion">
    <h2 :class="$style.heading">{{ store.creatingQuestion ? t('structureddiary', 'Create question') : t('structureddiary', 'Edit question') }}</h2>
    <NcTextField
        v-model="form.label"
        :label="t('structureddiary', 'Label')"
        type="text"/>
    <div :class="$style.field">
      <div :class="$style.displayTextHeader">
        <label :class="$style.editorLabel" for="question-display-text">{{ t('structureddiary', 'Display text') }}</label>
        <NcCheckboxRadioSwitch
            v-model="form.displayTextSynced"
            type="switch">
          {{ t('structureddiary', 'Sync with label') }}
        </NcCheckboxRadioSwitch>
      </div>
      <div v-if="form.displayTextSynced" :class="$style.syncedDisplayText" data-cy="synced-display-text">
        {{ form.displayText || form.label }}
      </div>
      <div v-else :class="$style.editorWrap">
        <VueEasyMDE
            id="question-display-text"
            v-model="form.displayText"
            :options="displayTextEditorOptions"
            :class="$style.editor"
        />
      </div>
    </div>
    <div :class="$style.grid">
      <NcSelect
          v-model="form.type"
          :input-label="t('structureddiary', 'Type')"
          label="id"
          :clearable="false"
          :options="typeOptions"
          :reduce="(option: { id: string, value: QuestionType }) => option.value"/>
      <NcCheckboxRadioSwitch
          v-model="form.active"
          type="switch">
        {{ t('structureddiary', 'Active') }}
      </NcCheckboxRadioSwitch>
    </div>
    <div v-if="showsRangeFields" :class="$style.grid">
      <NcTextField
          v-model="form.minimum"
          :error="minimumHasError || rangeOrderError"
          :helper-text="minimumHelperText"
          :label="t('structureddiary', 'Minimum')"
          inputmode="decimal"
          type="number"/>
      <NcTextField
          v-model="form.maximum"
          :error="maximumHasError || rangeOrderError"
          :helper-text="maximumHelperText"
          :label="t('structureddiary', 'Maximum')"
          inputmode="decimal"
          type="number"/>
    </div>
    <div v-if="showsChoiceFields" :class="$style.field">
      <label :class="$style.editorLabel" for="question-choice-input">{{ t('structureddiary', 'Choices') }}</label>
      <div v-if="form.choices.length > 0" :class="$style.choiceList" data-cy="question-choices">
        <span
            v-for="choice in form.choices"
            :key="choice"
            :class="$style.choiceChip">
          <span>{{ choice }}</span>
          <button
              type="button"
              :class="$style.choiceRemove"
              :aria-label="t('structureddiary', 'Remove choice {choice}', {choice})"
              @click="removeChoice(choice)">
            &times;
          </button>
        </span>
      </div>
      <div :class="$style.choiceInputRow">
        <NcTextField
            id="question-choice-input"
            v-model="form.choiceDraft"
            :label="t('structureddiary', 'New choice')"
            type="text"
            @keydown.enter.prevent="addChoice"/>
        <NcButton
            variant="secondary"
            :disabled="normalizedChoice(form.choiceDraft) === ''"
            @click="addChoice">
          {{ t('structureddiary', 'Add choice') }}
        </NcButton>
      </div>
      <p :class="$style.helperText">{{ t('structureddiary', 'Add each selectable option separately. Duplicate options are ignored.') }}</p>
    </div>
    <div v-if="showsTemplateTextField" :class="$style.field">
      <label :class="$style.editorLabel" for="question-template-text">{{ t('structureddiary', 'Template text') }}</label>
      <div v-if="showsTemplateMarkdownEditor" :class="$style.editorWrap">
        <VueEasyMDE
            id="question-template-text"
            v-model="form.templateText"
            :options="baseDisplayTextEditorOptions"
            :class="$style.editor"
        />
      </div>
      <NcTextField
          v-else-if="showsTemplateSingleLineInput"
          id="question-template-text"
          v-model="form.templateText"
          :label="t('structureddiary', 'Template text')"
          type="text"/>
    </div>
    <div :class="$style.actions">
      <NcButton variant="secondary" @click="cancelQuestionEdit">
        {{ t('structureddiary', 'Cancel') }}
      </NcButton>
      <NcButton type="submit" variant="primary">
        {{ t('structureddiary', 'Save question') }}
      </NcButton>
    </div>
  </form>
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

.displayTextHeader {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.syncedDisplayText {
	min-height: 46px;
	padding: 12px 14px;
	border: 1px solid var(--color-border-maxcontrast);
	border-radius: 14px;
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
	white-space: pre-wrap;
	overflow-wrap: anywhere;
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

.choiceList {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.choiceChip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 30px;
  padding: 2px 8px 2px 12px;
  border: 1px solid var(--color-border-dark);
  border-radius: 999px;
  background: var(--color-background-hover);
}

.choiceRemove {
  display: inline-grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--color-text-maxcontrast);
  cursor: pointer;
}

.choiceRemove:hover,
.choiceRemove:focus-visible {
  background: var(--color-background-dark);
  color: var(--color-main-text);
}

.choiceInputRow {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 10px;
  align-items: end;
}

.helperText {
  margin: 0;
  color: var(--color-text-maxcontrast);
  font-size: 0.9em;
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

  .choiceInputRow {
    grid-template-columns: 1fr;
  }
}
</style>
