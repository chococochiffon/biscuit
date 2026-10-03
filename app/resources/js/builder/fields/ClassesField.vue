<script setup lang="ts">
import { computed, ref } from 'vue'
import { classesError, parseClasses } from '../customCss'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { BuilderNode } from '../types'

// ブロックの追加のクラス名(スーパー管理者だけ)。空白で区切って入力し、正しいときだけ反映する
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()
const draft = ref((props.node.classes ?? []).join(' '))
const error = computed(() => classesError(parseClasses(draft.value)))
const id = computed(() => `classes-${props.node.id}`)

function commit(): void {
  if (error.value === null) {
    store.updateClasses(props.node.id, parseClasses(draft.value))
  }
}
</script>

<template>
  <section class="mb-3">
    <h3 class="builder-panel-heading">{{ t('追加のクラス名') }}</h3>
    <input
      :id="id"
      v-model="draft"
      type="text"
      class="form-control form-control-sm font-monospace"
      :class="{ 'is-invalid': error }"
      placeholder="my-card highlight"
      :aria-label="t('追加のクラス名')"
      @change="commit"
    >
    <div v-if="error" class="invalid-feedback">{{ error }}</div>
    <div class="form-text">{{ t('空白で区切って 5 個まで。ツールバーの「CSS」やテーマのサイト共通の CSS から、このクラス名で狙えます。') }}</div>
  </section>
</template>
