<script setup lang="ts">
import { onBeforeUnmount, onMounted, provide, watch } from 'vue'
import { createApi } from '../api'
import { t } from '../i18n'
import { builderStoreKey, createBuilderStore } from '../store'
import type { EditorConfig } from '../types'
import BuilderCanvas from './BuilderCanvas.vue'
import BuilderToolbar from './BuilderToolbar.vue'
import LeftPanel from './LeftPanel.vue'
import PropertyPanel from './PropertyPanel.vue'
import TemplateDialog from './TemplateDialog.vue'

// ページビルダーのエディタ全体(上にツールバー、左にパレットとコンポーネントツリー、中央に Canvas、右にプロパティ)
const props = defineProps<{
  config: EditorConfig
}>()

const store = createBuilderStore(createApi(props.config))
provide(builderStoreKey, store)

// 内容を変えてから AUTO_SAVE_MILLISECONDS 操作がなければ、下書きを自動で保存する
// (公開は「公開」を押したときだけ。ほかの管理者が先に保存していたら、読み込み直すまで止める)
const AUTO_SAVE_MILLISECONDS = 2000
let autoSaveTimer: ReturnType<typeof setTimeout> | undefined

function scheduleAutoSave(): void {
  clearTimeout(autoSaveTimer)
  autoSaveTimer = setTimeout(async () => {
    if (!store.state.dirty || store.state.conflict) {
      return
    }
    // 保存・公開の途中なら、終わってからやり直す
    if (store.state.busy || store.state.dragging) {
      scheduleAutoSave()

      return
    }
    await store.save(true)
  }, AUTO_SAVE_MILLISECONDS)
}

watch(() => store.state.revision, scheduleAutoSave)

// 保存していない変更があるまま画面を離れようとしたら確かめる
function confirmLeave(event: BeforeUnloadEvent): void {
  if (store.state.dirty) {
    event.preventDefault()
  }
}

// キーボードの操作: Ctrl+S で下書き保存。入力欄の外では Ctrl+Z/Ctrl+Shift+Z(Ctrl+Y)で元に戻す/やり直す、
// Delete/Backspace で選択中のブロックを削除、Esc で親を選ぶ(入力欄の中では、入力欄の元に戻すなどを使う)
function handleKeydown(event: KeyboardEvent): void {
  const target = event.target as HTMLElement | null
  const withModifier = event.ctrlKey || event.metaKey
  const key = event.key.toLowerCase()

  // テンプレートの画面を開いているあいだは、Esc で閉じるだけにする(後ろのブロックを消したりしない)
  if (store.state.templatesOpen) {
    if (event.key === 'Escape') {
      store.state.templatesOpen = false
    }

    return
  }

  if (withModifier && key === 's') {
    event.preventDefault()
    store.save()

    return
  }

  if (target?.closest('input, textarea, select, [contenteditable="true"]')) {
    return
  }

  if (withModifier && (key === 'z' || key === 'y')) {
    event.preventDefault()
    if (key === 'y' || event.shiftKey) {
      store.redo()
    }
    else {
      store.undo()
    }

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
  clearTimeout(autoSaveTimer)
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
      <LeftPanel />
      <BuilderCanvas />
      <PropertyPanel />
    </div>
    <TemplateDialog v-if="store.state.templatesOpen" />
  </div>
</template>
