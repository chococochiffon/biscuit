<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '../i18n'
import { ancestorsOf } from '../nodes'
import { availableSectionPresets } from '../sections'
import { useBuilderStore } from '../store'
import type { BuilderNode } from '../types'
import CanvasNode from './CanvasNode.vue'

// ブロックの子(またはページの直下)の並び。ドラッグ中のものを置ける並びなら、マウスの位置から入れる位置を決めて線で示す。
// 置けない並び(例: カラムの中のセクション)では受けずに外側の並びへ任せる(イベントを親へ伝える)。
// 行(row)の中は横に並ぶため、横向き(horizontal)で位置を決める。
// ページの直下では、マウスを乗せたセクションの下の境目に「+ セクションを追加」を出す(ひな形を選ぶ画面を開く)
const props = withDefaults(defineProps<{
  parentId: string | null
  children: BuilderNode[]
  tag?: string
  direction?: 'vertical' | 'horizontal'
  isRoot?: boolean
  emptyLabel?: string
}>(), {
  tag: 'div',
  direction: 'vertical',
  isRoot: false,
  emptyLabel: undefined,
})

const store = useBuilderStore()
const listElement = ref<HTMLElement | null>(null)
const indicatorStyle = ref<Record<string, string>>({})

// コンポーネントツリーの上でドラッグしているあいだは、Canvas には線を出さない(位置を計算していないため)
// マウスを乗せているブロックを含む、ページの直下のブロック(その下の境目に「+ セクションを追加」を出す)
const canAddSection = computed(() => props.isRoot && availableSectionPresets(store.state.registry, store.state.content.version).length > 0)
const hoveredRootId = computed(() => {
  const id = store.state.hoveredId

  if (!canAddSection.value || id === null || store.state.dragging !== null || store.state.resizing !== null) {
    return null
  }

  return ancestorsOf(store.state.content, id)[0]?.id ?? id
})

const isTarget = computed(() => store.state.dragging !== null && store.state.dropTarget?.from !== 'tree' && store.state.dropTarget?.parentId === props.parentId)

function childElements(): HTMLElement[] {
  return Array.from(listElement.value?.children ?? []).filter(
    (element): element is HTMLElement => element instanceof HTMLElement && element.dataset.nodeId !== undefined,
  )
}

/**
 * マウスの位置から、何番目に入れるかを決める。
 */
function dropIndex(event: DragEvent, elements: HTMLElement[]): number {
  for (const [index, element] of elements.entries()) {
    const rect = element.getBoundingClientRect()

    if (props.direction === 'vertical') {
      if (event.clientY < rect.top + rect.height / 2) {
        return index
      }
    }
    else if (event.clientY < rect.top || (event.clientY <= rect.bottom && event.clientX < rect.left + rect.width / 2)) {
      return index
    }
  }

  return elements.length
}

/**
 * 入れる位置を示す線の位置(並びの要素からの相対)。空の並びは全体を囲む。
 */
function indicatorFor(index: number, elements: HTMLElement[]): Record<string, string> {
  const list = listElement.value?.getBoundingClientRect()

  if (!list || elements.length === 0) {
    return { inset: '0' }
  }

  const before = index < elements.length
  const rect = elements[before ? index : elements.length - 1].getBoundingClientRect()
  const px = (value: number) => `${Math.round(value)}px`

  return props.direction === 'vertical'
    ? { top: px((before ? rect.top : rect.bottom) - list.top - 2), left: px(rect.left - list.left), width: px(rect.width), height: '4px' }
    : { left: px((before ? rect.left : rect.right) - list.left - 2), top: px(rect.top - list.top), height: px(rect.height), width: '4px' }
}

function onDragOver(event: DragEvent): void {
  if (!store.state.dragging) {
    return
  }

  if (!store.canDropInto(props.parentId)) {
    // どの並びも受けなかったら、入れる位置を消す
    if (props.isRoot) {
      store.state.dropTarget = null
    }

    return
  }

  event.preventDefault()
  event.stopPropagation()

  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = store.state.dragging.kind === 'new' ? 'copy' : 'move'
  }

  const elements = childElements()
  const index = dropIndex(event, elements)
  store.state.dropTarget = { parentId: props.parentId, index, from: 'canvas' }
  indicatorStyle.value = indicatorFor(index, elements)
}

function onDrop(event: DragEvent): void {
  if (store.state.dropTarget?.parentId !== props.parentId || !store.canDropInto(props.parentId)) {
    return
  }

  event.preventDefault()
  event.stopPropagation()
  store.drop()
}
</script>

<template>
  <component
    :is="tag"
    ref="listElement"
    class="builder-drop-list"
    :class="{ 'is-drop-target': isTarget, 'is-empty': children.length === 0 }"
    @dragover="onDragOver"
    @drop="onDrop"
  >
    <template v-for="(child, index) in children" :key="child.id">
      <CanvasNode :node="child" />
      <div v-if="hoveredRootId === child.id" class="builder-section-inserter">
        <button type="button" class="builder-section-inserter-button" @click.stop="store.state.sectionInsertIndex = index + 1">
          <i class="bi bi-plus-lg" /> {{ t('セクションを追加') }}
        </button>
      </div>
    </template>
    <div v-if="children.length === 0" class="builder-drop-empty" :class="{ 'col-12': direction === 'horizontal' }">
      {{ emptyLabel ?? t('ここにブロックをドラッグ') }}
    </div>
    <div v-if="isTarget" class="builder-drop-indicator" :class="{ 'is-empty': children.length === 0 }" :style="indicatorStyle" />
  </component>
</template>
