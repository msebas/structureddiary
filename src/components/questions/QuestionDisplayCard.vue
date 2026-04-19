<script setup lang="ts">
import type { Question } from '@/types/types'
import { formatDateTime } from '@/utils/format'

const props = defineProps<{
	question: Question | null
	versionChain: Question[]
}>()
</script>

<template>
	<section :class="$style.card">
		<template v-if="props.question">
			<header :class="$style.header">
				<div>
					<h2 :class="$style.title">{{ props.question.label }}</h2>
					<div :class="$style.meta">{{ formatDateTime(props.question.created_at) }}</div>
				</div>
				<div :class="$style.state">
					{{ props.question.active ? 'Active' : 'Inactive' }}
				</div>
			</header>
			<div :class="$style.body">
				<p><strong>Display text:</strong> {{ props.question.display_text }}</p>
				<p><strong>Type:</strong> {{ props.question.type }}</p>
				<p><strong>Template text:</strong> {{ props.question.template_text || 'n/a' }}</p>
				<p><strong>Minimum:</strong> {{ props.question.minimum ?? 'n/a' }}</p>
				<p><strong>Maximum:</strong> {{ props.question.maximum ?? 'n/a' }}</p>
				<p><strong>Choices:</strong> {{ props.question.choices?.join(', ') || 'n/a' }}</p>
			</div>
			<section v-if="props.versionChain.length > 0" :class="$style.versions">
				<h3>Versions</h3>
				<ul>
					<li v-for="version in props.versionChain" :key="version.id">
						{{ formatDateTime(version.created_at) }} · {{ version.label }}
					</li>
				</ul>
			</section>
		</template>
		<template v-else>
			<div :class="$style.empty">Select a question to inspect it here.</div>
		</template>
	</section>
</template>

<style module>
.card {
	display: grid;
	gap: 18px;
	padding: 22px;
	border-radius: 24px;
	background: rgba(255, 255, 255, 0.98);
	box-shadow: 0 20px 48px rgba(12, 25, 46, 0.09);
}

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
	color: #69798b;
}

.state {
	border-radius: 999px;
	padding: 8px 12px;
	background: rgba(16, 37, 66, 0.08);
	font-weight: 700;
}

.body p {
	margin: 0 0 10px;
}

.versions ul {
	margin: 8px 0 0;
	padding-left: 18px;
}

.empty {
	display: grid;
	place-items: center;
	min-height: 240px;
	color: #718194;
}
</style>

