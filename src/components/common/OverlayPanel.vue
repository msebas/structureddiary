<script setup lang="ts">
import { t } from '@nextcloud/l10n'

const props = defineProps<{
	title: string
	open: boolean
}>()

const emit = defineEmits<{
	(event: 'close'): void
}>()
</script>

<template>
	<Teleport to="body">
		<transition name="overlay-fade">
			<div v-if="props.open" :class="$style.overlay">
				<div :class="$style.panel">
					<header :class="$style.header">
						<h3 :class="$style.title">
							{{ props.title }}
						</h3>
						<button type="button" :class="$style.close" @click="emit('close')">
							{{ t('structureddiary', 'Close') }}
						</button>
					</header>
					<div :class="$style.body">
						<slot />
					</div>
				</div>
			</div>
		</transition>
	</Teleport>
</template>

<style module>
.overlay {
	position: fixed;
	inset: 0;
	z-index: 80;
	display: grid;
	place-items: center;
	padding: 24px;
	background: rgba(18, 23, 31, 0.58);
	backdrop-filter: blur(6px);
}

.panel {
	width: min(920px, 100%);
	max-height: min(90vh, 100%);
	overflow: auto;
	border: 1px solid rgba(39, 54, 74, 0.22);
	border-radius: 24px;
	background:
		linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(245, 248, 252, 0.98));
	box-shadow: 0 28px 70px rgba(12, 25, 46, 0.22);
}

.header {
	position: sticky;
	top: 0;
	z-index: 40;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 18px 22px;
	border-bottom: 1px solid rgba(39, 54, 74, 0.12);
	background: rgba(255, 255, 255, 0.96);
}

.title {
	margin: 0;
	font-size: 1.1rem;
	font-weight: 700;
}

.close {
	border: 0;
	border-radius: 999px;
	padding: 10px 14px;
	background: #102542;
	color: white;
	cursor: pointer;
}

.body {
	padding: 22px;
}
</style>
