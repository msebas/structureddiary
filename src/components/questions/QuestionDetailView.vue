<script setup lang="ts">
import { computed, watch } from 'vue'
import { formatDateTime } from '@/utils/format'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import { t } from '@nextcloud/l10n'

const store = useStructuredDiaryStore()

const question = computed(() => store.selectedQuestion)
const versionChain = computed(() => store.selectedQuestionVersionChain)

watch(() => store.selectedQuestionId, async (questionId) => {
	if (questionId === null) {
		return
	}
	await store.loadQuestionVersions(questionId)
}, { immediate: true })
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
				<p><strong>{{ t('structureddiary', 'Display text:') }}</strong> {{ question.display_text }}</p>
				<p><strong>{{ t('structureddiary', 'Type:') }}</strong> {{ question.type }}</p>
				<p><strong>{{ t('structureddiary', 'Template text:') }}</strong> {{ question.template_text || t('structureddiary', 'n/a') }}</p>
				<p><strong>{{ t('structureddiary', 'Minimum:') }}</strong> {{ question.minimum ?? t('structureddiary', 'n/a') }}</p>
				<p><strong>{{ t('structureddiary', 'Maximum:') }}</strong> {{ question.maximum ?? t('structureddiary', 'n/a') }}</p>
				<p><strong>{{ t('structureddiary', 'Choices:') }}</strong> {{ question.choices?.join(', ') || t('structureddiary', 'n/a') }}</p>
			</div>
			<section v-if="versionChain.length > 0" :class="$style.versions">
				<h3>{{ t('structureddiary', 'Versions') }}</h3>
				<ul>
					<li v-for="version in versionChain" :key="version.id">
						{{ formatDateTime(version.created_at) }} · {{ version.label }}
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
}

.meta {
	margin-top: 6px;
}

.state {
	padding: 8px 12px;
}

.body p {
	margin: 0 0 10px;
}

.versions ul {
	margin: 8px 0 0;
	padding-left: 18px;
}

.empty {
	min-height: 240px;
}
</style>
