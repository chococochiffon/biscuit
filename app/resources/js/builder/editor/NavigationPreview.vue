<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { BuilderNode, NavigationItem } from '../types'

// ナビゲーションのブロックの Canvas の見本。項目の出どころ(サイトのナビメニュー・固定ページ)どおりの項目を biscuit から取ってきて、
// 公開側(chococo)と同じ Bootstrap の nav で並べる。Canvas ではリンクは開かない
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()
const items = ref<NavigationItem[] | null>(null)

watch(() => props.node.props.source, async (source) => {
  items.value = await store.navigationPreview(source)
}, { immediate: true })

const navClass = computed(() => {
  const variant = props.node.props.variant
  const align = props.node.props.align

  return [
    variant === 'pills' ? 'nav-pills' : variant === 'underline' ? 'nav-underline' : '',
    props.node.props.direction === 'vertical' ? 'flex-column' : '',
    align === 'center' ? 'justify-content-center' : align === 'end' ? 'justify-content-end' : '',
  ]
})
</script>

<template>
  <div>
    <div v-if="items === null" class="builder-preview-placeholder">{{ t('読み込んでいます...') }}</div>
    <div v-else-if="items.length === 0" class="builder-preview-placeholder">
      <i class="bi bi-menu-button-wide" /> {{ t('表示する項目がありません。') }}
    </div>
    <ul v-else class="nav" :class="navClass">
      <li v-for="(item, index) in items" :key="index" class="nav-item">
        <span class="nav-link" :class="{ active: index === 0 && node.props.variant !== 'links' }">{{ item.label }}</span>
      </li>
    </ul>
  </div>
</template>
