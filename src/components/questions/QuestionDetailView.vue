<script setup lang="ts">
import { computed, watch } from 'vue'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import { formatDateTime } from '@/utils/format'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import { t } from '@nextcloud/l10n'

const store = useStructuredDiaryStore()

const question = computed(() => store.selectedQuestion)
const versionChain = computed(() => store.selectedQuestionVersionChain)
const showsDisplayText = computed(() => question.value !== null && question.value.display_text !== question.value.label)
const showsRangeValues = computed(() => question.value !== null && ['text', 'integer', 'editable_select', 'time', 'number', 'rating'].includes(question.value.type))
const showsChoices = computed(() => question.value !== null && (question.value.type === 'select' || question.value.type === 'editable_select'))
const showsTemplateText = computed(() => question.value !== null && question.value.template_text.trim() !== '' && ['text', 'editable_select', 'number', 'integer'].includes(question.value.type))
const templateTextHasMultipleLines = computed(() => (question.value?.template_text.match(/\r\n|\r|\n/g)?.length ?? 0) > 0)

watch(() => store.selectedQuestionId, async (questionId) => {
	if (questionId === null) {
		return
	}
	await store.loadQuestionVersions(questionId)
}, { immediate: true })

async function selectQuestionVersion(questionId: number): Promise<void> {
	if (store.selectedDiaryId === null || question.value?.id === questionId) {
		return
	}

	await store.pushWorkspaceRoute({
		name: 'question',
		params: { diaryId: store.selectedDiaryId, questionId },
	})
	await store.loadQuestion(questionId)
}
</script>

<template>
	<section class="workspace-card">
		<template v-if="question">
			<header :class="$style.header">
				<div>
					<h2 :class="$style.title">{{ question.label }}</h2>
					<div :class="['workspace-card-muted', $style.meta]">{{ formatDateTime(question.created_at) }}</div>
				</div>
				<div :class="['workspace-card-pill', $style.state]">
					{{ question.active ? t('structureddiary', 'Active') : t('structureddiary', 'Inactive') }}
				</div>
			</header>
			<div :class="$style.body">
				<div v-if="showsDisplayText" :class="$style.detailRow">
					<strong>{{ t('structureddiary', 'Display text:') }}</strong>
					<span>{{ question.display_text }}</span>
				</div>
				<div :class="$style.detailRow">
					<strong>{{ t('structureddiary', 'Type:') }}</strong>
					<span>{{ question.type }}</span>
				</div>
				<div v-if="showsRangeValues" :class="$style.detailRow">
					<strong>{{ t('structureddiary', 'Minimum:') }}</strong>
					<span>{{ question.minimum ?? t('structureddiary', 'n/a') }}</span>
				</div>
				<div v-if="showsRangeValues" :class="$style.detailRow">
					<strong>{{ t('structureddiary', 'Maximum:') }}</strong>
					<span>{{ question.maximum ?? t('structureddiary', 'n/a') }}</span>
				</div>
				<div v-if="showsChoices" :class="$style.detailRow">
					<strong>{{ t('structureddiary', 'Choices:') }}</strong>
					<span>{{ question.choices?.join(', ') || t('structureddiary', 'n/a') }}</span>
				</div>
				<div v-if="showsTemplateText" :class="[$style.detailRow, templateTextHasMultipleLines && $style.detailRowStacked]">
					<strong>{{ t('structureddiary', 'Template text:') }}</strong>
					<NcRichText
						:text="question.template_text"
						:use-markdown="true"
						:use-extended-markdown="true" />
				</div>
			</div>
			<section v-if="versionChain.length > 0" :class="$style.versions">
				<h3>{{ t('structureddiary', 'Versions') }}</h3>
				<ul>
					<li v-for="version in versionChain" :key="version.id">
						<button
							type="button"
							:class="[$style.versionButton, version.id === question.id && $style.versionButtonActive]"
							:disabled="version.id === question.id"
							@click="selectQuestionVersion(version.id)">
							{{ formatDateTime(version.created_at) }} · {{ version.label }}
						</button>
					</li>
				</ul>
			</section>
		</template>
		<template v-else>
			<div :class="['workspace-card-empty', $style.empty]">{{ t('structureddiary', 'Select a question to inspect it here.') }}</div>
		</template>
	</section>
</template>

<style module>
.header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 14px;
}

.title {
	margin: 0;
	font-size: clamp(1.2rem, 2vw, 1.6rem);
	line-height: 1.25;
	overflow-wrap: anywhere;
}

.meta {
	margin-top: 6px;
}

.state {
	padding: 8px 12px;
}

.body {
	display: grid;
	gap: 10px;
}

.detailRow {
	display: grid;
	grid-template-columns: 150px minmax(0, 1fr);
	gap: 10px;
	align-items: baseline;
	overflow-wrap: anywhere;
}

.detailRowStacked {
	grid-template-columns: 1fr;
	gap: 6px;
}

.versions ul {
	margin: 8px 0 0;
	padding-left: 18px;
}

.versionButton {
	border: 0;
	padding: 2px 0;
	background: transparent;
	color: var(--color-primary-element);
	text-align: left;
	cursor: pointer;
	overflow-wrap: anywhere;
}

.versionButton:hover,
.versionButton:focus-visible {
	text-decoration: underline;
}

.versionButtonActive {
	color: var(--color-main-text);
	font-weight: 700;
	cursor: default;
}

.versionButtonActive:hover,
.versionButtonActive:focus-visible {
	text-decoration: none;
}

.empty {
	min-height: 240px;
}

@media (max-width: 640px) {
	.header {
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto;
		gap: 10px;
	}

	.title {
		font-size: clamp(1.1rem, 5vw, 1.35rem);
	}

	.state {
		padding: 6px 10px;
	}
}

@media (max-width: 430px) {
	.header {
		grid-template-columns: minmax(0, 1fr);
	}

	.state {
		justify-self: start;
	}

	.detailRow {
		grid-template-columns: 1fr;
		gap: 4px;
	}
}
</style>
