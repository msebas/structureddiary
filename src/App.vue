<script setup lang="ts">
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import PrimaryWorkspaceView from '@/views/PrimaryWorkspaceView.vue'
</script>

<template>
	<NcContent app-name="structureddiary">
		<NcAppContent :class="$style.content">
			<PrimaryWorkspaceView />
		</NcAppContent>
	</NcContent>
</template>

<style module>
.content {
	min-height: 100vh;
	font-family: 'IBM Plex Sans', 'Segoe UI', sans-serif;
	color: #12253e;
	background:
		radial-gradient(circle at top left, rgba(245, 179, 121, 0.2), transparent 26%),
		radial-gradient(circle at bottom right, rgba(124, 177, 224, 0.16), transparent 30%),
		linear-gradient(180deg, #fff8f2, #eff4fa);
}
</style>
