<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { BuilderNode, PropDefinition } from '../types'
import ImageField from './ImageField.vue'
import RichTextField from './RichTextField.vue'

// ブロックの内容(props)の項目 1 つの入力欄。項目の型(BlockRegistry の type)に合わせて入力欄を選ぶ
const props = defineProps<{
  node: BuilderNode
  name: string
  prop: PropDefinition
}>()

const store = useBuilderStore()
const id = computed(() => `prop-${props.node.id}-${props.name}`)
const value = computed(() => props.node.props[props.name])

// 選択肢の表示名(値そのものを出すと分かりにくいもの)
const OPTION_LABELS: Record<string, string> = {
  _self: t('同じタブで開く'),
  _blank: t('新しいタブで開く'),
  'primary': t('塗りつぶし(メイン)'),
  'secondary': t('塗りつぶし(サブ)'),
  'outline-primary': t('枠線(メイン)'),
  'outline-secondary': t('枠線(サブ)'),
  'link': t('リンク'),
  'newest': t('新しい順'),
  'oldest': t('古い順'),
  'card': t('カード'),
  'list': t('リスト'),
  'site': t('サイトのナビメニューと同じ'),
  'pages': t('固定ページ(リンクリストに表示するもの)'),
  'horizontal': t('横に並べる'),
  'vertical': t('縦に並べる'),
  'links': t('リンク'),
  'pills': t('ピル'),
  'underline': t('下線'),
  'start': t('左揃え'),
  'center': t('中央揃え'),
  'end': t('右揃え'),
}

// 範囲の狭い整数(見出しのレベル・カラムの幅・余白の段階)は選択肢にする
const intOptions = computed(() => {
  const { min = 0, max = 0 } = props.prop

  return max - min <= 12 ? Array.from({ length: max - min + 1 }, (_, index) => min + index) : null
})

// リンク先として受け付ける形(biscuit の BuilderValidator と同じ)
const URL_PATTERN = /^(?:https?:\/\/[^\s\\]+|mailto:[^\s\\]+|tel:[0-9+\-() ]+|\/(?!\/)[^\s\\]*|#[^\s\\]*)$/i
const isUrlInvalid = computed(() => props.prop.type === 'url' && typeof value.value === 'string' && !URL_PATTERN.test(value.value))

function update(next: unknown): void {
  store.updateProp(props.node.id, props.name, next)
}

function updateInt(raw: string): void {
  update(raw === '' ? null : Number(raw))
}
</script>

<template>
  <div class="mb-3">
    <div v-if="prop.type === 'bool'" class="form-check form-switch">
      <input
        :id="id"
        type="checkbox"
        class="form-check-input"
        role="switch"
        :checked="value === true"
        @change="update(($event.target as HTMLInputElement).checked)"
      >
      <label :for="id" class="form-check-label small fw-semibold">{{ prop.label }}</label>
    </div>

    <label v-else :for="id" class="form-label small fw-semibold mb-1">{{ prop.label }}</label>

    <input
      v-if="prop.type === 'string'"
      :id="id"
      type="text"
      class="form-control form-control-sm"
      :maxlength="prop.max"
      :value="value ?? ''"
      @input="update(($event.target as HTMLInputElement).value)"
    >

    <RichTextField
      v-else-if="prop.type === 'richtext'"
      :id="id"
      :model-value="typeof value === 'string' ? value : ''"
      @update:model-value="update"
    />

    <select
      v-else-if="prop.type === 'int' && intOptions"
      :id="id"
      class="form-select form-select-sm form-select-auto"
      :value="value ?? ''"
      @change="updateInt(($event.target as HTMLSelectElement).value)"
    >
      <option v-if="prop.nullable" value="">{{ t('指定しない') }}</option>
      <option v-for="option in intOptions" :key="option" :value="option">
        {{ name === 'level' ? `H${option}` : option }}
      </option>
    </select>

    <input
      v-else-if="prop.type === 'int'"
      :id="id"
      type="number"
      class="form-control form-control-sm"
      :min="prop.min"
      :max="prop.max"
      :value="value ?? ''"
      @change="updateInt(($event.target as HTMLInputElement).value)"
    >

    <select
      v-else-if="prop.type === 'enum'"
      :id="id"
      class="form-select form-select-sm form-select-auto"
      :value="value"
      @change="update(($event.target as HTMLSelectElement).value)"
    >
      <option v-for="option in prop.options" :key="option" :value="option">{{ OPTION_LABELS[option] ?? option }}</option>
    </select>

    <template v-else-if="prop.type === 'url'">
      <input
        :id="id"
        type="text"
        class="form-control form-control-sm"
        :class="{ 'is-invalid': isUrlInvalid }"
        placeholder="https://… / /about / #section"
        :value="value ?? ''"
        @input="update(($event.target as HTMLInputElement).value || null)"
      >
      <div v-if="isUrlInvalid" class="invalid-feedback">
        {{ t('https:// などで始まる URL か、/ で始まるサイト内のパスを入力してください。') }}
      </div>
    </template>

    <ImageField
      v-else-if="prop.type === 'image'"
      :id="id"
      :model-value="typeof value === 'string' ? value : null"
      @update:model-value="update"
    />
  </div>
</template>
