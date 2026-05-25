<script setup lang="ts">
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import type { NcSelectUsersModel } from '@nextcloud/vue/components/NcSelectUsers'
import { computed, ref, watch } from 'vue'
import { userService } from '@/services'
import type { DiaryShareInput } from '@/stores/structuredDiary'
import { Permissions, type DiaryShare, type SelectOption } from '@/types/types'
import { t } from '@nextcloud/l10n'

const props = defineProps<{
	shares: DiaryShare[]
}>()

const emit = defineEmits<{
	(event: 'update:shares', payload: DiaryShareInput[]): void
}>()

const readers = ref<NcSelectUsersModel[]>([])
const analysts = ref<NcSelectUsersModel[]>([])
const writers = ref<NcSelectUsersModel[]>([])
const managers = ref<NcSelectUsersModel[]>([])
const userOptions = ref<NcSelectUsersModel[]>([])
const shareWarning = ref<string | null>(null)
const loadingUsers = ref(false)

function toUserModel(option: SelectOption<string>): NcSelectUsersModel {
	return {
		id: option.value,
		user: option.value,
		displayName: option.label,
	}
}

function fromUserId(userId: string): NcSelectUsersModel {
	return {
		id: userId,
		user: userId,
		displayName: userId,
	}
}

function upsertUserOptions(nextItems: NcSelectUsersModel[]): void {
	const merged = new Map(userOptions.value.map((item) => [item.id, item]))
	for (const item of nextItems) {
		merged.set(item.id, item)
	}
	userOptions.value = Array.from(merged.values())
}

async function searchUsers(query: string): Promise<void> {
	loadingUsers.value = true
	try {
		const matches = await userService.search(query)
		upsertUserOptions(matches.map(toUserModel))
	} finally {
		loadingUsers.value = false
	}
}

function shareUsers(permissionMask: number): NcSelectUsersModel[] {
	return props.shares
		.filter((share) => (share.permission & permissionMask) === permissionMask)
		.map((share) => fromUserId(share.shared_with))
}

function normalizeSelection(items: NcSelectUsersModel[]): NcSelectUsersModel[] {
	const seen = new Set<string>()
	return items.filter((item) => {
		if (seen.has(item.id)) {
			return false
		}
		seen.add(item.id)
		return true
	})
}

function ensureReadersIncludeElevated(): void {
	const requiredReaders = [...analysts.value, ...writers.value, ...managers.value]
	const readerIds = new Set(readers.value.map((item) => item.id))
	const missingReaders = requiredReaders.filter((item) => !readerIds.has(item.id))
	if (missingReaders.length === 0) {
		return
	}

	readers.value = normalizeSelection([...readers.value, ...missingReaders])
	shareWarning.value = t('structureddiary', 'Analyze, write, and manage access imply read access. Missing readers were added again.')
}

const sharePayload = computed<DiaryShareInput[]>(() => {
	const entries = new Map<string, number>()
	for (const reader of readers.value) {
		entries.set(reader.id, Permissions.READ)
	}
	for (const analyst of analysts.value) {
		entries.set(analyst.id, (entries.get(analyst.id) ?? 0) | Permissions.READ | Permissions.ANALYZE)
	}
	for (const writer of writers.value) {
		entries.set(writer.id, (entries.get(writer.id) ?? 0) | Permissions.READ | Permissions.WRITE)
	}
	for (const manager of managers.value) {
		entries.set(manager.id, (entries.get(manager.id) ?? 0) | Permissions.READ | Permissions.MANAGE)
	}

	return Array.from(entries.entries()).map(([sharedWith, permission]) => ({ sharedWith, permission }))
})

watch(() => props.shares, () => {
	readers.value = shareUsers(Permissions.READ)
	analysts.value = shareUsers(Permissions.READ | Permissions.ANALYZE)
	writers.value = shareUsers(Permissions.READ | Permissions.WRITE)
	managers.value = shareUsers(Permissions.READ | Permissions.MANAGE)
	shareWarning.value = null
	upsertUserOptions([
		...readers.value,
		...analysts.value,
		...writers.value,
		...managers.value,
	])
}, { immediate: true })

watch([analysts, writers, managers], () => {
	ensureReadersIncludeElevated()
}, { deep: true })

watch(sharePayload, (payload) => {
	emit('update:shares', payload)
}, { immediate: true })
</script>

<template>
	<section class="workspace-card workspace-card--form">
		<h2 :class="$style.heading">{{ t('structureddiary', 'Share diary') }}</h2>

		<NcNoteCard v-if="shareWarning" type="warning">
			{{ shareWarning }}
		</NcNoteCard>

		<div :class="$style.field">
			<label :class="$style.label">{{ t('structureddiary', 'Readers') }}</label>
			<NcSelectUsers
				:class="$style.shareUserSelect"
				:model-value="readers"
				:options="userOptions"
				:loading="loadingUsers"
				:input-label="t('structureddiary', 'Readers')"
				:placeholder="t('structureddiary', 'Select readers')"
				:multiple="true"
				:label-outside="true"
				@search="searchUsers"
				@update:model-value="readers = Array.isArray($event) ? normalizeSelection($event) : []" />
		</div>

		<div :class="$style.field">
			<label :class="$style.label">{{ t('structureddiary', 'Writers') }}</label>
			<NcSelectUsers
				:class="$style.shareUserSelect"
				:model-value="writers"
				:options="userOptions"
				:loading="loadingUsers"
				:input-label="t('structureddiary', 'Writers')"
				:placeholder="t('structureddiary', 'Select writers')"
				:multiple="true"
				:label-outside="true"
				@search="searchUsers"
				@update:model-value="writers = Array.isArray($event) ? normalizeSelection($event) : []" />
		</div>

		<div :class="$style.field">
			<label :class="$style.label">{{ t('structureddiary', 'Analyze') }}</label>
			<NcSelectUsers
				:class="$style.shareUserSelect"
				:model-value="analysts"
				:options="userOptions"
				:loading="loadingUsers"
				:input-label="t('structureddiary', 'Analyze')"
				:placeholder="t('structureddiary', 'Select users allowed to analyze')"
				:multiple="true"
				:label-outside="true"
				@search="searchUsers"
				@update:model-value="analysts = Array.isArray($event) ? normalizeSelection($event) : []" />
		</div>

		<div :class="$style.field">
			<label :class="$style.label">{{ t('structureddiary', 'Managers') }}</label>
			<NcSelectUsers
				:class="$style.shareUserSelect"
				:model-value="managers"
				:options="userOptions"
				:loading="loadingUsers"
				:input-label="t('structureddiary', 'Managers')"
				:placeholder="t('structureddiary', 'Select managers')"
				:multiple="true"
				:label-outside="true"
				@search="searchUsers"
				@update:model-value="managers = Array.isArray($event) ? normalizeSelection($event) : []" />
		</div>
	</section>
</template>

<style module>
.heading {
	margin: 0;
	font-size: 1.6rem;
}

.field {
	display: grid;
	gap: 8px;
	min-width: 0;
}

.label {
	font-size: 0.9rem;
	font-weight: 600;
}

.shareUserSelect {
	display: block !important;
	min-width: 0 !important;
	height: auto !important;
	margin-block-end: 0 !important;
}

.shareUserSelect :global(.vs__selected-options) {
	min-width: 0;
}

.shareUserSelect :global(.vs__dropdown-toggle) {
	min-height: calc(var(--default-clickable-area) - 2 * var(--border-width-input));
}

.shareUserSelect :global(.vs__selected) {
	flex: 0 1 auto;
}
</style>
