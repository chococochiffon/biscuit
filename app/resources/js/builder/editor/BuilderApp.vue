<script setup lang="ts">
import { onBeforeUnmount, onMounted, provide } from 'vue'
import { createApi } from '../api'
import { t } from '../i18n'
import { builderStoreKey, createBuilderStore } from '../store'
import type { EditorConfig } from '../types'
import BuilderCanvas from './BuilderCanvas.vue'
import BuilderPalette from './BuilderPalette.vue'
import BuilderToolbar from './BuilderToolbar.vue'
import PropertyPanel from './PropertyPanel.vue'

// ページビルダーのエディタ全体(上にツールバー、左にパレット、中央に Canvas、右にプロパティ)
const props = defineProps<{
  config: EditorConfig
}>()

const store = createBuilderStore(createApi(props.config))
provide(builderStoreKey, store)

// 保存していない変更があるまま画面を離れようとしたら確かめる
function confirmLeave(event: BeforeUnloadEvent): void {
  if (store.state.dirty) {
    event.preventDefault()
  }
}

// 入力欄の外での操作: Delete/Backspace で選択中のブロックを削除、Esc で親を選ぶ
function handleKeydown(event: KeyboardEvent): void {
  const target = event.target as HTMLElement | null

  if (target?.closest('input, textarea, select, [contenteditable="true"]')) {
    return
  }

  if ((event.key === 'Delete' || event.key === 'Backspace') && store.state.selectedId) {
    event.preventDefault()
    store.remove(store.state.selectedId)
  }
  else if (event.key === 'Escape') {
    store.selectParent()
  }
}

onMounted(() => {
  store.load()
  window.addEventListener('beforeunload', confirmLeave)
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', confirmLeave)
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div class="builder-app">
    <BuilderToolbar :back-url="config.backUrl" />
    <div v-if="store.state.loadError" class="p-4 text-danger">
      {{ t('ページビルダーを読み込めませんでした。画面を読み込み直してください。') }}
    </div>
    <div v-else-if="!store.state.loaded" class="p-4 text-secondary">
      {{ t('読み込んでいます...') }}
    </div>
    <div v-else class="builder-body">
      <BuilderPalette />
      <BuilderCanvas />
      <PropertyPanel />
    </div>
  </div>
</template>
