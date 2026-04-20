<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiPlus } from '@mdi/js'
import { computed } from 'vue'
import {useStructuredDiaryStore} from "@/stores/structuredDiary";
import {useRoute} from "vue-router";

const router = useRoute();
const store = useStructuredDiaryStore()
const diary = computed(() => store.diaries[Number(router.params.entryId)])

import { getCurrentUser } from '@nextcloud/auth'
import {Permissions} from "@/types/types";

const user = computed(()=> getCurrentUser()?.uid)

const manage_permissions_on_diary = computed(()=>{
  return (store.diaryShares?.[diary.value?.id]?.[user.value || ""].permission & Permissions.MANAGE) !==0
})

</script>

<template>
	<header :class="$style.header">
		<div :class="$style.leading">
			<h1 :class="$style.title">
				{{ diary?.title ?? 'Structured Diary' }}
			</h1>
		</div>

		<div :class="$style.actions">
			<NcButton
				aria-label="Create new diary"
				@click="store.startCreatingDiary()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
			</NcButton>
			<NcButton
				v-if="diary!=null && manage_permissions_on_diary"
				variant="secondary"
				@click="store.editDiary(diary.id)">
				Edit diary
			</NcButton>
			<NcButton
				v-if="diary!=null && manage_permissions_on_diary"
				variant="secondary"
				@click="store.editDiaryShares(diary.id)">
				Edit diary share
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
