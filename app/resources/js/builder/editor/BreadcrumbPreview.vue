<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { BuilderNode } from '../types'

// パンくずのブロックの Canvas の見本。編集しているページのパンくず(公開側と同じ組み立て)を、ブロックの見た目で並べる。
// トップページはパンくずがないため、公開側では何も出さない
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()

const SEPARATORS: Record<string, string> = { slash: '/', chevron: '›', arrow: '→' }

const items = computed(() => (props.node.props.showCurrent === false ? store.state.breadcrumbs.slice(0, -1) : store.state.breadcrumbs))
const separator = computed(() => `'${SEPARATORS[String(props.node.props.separator)] ?? '/'}'`)
const alignClass = computed(() => (props.node.props.align === 'center' ? 'justify-content-center' : props.node.props.align === 'end' ? 'justify-content-end' : ''))
</script>

<template>
  <div>
    <div v-if="store.state.breadcrumbs.length === 0" class="builder-preview-placeholder">
      <i class="bi bi-chevron-double-right" /> {{ t('トップページではパンくずを表示しません。') }}
    </div>
    <nav v-else :aria-label="t('パンくず')">
      <ol class="breadcrumb mb-0" :class="alignClass" :style="{ '--bs-breadcrumb-divider': separator }">
        <li v-for="(item, index) in items" :key="index" class="breadcrumb-item" :class="{ active: index === store.state.breadcrumbs.length - 1 }">
          <span :class="{ 'link-secondary text-decoration-underline': item.path && index < store.state.breadcrumbs.length - 1 }">{{ item.label }}</span>
        </li>
      </ol>
    </nav>
  </div>
</template>
