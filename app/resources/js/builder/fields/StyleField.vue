<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import { isValidStyleValue, styleValueFor } from '../styles'
import type { BuilderNode } from '../types'
import { styleLabel, styleOptionLabel } from './styleLabels'

// スタイル 1 つの入力欄。選んでいる端末の値を編集する(タブレット・スマートフォンでは、その端末だけの上書き)。
// 長さ・色・数値は許した形の値だけを反映し、それ以外は入力欄に印を付けて反映しない
const props = defineProps<{
  node: BuilderNode
  name: string
  kind: string | string[] | undefined
}>()

const store = useBuilderStore()
const id = computed(() => `style-${props.node.id}-${props.name}`)
const current = computed(() => styleValueFor(props.node, store.state.device, props.name))
const options = computed(() => (Array.isArray(props.kind) ? props.kind : null))

// 入力中の文字(反映できない値のあいだも入力欄に残す)
const draft = ref(current.value.source === 'own' ? current.value.value ?? '' : '')
const isInvalid = computed(() => draft.value !== '' && !isValidStyleValue(props.kind, draft.value))

const PLACEHOLDERS: Record<string, string> = {
  length: '16px / 1.5rem / 50%',
  number: '1.8',
  color: '#336699',
}
const placeholder = computed(() =>
  current.value.source === 'inherited' ? `${current.value.value}(${t('引き継ぎ')})` : PLACEHOLDERS[String(props.kind)] ?? '',
)

function commit(value: string): void {
  draft.value = value

  if (value === '') {
    store.updateStyle(props.node.id, props.name, null)
  }
  else if (isValidStyleValue(props.kind, value)) {
    store.updateStyle(props.node.id, props.name, value)
  }
}
</script>

<template>
  <div class="mb-2">
    <label :for="id" class="form-label small mb-1">{{ styleLabel(name) }}</label>

    <select
      v-if="options"
      :id="id"
      class="form-select form-select-sm form-select-auto"
      :value="current.source === 'own' ? current.value : ''"
      @change="commit(($event.target as HTMLSelectElement).value)"
    >
      <option value="">
        {{ current.source === 'inherited' ? `${t('引き継ぎ')}: ${styleOptionLabel(name, current.value ?? '')}` : t('指定しない') }}
      </option>
      <option v-for="option in options" :key="option" :value="option">{{ styleOptionLabel(name, option) }}</option>
    </select>

    <div v-else class="input-group input-group-sm">
      <input
        v-if="kind === 'color'"
        type="color"
        class="form-control form-control-color"
        :value="isValidStyleValue('color', draft) && draft.length === 7 ? draft : current.value?.slice(0, 7) ?? '#000000'"
        :aria-label="styleLabel(name)"
        @input="commit(($event.target as HTMLInputElement).value)"
      >
      <input
        :id="id"
        type="text"
        class="form-control"
        :class="{ 'is-invalid': isInvalid }"
        :placeholder="placeholder"
        :value="draft"
        @input="commit(($event.target as HTMLInputElement).value.trim())"
      >
      <button v-if="draft !== ''" type="button" class="btn btn-outline-secondary" :title="t('指定を外す')" @click="commit('')">
        <i class="bi bi-x-lg" />
      </button>
    </div>
    <div v-if="isInvalid" class="invalid-feedback d-block">
      {{ kind === 'color' ? t('#336699 のような色を入力してください。') : kind === 'number' ? t('1.8 のような数値を入力してください。') : t('16px・1.5rem・50% のような長さを入力してください。') }}
    </div>
  </div>
</template>
