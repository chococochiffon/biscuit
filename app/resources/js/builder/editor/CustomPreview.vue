<script setup lang="ts">
import { computed } from 'vue'
import { applyOverrides } from '../custom'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { BuilderNode } from '../types'
import BlockPreview from './BlockPreview.vue'

// 独自コンポーネントのブロックの Canvas の見本。部品の公開中の内容に差し替えた値を当てはめて、選択・ドラッグを受けずに描く
// (部品の中身は部品のエディタで編集し、差し替えた値は右のプロパティで入力する。クリックするとこのブロックが選ばれる)
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()
store.loadComponents()

const componentId = computed(() => (typeof props.node.props.component === 'number' ? props.node.props.component : null))
const component = computed(() => store.state.components?.find(item => item.id === componentId.value && item.kind === 'custom') ?? null)
const children = computed(() =>
  component.value?.content ? applyOverrides(component.value.content.children, (props.node.props.values as Record<string, unknown> | undefined) ?? {}) : [],
)
</script>

<template>
  <div class="builder-custom-preview">
    <div v-if="componentId === null" class="builder-preview-placeholder">
      <i class="bi bi-boxes" /> {{ t('使う部品を選んでください') }}
    </div>
    <div v-else-if="store.state.components === null" class="builder-preview-placeholder">{{ t('読み込んでいます...') }}</div>
    <div v-else-if="component === null" class="builder-preview-placeholder">
      <i class="bi bi-boxes" /> {{ t('(削除されたコンポーネント)') }}
    </div>
    <div v-else-if="children.length === 0" class="builder-preview-placeholder">
      <i class="bi bi-boxes" /> {{ t('「:name」はまだ公開されていません。', { name: component.name }) }}
    </div>
    <template v-else>
      <BlockPreview v-for="child in children" :key="child.id" :node="child" :class="child.classes" readonly />
    </template>
  </div>
</template>
