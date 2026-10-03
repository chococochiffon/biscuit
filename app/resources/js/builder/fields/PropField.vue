<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { BuilderNode, PropDefinition } from '../types'
import ImageField from './ImageField.vue'
import RichTextField from './RichTextField.vue'
import { videoEmbedUrl } from '../video'

// ブロックの内容(props)の項目 1 つの入力欄。項目の型(BlockRegistry の type)に合わせて入力欄を選ぶ。
// override を渡すと、独自コンポーネントの差し替えた値の入力欄になる(値の読み書きを override に任せ、空は「部品の値のまま」。
// 選択肢・オン/オフにも「部品の値のまま」を出し、文字の入力欄には部品の値を薄く出す)
const props = defineProps<{
  node: BuilderNode
  name: string
  prop: PropDefinition
  override?: { value: unknown, fallback: unknown, set: (value: unknown) => void }
}>()

const store = useBuilderStore()

// コンポーネント(グローバル・独自)の選択肢は、入力欄を出したときに読み込み、項目の種類のものだけを出す
const COMPONENT_SOURCES: Record<string, 'global' | 'custom'> = { 'global-components': 'global', 'custom-components': 'custom' }
const componentKind = computed(() => COMPONENT_SOURCES[props.prop.source ?? ''] ?? null)
const componentOptions = computed(() => (store.state.components ?? []).filter(component => component.kind === componentKind.value))

if (componentKind.value) {
  store.loadComponents()
}

const id = computed(() => `prop-${props.node.id}-${props.name}`)
const value = computed(() => (props.override ? props.override.value : props.node.props[props.name]))
const fallbackText = computed(() => (props.override && typeof props.override.fallback === 'string' ? props.override.fallback : ''))

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
  'slash': t('スラッシュ( / )'),
  'chevron': t('山かっこ( › )'),
  'arrow': t('矢印( → )'),
  '16x9': '16:9',
  '4x3': '4:3',
  '1x1': '1:1',
  '21x9': '21:9',
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
const isVideoInvalid = computed(() => props.prop.type === 'video' && typeof value.value === 'string' && videoEmbedUrl(value.value) === null)

function update(next: unknown): void {
  if (props.override) {
    props.override.set(next)

    return
  }

  store.updateProp(props.node.id, props.name, next)
}

function updateInt(raw: string): void {
  update(raw === '' ? null : Number(raw))
}
</script>

<template>
  <div class="mb-3">
    <template v-if="prop.type === 'bool' && override">
      <label :for="id" class="form-label small fw-semibold mb-1">{{ prop.label }}</label>
      <select
        :id="id"
        class="form-select form-select-sm form-select-auto"
        :value="value === true ? 'true' : value === false ? 'false' : ''"
        @change="update(({ true: true, false: false } as Record<string, boolean>)[($event.target as HTMLSelectElement).value] ?? null)"
      >
        <option value="">{{ t('部品の値のまま') }}</option>
        <option value="true">{{ t('オン') }}</option>
        <option value="false">{{ t('オフ') }}</option>
      </select>
    </template>

    <div v-else-if="prop.type === 'bool'" class="form-check form-switch">
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
      :placeholder="fallbackText"
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
      v-else-if="prop.source === 'gallery-categories'"
      :id="id"
      class="form-select form-select-sm form-select-auto"
      :value="value ?? ''"
      @change="updateInt(($event.target as HTMLSelectElement).value)"
    >
      <option value="">{{ t('すべて') }}</option>
      <option v-for="category in store.state.galleryCategories" :key="category.id" :value="category.id">{{ category.name }}</option>
      <option v-if="typeof value === 'number' && !store.state.galleryCategories.some(category => category.id === value)" :value="value">
        {{ t('(削除された分類)') }}
      </option>
    </select>

    <select
      v-else-if="componentKind"
      :id="id"
      class="form-select form-select-sm form-select-auto"
      :value="value ?? ''"
      @focus="store.loadComponents()"
      @change="updateInt(($event.target as HTMLSelectElement).value)"
    >
      <option value="">{{ t('選んでください') }}</option>
      <option v-for="component in componentOptions" :key="component.id" :value="component.id">
        {{ component.published ? component.name : `${component.name} ${t('(未公開)')}` }}
      </option>
      <option v-if="typeof value === 'number' && store.state.components !== null && !componentOptions.some(component => component.id === value)" :value="value">
        {{ t('(削除されたコンポーネント)') }}
      </option>
    </select>

    <select
      v-else-if="prop.type === 'int' && intOptions"
      :id="id"
      class="form-select form-select-sm form-select-auto"
      :value="value ?? ''"
      @change="updateInt(($event.target as HTMLSelectElement).value)"
    >
      <option v-if="override" value="">{{ t('部品の値のまま') }}</option>
      <option v-else-if="prop.nullable" value="">{{ t('指定しない') }}</option>
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
      :value="value ?? ''"
      @change="update(($event.target as HTMLSelectElement).value || null)"
    >
      <option v-if="override" value="">{{ t('部品の値のまま') }}</option>
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

    <template v-else-if="prop.type === 'video'">
      <input
        :id="id"
        type="text"
        class="form-control form-control-sm"
        :class="{ 'is-invalid': isVideoInvalid }"
        placeholder="https://www.youtube.com/watch?v=…"
        :value="value ?? ''"
        @input="update(($event.target as HTMLInputElement).value.trim() || null)"
      >
      <div v-if="isVideoInvalid" class="invalid-feedback">
        {{ t('YouTube か Vimeo の動画の URL を入力してください。') }}
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
