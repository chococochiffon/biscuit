<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '../i18n'
import { defaultSize, deviceBox, hasDeviceLayout, isStacked, layoutStyle } from '../layout'
import { useBuilderStore } from '../store'
import type { BuilderLayout, BuilderNode, LayoutBox } from '../types'
import BlockPreview from './BlockPreview.vue'
import CanvasNode from './CanvasNode.vue'
import { surfaceFrame } from './freeGeometry'
import { surfaceIdOf } from './freeMove'

// 自由配置(内容の v2)の面: セクション・ボックスの中と、独自コンポーネントの一番外側。公開側と同じく 1 つのマスに子を重ね、
// 上と左の余白で置く(layout.ts の layoutStyle())。パレットのブロックをドラッグして離すと、その場所に置く。
// ブロックを動かしているときは、吸い付いたガイド線と、離すと入る面を示す。readonly のときは描くだけ(コンポーネントの見本)
const props = withDefaults(defineProps<{
  parentId: string | null
  children: BuilderNode[]
  tag?: string
  readonly?: boolean
}>(), {
  tag: 'div',
  readonly: false,
})

const store = useBuilderStore()
const surface = ref<HTMLElement | null>(null)
const surfaceId = computed(() => surfaceIdOf(props.parentId))
const placement = computed(() => ({ parentId: props.parentId, siblings: props.children }))

const guides = computed(() => store.state.guides.filter(guide => guide.surfaceId === surfaceId.value))
const isDropTarget = computed(() => !props.readonly && (store.state.freeDropSurfaceId === surfaceId.value || (store.state.dropTarget?.from === 'canvas' && store.state.dropTarget.parentId === props.parentId && store.state.dragging?.kind === 'new')))
// パレットのブロックを置く場所の見本(面の左上からの px)
const ghost = ref<{ left: number, top: number, width: number } | null>(null)

function readonlyStyle(child: BuilderNode): Record<string, string> {
  return layoutStyle(child, props.children, store.state.device)
}

function hasHeight(child: BuilderNode): boolean {
  return (isStacked(props.children, store.state.device) ? child.layout?.desktop : deviceBox(child, store.state.device))?.h !== undefined
}

/**
 * 離した場所に置くときの位置。スマートフォンで縦 1 列に並べている面では位置を決めず、いちばん下に置く(layout.ts の syncLayouts())。
 */
function dropLayout(event: DragEvent, type: string): BuilderLayout | undefined {
  const device = store.state.device

  if (!surface.value || isStacked(props.children, device)) {
    return undefined
  }

  const frame = surfaceFrame(surface.value)
  const { w, h } = defaultSize(type, store.definition(type)?.layoutHeight === true)
  const x = ((event.clientX - frame.left) / frame.width) * 100 - w / 2
  const box: LayoutBox = h === undefined ? { x, y: event.clientY - frame.top, w } : { x, y: event.clientY - frame.top, w, h }
  ghost.value = { left: frame.offsetLeft + (Math.min(100 - w, Math.max(0, x)) / 100) * frame.width, top: frame.offsetTop + Math.max(0, box.y), width: (w / 100) * frame.width }

  // タブレット・スマートフォンだけの位置を持つ面では、その端末の位置にも入れる(デスクトップは同じ位置から始める)
  return device !== 'desktop' && hasDeviceLayout(props.children, device) ? { desktop: { ...box }, [device]: box } : { desktop: box }
}

function onDragOver(event: DragEvent): void {
  const dragging = store.state.dragging

  // 置いてあるブロック(セクションなど)のドラッグは、外側の並びに任せる
  if (props.readonly || dragging?.kind !== 'new' || !store.canDropInto(props.parentId)) {
    return
  }

  event.preventDefault()
  event.stopPropagation()

  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'copy'
  }

  store.state.dropTarget = { parentId: props.parentId, index: props.children.length, from: 'canvas', layout: dropLayout(event, dragging.type) }
}

function onDragLeave(event: DragEvent): void {
  if (!surface.value?.contains(event.relatedTarget as Node | null)) {
    ghost.value = null
  }
}

function onDrop(event: DragEvent): void {
  ghost.value = null

  if (store.state.dropTarget?.parentId !== props.parentId || store.state.dragging?.kind !== 'new') {
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
    ref="surface"
    class="builder-free"
    :class="{ 'is-drop-target': isDropTarget, 'is-empty': children.length === 0 }"
    :data-surface-id="readonly ? undefined : surfaceId"
    @dragover="onDragOver"
    @dragleave="onDragLeave"
    @drop="onDrop"
  >
    <template v-if="readonly">
      <div
        v-for="child in children"
        :key="child.id"
        class="builder-free-item"
        :class="[child.classes, { 'has-height': hasHeight(child), 'is-image': child.type === 'image' }]"
        :style="readonlyStyle(child)"
      >
        <BlockPreview :node="child" readonly />
      </div>
    </template>
    <template v-else>
      <CanvasNode v-for="child in children" :key="child.id" :node="child" :placement="placement" />
      <div v-if="children.length === 0" class="builder-free-empty">{{ t('ここにブロックをドラッグ') }}</div>
      <div
        v-for="(guide, index) in guides"
        :key="index"
        class="builder-free-guide"
        :class="`is-${guide.orientation}`"
        :style="guide.orientation === 'vertical' ? { left: `${guide.position}px` } : { top: `${guide.position}px` }"
      />
      <div
        v-if="ghost && isDropTarget"
        class="builder-free-ghost"
        :style="{ left: `${ghost.left}px`, top: `${ghost.top}px`, width: `${ghost.width}px` }"
      />
    </template>
  </component>
</template>
