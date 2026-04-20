<script setup lang="ts">
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationSearch from '@nextcloud/vue/components/NcAppNavigationSearch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiCogOutline, mdiShareVariantOutline } from '@mdi/js'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useStructuredDiaryStore } from '@/stores/structuredDiary'
import { isManagementRoute } from '@/services/workspaceRoute'
import type { Diary, DiaryGroupSet } from '@/types/types'

const labels: Record<keyof DiaryGroupSet, string> = {
	owned: 'Owned diaries',
	managed: 'Shared with full access',
	writable: 'Shared with write access',
	readable: 'Shared with read access',
}

const store = useStructuredDiaryStore()
const router = useRouter()
const inManagement = computed(() => isManagementRoute(String(router.currentRoute.value.name ?? 'entries')))

const visibleGroupEntries = computed(() =>
	Object.entries(store.diaryGroups).filter(([, items]) => items.length > 0) as Array<[keyof DiaryGroupSet, Diary[]]>)

function diaryLabel(diary: Diary): string {
	return diary.is_owner ? diary.title : `${diary.title} (${diary.user_id})`
}

function diaryIcon(): string {
	return mdiShareVariantOutline
}

function selectDiary(diary: Diary): void {
	store.setSelectedDiary(diary.id)
	void router.push({ name: 'entriesIndex' })
}

function openManagement(): void {
	if (inManagement.value) {
		void router.push({ name: 'entriesIndex' })
		return
	}

	if (store.selectedDiaryId === null && store.diaries.length > 0) {
		store.setSelectedDiary(store.diaries[0].id)
	}
	void router.push({ name: 'diaries' })
}
</script>

<template>
	<NcAppNavigation>
		<template #search>
			<div :class="$style.search">
				<NcAppNavigationSearch v-model="store.diarySearch" label="Search diaries" />
			</div>
		</template>

		<template #list>
			<div :class="$style.sections">
				<section
					v-for="[key, items] in visibleGroupEntries"
					:key="key"
					:class="$style.section">
					<h3 :class="$style.sectionTitle">
						{{ labels[key] }}
					</h3>
					<NcAppNavigationItem
						v-for="diary in items"
						:key="diary.id"
						:name="diaryLabel(diary)"
						:active="diary.id === store.selectedDiaryId"
						@click="selectDiary(diary)">
						<template v-if="!diary.is_owner" #icon>
							<NcIconSvgWrapper :path="diaryIcon()" />
						</template>
					</NcAppNavigationItem>
				</section>
			</div>
		</template>

		<template #footer>
			<div :class="$style.footer">
				<NcAppNavigationItem
					:name="inManagement ? 'Entries' : 'Management'"
					@click="openManagement()">
					<template #icon>
						<NcIconSvgWrapper :path="mdiCogOutline" />
					</template>
				</NcAppNavigationItem>
			</div>
		</template>
	</NcAppNavigation>
</template>

<style module>
.search {
	padding: 4px;
}

.sections {
	padding: 4px 0;
}

.section {
	margin-bottom: 12px;
}

.sectionTitle {
	margin: 0;
	padding: 8px 14px 6px;
	color: var(--color-text-maxcontrast);
	font-size: 0.75rem;
	font-weight: 600;
	letter-spacing: 0.06em;
	text-transform: uppercase;
}

.footer {
	display: grid;
	gap: 4px;
	padding: 4px 0;
}
</style>
