<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { BuilderNode } from '../types'
import BlockPreview from './BlockPreview.vue'

// グローバルコンポーネントのブロックの Canvas の見本。選んだコンポーネントの公開中の内容を、選択・ドラッグを受けずに描く
// (中身はコンポーネントのエディタで編集する。クリックするとこのブロックが選ばれる)。
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()
store.loadComponents()

const componentId = computed(() => (typeof props.node.props.component === 'number' ? props.node.props.component : null))
const component = computed(() => store.state.components?.find(item => item.id === componentId.value) ?? null)
</script>

<template>
  <div class="builder-global-preview">
    <div v-if="componentId === null" class="builder-preview-placeholder">
      <i class="bi bi-puzzle" /> {{ t('使うコンポーネントを選んでください') }}
    </div>
    <div v-else-if="store.state.components === null" class="builder-preview-placeholder">{{ t('読み込んでいます...') }}</div>
    <div v-else-if="component === null" class="builder-preview-placeholder">
      <i class="bi bi-puzzle" /> {{ t('(削除されたコンポーネント)') }}
    </div>
    <div v-else-if="component.content === null || component.content.children.length === 0" class="builder-preview-placeholder">
      <i class="bi bi-puzzle" /> {{ t('「:name」はまだ公開されていません。', { name: component.name }) }}
    </div>
    <template v-else>
      <BlockPreview v-for="child in component.content.children" :key="child.id" :node="child" :class="child.classes" readonly />
    </template>
  </div>
</template>
