<script setup lang="ts">
import { onBeforeUnmount, onMounted, provide, watch } from 'vue'
import { createApi } from '../api'
import { t } from '../i18n'
import { builderStoreKey, createBuilderStore } from '../store'
import type { EditorConfig, LayoutBox } from '../types'
import BuilderCanvas from './BuilderCanvas.vue'
import ConversionStage from './ConversionStage.vue'
import { surfaceFrame } from './freeGeometry'
import BuilderToolbar from './BuilderToolbar.vue'
import LeftPanel from './LeftPanel.vue'
import PropertyPanel from './PropertyPanel.vue'
import SectionDialog from './SectionDialog.vue'
import TemplateDialog from './TemplateDialog.vue'
import CssDialog from './CssDialog.vue'
import HelpDialog from './HelpDialog.vue'
import TransferDialog from './TransferDialog.vue'
import VersionDialog from './VersionDialog.vue'

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

// 直接書き換えているブロックとは別のブロックを選んだら(パレットからの追加・貼り付けなども)、書き換えを終える
watch(() => store.state.selectedId, (id) => {
  if (store.state.editingId !== null && store.state.editingId !== id) {
    store.finishInlineEdit()
  }
})

// 保存していない変更があるまま画面を離れようとしたら確かめる
function confirmLeave(event: BeforeUnloadEvent): void {
  if (store.state.dirty) {
    event.preventDefault()
  }
}

function dialogOpen(): boolean {
  return store.state.templatesOpen || store.state.versionsOpen || store.state.transferOpen || store.state.cssOpen || store.state.helpOpen || store.state.sectionInsertIndex !== null
}

// キーボードの操作: Ctrl+S で下書き保存。入力欄の外では Ctrl+Z/Ctrl+Shift+Z(Ctrl+Y)で元に戻す/やり直す、
// Delete/Backspace で選択中のブロックを削除、Esc で親を選ぶ(入力欄の中では、入力欄の元に戻すなどを使う)
function handleKeydown(event: KeyboardEvent): void {
  const target = event.target as HTMLElement | null
  const withModifier = event.ctrlKey || event.metaKey
  const key = event.key.toLowerCase()

  // セクションの追加・テンプレート・版の履歴・書き出しと読み込みの画面を開いているあいだは、Esc で閉じるだけにする(後ろのブロックを消したりしない)
  if (dialogOpen()) {
    if (event.key === 'Escape') {
      store.state.templatesOpen = false
      store.state.versionsOpen = false
      store.state.transferOpen = false
      store.state.cssOpen = false
      store.state.helpOpen = false
      store.state.sectionInsertIndex = null
    }

    return
  }

  if (withModifier && key === 's') {
    event.preventDefault()
    store.save()

    return
  }

  if (target?.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"])')) {
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

  if (event.key.startsWith('Arrow') && !withModifier && nudgeSelected(event)) {
    event.preventDefault()

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

// 矢印キー: 自由配置のブロックを 1px(Shift と一緒なら 10px)動かす。入力欄の中・文字を書き換えている間は動かさない。
// スマートフォンで縦 1 列に並べている面では動かさない(ドラッグで、その端末だけの位置にしてから動かす)。動かしたら true
function nudgeSelected(event: KeyboardEvent): boolean {
  const target = event.target as HTMLElement | null
  const id = store.state.selectedId

  if (!id || store.state.editingId !== null || target?.closest?.('input, textarea, select, [contenteditable]:not([contenteditable="false"])') || !store.isFreeChild(id)) {
    return false
  }

  const device = store.state.device
  const node = store.selectedNode()
  const box = device === 'desktop' ? node?.layout?.desktop : node?.layout?.[device]
  const surface = document.querySelector<HTMLElement>(`[data-node-id="${id}"]`)?.parentElement

  if (!box || !surface) {
    return false
  }

  const step = event.shiftKey ? 10 : 1
  const width = surfaceFrame(surface).width
  const moves: Record<string, Partial<LayoutBox>> = {
    ArrowLeft: { x: box.x - (step / width) * 100 },
    ArrowRight: { x: box.x + (step / width) * 100 },
    ArrowUp: { y: box.y - step },
    ArrowDown: { y: box.y + step },
  }

  if (!(event.key in moves)) {
    return false
  }

  store.updateLayout(id, device, moves[event.key])

  return true
}

// コピー・切り取り・貼り付け(Ctrl+C/Ctrl+X/Ctrl+V): 入力欄の外では、選択中のブロックを(子ごと)システムのクリップボードに入れ、
// クリップボードのブロックを選択中のブロックの近くに貼り付ける(別のタブ・別のページのエディタへも貼り付けられる)。
// 入力欄の中と、Canvas の文字を選んでいるときのコピーは、ブラウザの動きのままにする
function isClipboardForEditor(event: ClipboardEvent): boolean {
  const target = event.target as HTMLElement | null

  return !dialogOpen() && event.clipboardData !== null && !target?.closest?.('input, textarea, select, [contenteditable]:not([contenteditable="false"])')
}

function handleCopy(event: ClipboardEvent, cut: boolean): void {
  const selection = window.getSelection()

  if (!isClipboardForEditor(event) || !store.state.selectedId || (selection && !selection.isCollapsed && selection.toString() !== '')) {
    return
  }

  const text = store.copySelected(cut)

  if (text !== null) {
    event.clipboardData!.setData('text/plain', text)
    event.preventDefault()
  }
}

const handleCopyEvent = (event: ClipboardEvent) => handleCopy(event, false)
const handleCutEvent = (event: ClipboardEvent) => handleCopy(event, true)

function handlePaste(event: ClipboardEvent): void {
  if (!isClipboardForEditor(event) || !store.state.loaded) {
    return
  }

  const text = event.clipboardData!.getData('text/plain')

  if (text !== '') {
    event.preventDefault()
    store.paste(text)
  }
}

// 使い方の画面は、このブラウザで初めてエディタを開いたときだけ自動で開く(覚えておけないブラウザでは開かない)
const HELP_SEEN_KEY = 'biscuit.builder.helpSeen'

function openHelpOnFirstVisit(): void {
  try {
    if (window.localStorage.getItem(HELP_SEEN_KEY) === null) {
      window.localStorage.setItem(HELP_SEEN_KEY, '1')
      store.state.helpOpen = true
    }
  }
  catch {
    // 保存できないブラウザ(プライベートなウィンドウなど)では、ツールバーの「使い方」から開く
  }
}

onMounted(() => {
  store.load().then(() => {
    if (store.state.loaded) {
      openHelpOnFirstVisit()
    }
  })
  window.addEventListener('beforeunload', confirmLeave)
  window.addEventListener('keydown', handleKeydown)
  window.addEventListener('copy', handleCopyEvent)
  window.addEventListener('cut', handleCutEvent)
  window.addEventListener('paste', handlePaste)
})

onBeforeUnmount(() => {
  clearTimeout(autoSaveTimer)
  window.removeEventListener('beforeunload', confirmLeave)
  window.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('copy', handleCopyEvent)
  window.removeEventListener('cut', handleCutEvent)
  window.removeEventListener('paste', handlePaste)
})
</script>

<template>
  <div class="builder-app">
    <BuilderToolbar :back-url="config.backUrl" :back-label="config.backLabel" :can-preview="config.endpoints.previewUrl !== null" />
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
    <SectionDialog v-if="store.state.sectionInsertIndex !== null" />
    <TemplateDialog v-if="store.state.templatesOpen" />
    <VersionDialog v-if="store.state.versionsOpen" />
    <TransferDialog v-if="store.state.transferOpen" />
    <CssDialog v-if="store.state.cssOpen" />
    <HelpDialog v-if="store.state.helpOpen" />
    <!-- 行・カラムの内容(v1)を自由配置に変換するとき、画面の外で描いて測る -->
    <ConversionStage />
  </div>
</template>
