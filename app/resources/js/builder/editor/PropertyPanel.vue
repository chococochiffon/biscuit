<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '../i18n'
import { ancestorsOf } from '../nodes'
import { useBuilderStore } from '../store'
import PropField from '../fields/PropField.vue'
import StyleField from '../fields/StyleField.vue'
import VisibilityFields from '../fields/VisibilityFields.vue'
import { styleGroups } from '../fields/styleLabels'

// 右のプロパティ: 選択中のブロックの内容(props)・スタイル・表示条件を、ブロックの定義から作った入力欄で編集する。
// スタイルは選んでいる端末の値を編集する(タブレット・スマートフォンは、その端末だけの上書き)。
// 上には選択中のブロックまでの階層を出し、クリックで親のブロックを選べる
const store = useBuilderStore()

const node = computed(() => store.selectedNode())
const definition = computed(() => (node.value ? store.definition(node.value.type) : null))
const ancestors = computed(() => (node.value ? ancestorsOf(store.state.content, node.value.id) : []))
const tab = ref<'content' | 'style' | 'visibility'>('content')

// ブロックで使えるスタイルを、まとまりごとに並べる
const groups = computed(() =>
  styleGroups()
    .map(group => ({ ...group, styles: group.styles.filter(name => definition.value?.styles.includes(name)) }))
    .filter(group => group.styles.length > 0),
)

const DEVICE_LABELS = { desktop: t('デスクトップ'), tablet: t('タブレット'), mobile: t('スマートフォン') }

// Undo/Redo で内容を差し替えたら入力欄を作り直す(入力欄が持つ入力中の値を捨てて、差し替えた値を出す)
const fieldKey = (name: string) => `${node.value?.id}:${name}:${store.state.device}:${store.state.restoreCount}`

const errors = computed(() => (node.value ? store.state.errors[`nodes.${node.value.id}`] ?? [] : store.state.errors.content ?? []))
</script>

<template>
  <aside class="builder-properties">
    <div v-if="node && definition">
      <nav class="builder-properties-path small mb-2" :aria-label="t('階層')">
        <button type="button" class="btn btn-link btn-sm p-0" @click="store.select(null)">{{ t('ページ') }}</button>
        <template v-for="ancestor in ancestors" :key="ancestor.id">
          <span class="text-secondary mx-1">›</span>
          <button type="button" class="btn btn-link btn-sm p-0" @click="store.select(ancestor.id)">
            {{ store.definition(ancestor.type)?.label ?? ancestor.type }}
          </button>
        </template>
        <span class="text-secondary mx-1">›</span>
        <span class="fw-semibold">{{ definition.label }}</span>
      </nav>

      <div v-for="(error, index) in errors" :key="index" class="alert alert-danger small py-2">{{ error }}</div>

      <ul class="nav nav-tabs nav-fill small mb-3">
        <li class="nav-item">
          <button type="button" class="nav-link" :class="{ active: tab === 'content' }" @click="tab = 'content'">{{ t('内容') }}</button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" :class="{ active: tab === 'style' }" @click="tab = 'style'">{{ t('スタイル') }}</button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" :class="{ active: tab === 'visibility' }" @click="tab = 'visibility'">
            {{ t('表示') }}<i v-if="node.visibility" class="bi bi-dot" />
          </button>
        </li>
      </ul>

      <template v-if="tab === 'content'">
        <PropField
          v-for="(prop, name) in definition.props"
          :key="fieldKey(String(name))"
          :node="node"
          :name="String(name)"
          :prop="prop"
        />
        <p v-if="Object.keys(definition.props).length === 0" class="small text-secondary">
          {{ t('このブロックには入力する内容がありません。') }}
        </p>
      </template>

      <VisibilityFields v-else-if="tab === 'visibility'" :key="fieldKey('visibility')" :node="node" />

      <template v-else>
        <p class="small text-secondary">
          <i class="bi" :class="store.state.device === 'desktop' ? 'bi-display' : store.state.device === 'tablet' ? 'bi-tablet' : 'bi-phone'" />
          {{ t(':device の見た目を編集しています。', { device: DEVICE_LABELS[store.state.device] }) }}
          <template v-if="store.state.device !== 'desktop'">
            {{ t('空欄の項目は、大きい画面の値を引き継ぎます。') }}
          </template>
        </p>
        <section v-for="group in groups" :key="group.label" class="mb-3">
          <h3 class="builder-panel-heading">{{ group.label }}</h3>
          <StyleField
            v-for="name in group.styles"
            :key="fieldKey(`style:${name}`)"
            :node="node"
            :name="name"
            :kind="store.state.registry.styles[name]"
          />
        </section>
        <p v-if="groups.length === 0" class="small text-secondary">
          {{ t('このブロックには変えられるスタイルがありません。') }}
        </p>
      </template>
    </div>
    <div v-else>
      <div v-for="(error, index) in errors" :key="index" class="alert alert-danger small py-2">{{ error }}</div>
      <p class="small text-secondary">{{ t('ブロックを選ぶと、ここで内容を編集できます。') }}</p>
    </div>
  </aside>
</template>
