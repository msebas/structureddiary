<script setup lang="ts">
import type { Answer, Question } from '@/types/types'
import { formatQuestionValue } from '@/utils/format'

const props = defineProps<{
	answer?: Answer
	question: Question
}>()
</script>

<template>
	<div :class="$style.answer">
		<template v-if="props.question.type === 'boolean'">
			<div :class="$style.toggleRow">
				<div :class="[$style.toggle, props.answer?.numeric_content === 1 && $style.toggleOn]" />
				<span>{{ props.answer?.numeric_content === 1 ? 'Yes' : 'No' }}</span>
			</div>
		</template>
		<template v-else-if="props.question.type === 'rating'">
			<div :class="$style.rating">
				<span
					v-for="index in 10"
					:key="index"
					:class="[index <= Math.round(props.answer?.numeric_content ?? 0) && $style.starOn]">
					★
				</span>
			</div>
		</template>
		<template v-else>
			<div :class="$style.text">
				{{ formatQuestionValue(props.answer, props.question) }}
			</div>
		</template>
	</div>
</template>

<style module>
.answer {
	display: grid;
	gap: 10px;
}

.text {
	white-space: pre-wrap;
	line-height: 1.6;
	color: #23364e;
}

.toggleRow {
	display: inline-flex;
	align-items: center;
	gap: 10px;
}

.toggle {
	width: 42px;
	height: 24px;
	border-radius: 999px;
	background: #bcc6d1;
	position: relative;
}

.toggle::after {
	content: '';
	position: absolute;
	top: 3px;
	left: 3px;
	width: 18px;
	height: 18px;
	border-radius: 50%;
	background: white;
}

.toggleOn {
	background: #2d8f63;
}

.toggleOn::after {
	left: 21px;
}

.rating {
	font-size: 1.1rem;
	letter-spacing: 0.05em;
	color: #c5ccd4;
}

.starOn {
	color: #d96941;
}
</style>

