<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { BuilderNode, PropDefinition } from '../types'

// 独自コンポーネントのエディタで、項目を「使うたびに変えられる項目」にする・その名前(ページで入力するときの項目名)を決める
const props = defineProps<{
  node: BuilderNode
  name: string
  prop: PropDefinition
}>()

const store = useBuilderStore()
const id = computed(() => `expose-${props.node.id}-${props.name}`)
const label = computed(() => props.node.exposed?.[props.name] ?? null)

function toggle(checked: boolean): void {
  store.updateExposed(props.node.id, props.name, checked ? props.prop.label : null)
}

function rename(value: string): void {
  if (value.trim() !== '') {
    store.updateExposed(props.node.id, props.name, value.trim().slice(0, 50))
  }
}
</script>

<template>
  <div class="builder-expose-field">
    <div class="form-check form-check-sm small">
      <input :id="id" type="checkbox" class="form-check-input" :checked="label !== null" @change="toggle(($event.target as HTMLInputElement).checked)">
      <label :for="id" class="form-check-label text-secondary">{{ t('使うたびに変えられる項目にする') }}</label>
    </div>
    <input
      v-if="label !== null"
      type="text"
      class="form-control form-control-sm mt-1"
      maxlength="50"
      :aria-label="t('ページで入力するときの項目名')"
      :placeholder="t('ページで入力するときの項目名')"
      :value="label"
      @change="rename(($event.target as HTMLInputElement).value)"
    >
  </div>
</template>
