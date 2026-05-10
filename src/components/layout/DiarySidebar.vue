<script setup lang="ts">
import type { Diary, DiaryGroupSet } from '@/types/types'
import { t } from '@nextcloud/l10n'

const props = defineProps<{
	groups: DiaryGroupSet
	search: string
	selectedDiaryId: number | null
}>()

const emit = defineEmits<{
	(event: 'update:search', value: string): void
	(event: 'select', diary: Diary): void
	(event: 'create'): void
	(event: 'manage'): void
}>()

const labels: Record<keyof DiaryGroupSet, string> = {
	owned: t('structureddiary', 'Owned diaries'),
	managed: t('structureddiary', 'Shared with full access'),
	writable: t('structureddiary', 'Shared with write access'),
	readable: t('structureddiary', 'Shared with read access'),
}
</script>

<template>
	<aside :class="$style.sidebar">
		<div :class="$style.searchWrap">
			<input
				:value="props.search"
				type="search"
				:placeholder="t('structureddiary', 'Search diaries')"
				:class="$style.search"
				@input="emit('update:search', ($event.target as HTMLInputElement).value)">
		</div>

		<section
			v-for="(items, key) in props.groups"
			:key="key"
			v-show="items.length > 0"
			:class="$style.group">
			<h3 :class="$style.groupTitle">
				{{ labels[key as keyof DiaryGroupSet] }}
			</h3>
			<button
				v-for="diary in items"
				:key="diary.id"
				type="button"
				:class="[$style.diaryItem, diary.id === props.selectedDiaryId && $style.diaryItemActive]"
				@click="emit('select', diary)">
				<div :class="$style.diaryItemMain">
					<span :class="$style.diaryTitle">{{ diary.title }}</span>
					<span v-if="!diary.is_owner" :class="$style.sharedBadge">{{ t('structureddiary', 'Shared') }}</span>
				</div>
				<div v-if="!diary.is_owner" :class="$style.diaryMeta">
					{{ diary.user_id }}
				</div>
			</button>
		</section>

		<div :class="$style.footer">
			<button type="button" :class="$style.createButton" @click="emit('create')">
				{{ t('structureddiary', 'New diary') }}
			</button>
			<button type="button" :class="$style.manageButton" @click="emit('manage')">
				{{ t('structureddiary', 'Open management') }}
			</button>
		</div>
	</aside>
</template>

<style module>
.sidebar {
	display: flex;
	flex-direction: column;
	min-height: 0;
	padding: 18px;
	background:
		radial-gradient(circle at top left, rgba(255, 206, 166, 0.36), transparent 44%),
		linear-gradient(180deg, #f9efe2, #f3f6fb);
	border-right: 1px solid rgba(27, 41, 58, 0.12);
}

.searchWrap {
	padding-bottom: 12px;
}

.search {
	width: 100%;
	border: 1px solid rgba(18, 37, 66, 0.16);
	border-radius: 16px;
	padding: 12px 14px;
	background: rgba(255, 255, 255, 0.92);
}

.group {
	display: grid;
	gap: 8px;
	padding: 12px 0;
}

.groupTitle {
	margin: 0;
	font-size: 0.86rem;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	color: #5c6a7b;
}

.diaryItem {
	display: grid;
	gap: 6px;
	border: 1px solid transparent;
	border-radius: 18px;
	padding: 12px 14px;
	background: rgba(255, 255, 255, 0.66);
	cursor: pointer;
	text-align: left;
}

.diaryItemActive {
	border-color: rgba(217, 105, 65, 0.45);
	background: rgba(255, 255, 255, 0.95);
	box-shadow: 0 10px 25px rgba(27, 41, 58, 0.08);
}

.diaryItemMain {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
}

.diaryTitle {
	font-weight: 700;
	color: #13253d;
}

.sharedBadge {
	border-radius: 999px;
	padding: 3px 9px;
	background: rgba(16, 37, 66, 0.1);
	font-size: 0.72rem;
	font-weight: 700;
}

.diaryMeta {
	font-size: 0.82rem;
	color: #57677a;
}

.footer {
	margin-top: auto;
	display: grid;
	gap: 10px;
	padding-top: 16px;
}

.createButton,
.manageButton {
	width: 100%;
	border: 0;
	border-radius: 18px;
	padding: 14px;
	background: #102542;
	color: white;
	font-weight: 700;
	cursor: pointer;
}

.createButton {
	background: rgba(16, 37, 66, 0.08);
	color: #102542;
}
</style>
