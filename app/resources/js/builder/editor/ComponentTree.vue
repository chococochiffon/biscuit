<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { t } from '../i18n'
import { ancestorsOf } from '../nodes'
import { useBuilderStore } from '../store'
import { flattenTree, resolveTreeDrop, summaryOf, type TreeDropPosition, type TreeRow } from '../tree'
import type { BuilderNode } from '../types'

// コンポーネントツリー: ページの構造を入れ子の一覧で見せる。クリックで選択、ドラッグで並び替え・入れ子の移動
// (パレットからのドラッグも受ける)、複製・削除ができる。子のあるブロックは畳める。
// Canvas で選んだブロックは、畳んでいる親を開いてツリーの中に見えるようにする
const store = useBuilderStore()

const collapsed = ref(new Set<string>())
const rows = computed(() => flattenTree(store.state.content.children, collapsed.value))

// 入れる位置の印(rowId が null ならページの末尾)
const indicator = ref<{ rowId: string | null, position: TreeDropPosition } | null>(null)

function toggle(id: string): void {
  const next = new Set(collapsed.value)

  if (!next.delete(id)) {
    next.add(id)
  }

  collapsed.value = next
}

function label(node: BuilderNode): string {
  return store.definition(node.type)?.label ?? node.type
}

watch(() => store.state.selectedId, async (id) => {
  if (!id) {
    return
  }

  const ancestors = ancestorsOf(store.state.content, id)

  if (ancestors.some(ancestor => collapsed.value.has(ancestor.id))) {
    const next = new Set(collapsed.value)
    ancestors.forEach(ancestor => next.delete(ancestor.id))
    collapsed.value = next
  }

  await nextTick()
  document.querySelector(`[data-tree-id="${id}"]`)?.scrollIntoView({ block: 'nearest' })
})

// ドラッグが終わったら(どこに落としても)印を消す
watch(() => store.state.dragging, (dragging) => {
  if (!dragging) {
    indicator.value = null
  }
})

function startDrag(event: DragEvent, node: BuilderNode): void {
  event.dataTransfer?.setData('text/plain', node.id)
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
  }
  store.state.dragging = { kind: 'move', id: node.id, type: node.type }
  store.select(node.id)
}

function clearTarget(): void {
  indicator.value = null
  if (store.state.dropTarget?.from === 'tree') {
    store.state.dropTarget = null
  }
}

function onRowDragOver(event: DragEvent, row: TreeRow): void {
  if (!store.state.dragging) {
    return
  }

  event.stopPropagation()
  const rect = (event.currentTarget as HTMLElement).getBoundingClientRect()
  const drop = resolveTreeDrop(row, (event.clientY - rect.top) / rect.height, parentId => store.canDropInto(parentId))

  if (!drop) {
    clearTarget()

    return
  }

  event.preventDefault()
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = store.state.dragging.kind === 'new' ? 'copy' : 'move'
  }
  store.state.dropTarget = { parentId: drop.parentId, index: drop.index, from: 'tree' }
  indicator.value = { rowId: row.node.id, position: drop.position }
}

// 行のない所(ツリーの下の余白)は、ページの末尾
function onListDragOver(event: DragEvent): void {
  if (!store.state.dragging || !store.canDropInto(null)) {
    clearTarget()

    return
  }

  event.preventDefault()
  store.state.dropTarget = { parentId: null, index: store.state.content.children.length, from: 'tree' }
  indicator.value = { rowId: null, position: 'after' }
}

function onDrop(event: DragEvent): void {
  if (store.state.dropTarget?.from === 'tree') {
    event.preventDefault()
    store.drop()
  }

  indicator.value = null
}
</script>

<template>
  <div class="builder-tree" @dragover="onListDragOver" @drop="onDrop">
    <p v-if="rows.length === 0" class="small text-secondary">{{ t('まだブロックがありません。') }}</p>
    <div
      v-for="row in rows"
      :key="row.node.id"
      class="builder-tree-row"
      :class="{
        'is-selected': store.state.selectedId === row.node.id,
        'is-hovered': store.state.hoveredId === row.node.id,
        'has-error': `nodes.${row.node.id}` in store.state.errors,
        [`is-drop-${indicator?.position}`]: indicator?.rowId === row.node.id,
      }"
      :style="{ paddingLeft: `${row.depth * 14 + 4}px` }"
      :data-tree-id="row.node.id"
      draggable="true"
      @dragstart="startDrag($event, row.node)"
      @dragend="store.endDrag()"
      @dragover="onRowDragOver($event, row)"
      @click="store.select(row.node.id)"
      @mouseenter="store.state.hoveredId = row.node.id"
      @mouseleave="store.state.hoveredId = null"
    >
      <button
        v-if="row.hasChildren"
        type="button"
        class="builder-tree-toggle"
        :aria-label="collapsed.has(row.node.id) ? t('開く') : t('畳む')"
        :aria-expanded="!collapsed.has(row.node.id)"
        @click.stop="toggle(row.node.id)"
      >
        <i class="bi" :class="collapsed.has(row.node.id) ? 'bi-chevron-right' : 'bi-chevron-down'" />
      </button>
      <span v-else class="builder-tree-toggle" />
      <i class="bi builder-tree-icon" :class="`bi-${store.definition(row.node.type)?.icon ?? 'square'}`" />
      <span class="builder-tree-label">{{ label(row.node) }}</span>
      <span class="builder-tree-summary">{{ summaryOf(row.node) }}</span>
      <span class="builder-tree-actions">
        <button type="button" class="builder-tree-action" :title="t('複製')" @click.stop="store.duplicate(row.node.id)">
          <i class="bi bi-copy" />
        </button>
        <button type="button" class="builder-tree-action text-danger" :title="t('削除')" @click.stop="store.remove(row.node.id)">
          <i class="bi bi-trash" />
        </button>
      </span>
    </div>
    <div class="builder-tree-end" :class="{ 'is-drop-after': indicator?.rowId === null }" />
  </div>
</template>
