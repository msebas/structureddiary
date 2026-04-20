<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiBookOpenVariant, mdiPlus } from '@mdi/js'
import { computed } from 'vue'
import type { Diary, Entry, Question } from '@/types/types'

const props = defineProps<{
	diary: Diary | null
	entry: Entry | null
	question: Question | null
	view: string
	showNewEntryButton?: boolean
}>()

const emit = defineEmits<{
	(event: 'openDiary'): void
	(event: 'newDiary'): void
	(event: 'newEntry'): void
	(event: 'editEntry'): void
	(event: 'editDiary'): void
	(event: 'editQuestion'): void
}>()

const showCreateButton = computed(() =>
	props.view === 'diary' || props.view === 'diary-edit'
		? true
		: (props.view === 'entry' || props.view === 'entry-edit') && props.showNewEntryButton !== false)

const createAriaLabel = computed(() => props.view.startsWith('diary') ? 'Create new diary' : 'Create new entry')

function triggerCreate(): void {
	if (props.view.startsWith('diary')) {
		emit('newDiary')
		return
	}

	emit('newEntry')
}
</script>

<template>
	<header :class="$style.header">
		<div :class="$style.leading">
			<NcButton
				variant="tertiary"
				aria-label="Open diary"
				:class="$style.diaryButton"
				@click="emit('openDiary')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiBookOpenVariant" />
				</template>
			</NcButton>
			<h1 :class="$style.title">
				{{ props.diary?.title ?? 'Structured Diary' }}
			</h1>
		</div>

		<div :class="$style.actions">
			<NcButton
				v-if="showCreateButton"
				:aria-label="createAriaLabel"
				@click="triggerCreate()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
			</NcButton>
			<NcButton
				v-if="props.entry && props.view !== 'entry-edit'"
				variant="secondary"
				@click="emit('editEntry')">
				Edit entry
			</NcButton>
			<NcButton
				v-if="props.diary"
				variant="secondary"
				@click="emit('editDiary')">
				Edit diary
			</NcButton>
			<NcButton
				v-if="props.question"
				variant="secondary"
				@click="emit('editQuestion')">
				Edit question
			</NcButton>
		</div>
	</header>
</template>

<style module>
.header {
	position: sticky;
	top: 0;
	z-index: 30;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 20px;
	padding: 18px 22px;
	background: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);
}

.leading {
	display: flex;
	align-items: center;
	gap: 14px;
	min-width: 0;
}

.diaryButton {
	margin-inline-start: 18px;
}

.title {
	margin: 0;
	font-size: clamp(1.2rem, 2vw, 1.8rem);
	line-height: 1.1;
	word-break: break-word;
}

.actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 10px;
}
</style>
