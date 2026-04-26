<script setup lang="ts">
import type { Question } from '@/types/types'
import { formatDateTime } from '@/utils/format'
import {computed} from "vue";
import {useRouter} from "vue-router";
import {useStructuredDiaryStore} from "@/stores/structuredDiary";

const props = withDefaults(defineProps<{
  question: Question | null
}>(), {
  question: null
})

const store = useStructuredDiaryStore()

const question = computed(()=> {
  if (props.question==null) {
    return store.selectedQuestion
  }
  return props.question
})

const versionChain = computed(()=> {
  if (props.question==null) {
    return store.selectedQuestionVersionChain ?? []
  }
  return store.getQuestionVersionChain(props.question.id)
})

</script>

<template>
  <section :class="$style.card">
    <template v-if="question">
      <header :class="$style.header">
        <div>
          <h2 :class="$style.title">{{ question.label }}</h2>
          <div :class="$style.meta">{{ formatDateTime(question.created_at) }}</div>
        </div>
        <div :class="$style.state">
          {{ question.active ? 'Active' : 'Inactive' }}
        </div>
      </header>
      <div :class="$style.body">
        <p><strong>Display text:</strong> {{ question.display_text }}</p>
        <p><strong>Type:</strong> {{ question.type }}</p>
        <p><strong>Template text:</strong> {{ question.template_text || 'n/a' }}</p>
        <p><strong>Minimum:</strong> {{ question.minimum ?? 'n/a' }}</p>
        <p><strong>Maximum:</strong> {{ question.maximum ?? 'n/a' }}</p>
        <p><strong>Choices:</strong> {{ question.choices?.join(', ') || 'n/a' }}</p>
      </div>
      <section v-if="versionChain.length > 0" :class="$style.versions">
        <h3>Versions</h3>
        <ul>
          <li v-for="version in versionChain" :key="version.id">
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

