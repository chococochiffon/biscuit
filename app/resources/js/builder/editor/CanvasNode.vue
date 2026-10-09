<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import { columnSpan } from '../styles'
import type { BuilderNode } from '../types'
import { isHiddenOn, periodState } from '../visibility'
import BlockPreview from './BlockPreview.vue'
import ResizeHandles from './ResizeHandles.vue'

// Canvas のブロック 1 つ。クリックで選択し、マウスを乗せる・選択すると枠と名前を出す。
// 名前の部分をつかんでドラッグすると別の場所へ移せ、選択中は前後への移動・複製・コピー・削除のボタンも出す。
// 選択中は端につまみを出し、ドラッグで高さ・幅・内側の余白を変えられる(ResizeHandles)。
// 表示条件の付いたブロックには右上に印を出し、選んでいる端末で表示しない・表示期間の外のブロックは薄く描く。
// カラムは行(Bootstrap の .row)の直下に並ぶため、この要素に幅のクラス(col-*)を付ける
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()
const element = ref<HTMLElement | null>(null)

const definition = computed(() => store.definition(props.node.type))
const isSelected = computed(() => store.state.selectedId === props.node.id)
const isHovered = computed(() => store.state.hoveredId === props.node.id)
const isDragging = computed(() => store.state.dragging?.kind === 'move' && store.state.dragging.id === props.node.id)
const hasError = computed(() => `nodes.${props.node.id}` in store.state.errors)
// 表示条件: 選んでいる端末で表示しないブロックは薄く描き、条件の付いたブロックには右上に印を出す
const hiddenHere = computed(() => isHiddenOn(props.node, store.state.device))
const period = computed(() => periodState(props.node, store.now()))
const PERIOD_TITLES = {
  always: '',
  scheduled: t('表示期間の前です(公開側にはまだ出ません)。'),
  active: t('表示する期間を指定しています。'),
  ended: t('表示期間を過ぎています(公開側には出ません)。'),
}
const columnClass = computed(() => (props.node.type === 'column' ? `col-${columnSpan(props.node, store.state.device)}` : ''))

function startDrag(event: DragEvent): void {
  event.stopPropagation()
  event.dataTransfer?.setData('text/plain', props.node.id)
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
  }
  store.state.dragging = { kind: 'move', id: props.node.id, type: props.node.type }
  // ドラッグ中に別のブロックへマウスが乗っても名前の部分(ドラッグ元)が消えないよう、選択しておく
  // (ドラッグ元の要素が消えると、ブラウザがドラッグを取りやめる)
  store.select(props.node.id)
}

/**
 * ブロックをシステムのクリップボードにコピーする(Ctrl+C と同じ。貼り付けは Ctrl+V)。
 */
async function copy(): Promise<void> {
  store.select(props.node.id)
  const text = store.copySelected()

  try {
    await navigator.clipboard.writeText(text!)
  }
  catch {
    store.state.message = { type: 'danger', text: t('クリップボードにコピーできませんでした。Ctrl+C でコピーしてください。') }
  }
}
</script>

<template>
  <div
    ref="element"
    class="builder-node"
    :class="[columnClass, { 'is-selected': isSelected, 'is-hovered': isHovered && !isSelected, 'is-dragging': isDragging, 'is-resizing': store.state.resizing?.id === node.id, 'has-error': hasError, 'is-hidden-here': hiddenHere, 'is-out-of-period': period === 'scheduled' || period === 'ended' }]"
    :data-node-id="node.id"
    @click.stop="store.select(node.id)"
    @mouseover.stop="store.state.hoveredId = node.id"
  >
    <div v-if="isSelected || isHovered || isDragging" class="builder-node-bar" @click.stop>
      <span
        class="builder-node-handle"
        draggable="true"
        :title="t('ドラッグして移動')"
        @dragstart="startDrag"
        @dragend="store.endDrag()"
        @click="store.select(node.id)"
      >
        <i class="bi bi-grip-vertical" />{{ definition?.label ?? node.type }}
      </span>
      <template v-if="isSelected">
        <button type="button" class="builder-node-button" :title="t('前へ移動')" @click="store.moveSelectedBy(-1)">
          <i class="bi bi-arrow-up" />
        </button>
        <button type="button" class="builder-node-button" :title="t('後ろへ移動')" @click="store.moveSelectedBy(1)">
          <i class="bi bi-arrow-down" />
        </button>
        <button type="button" class="builder-node-button" :title="t('複製')" @click="store.duplicate(node.id)">
          <i class="bi bi-copy" />
        </button>
        <button type="button" class="builder-node-button" :title="t('コピー') + ' (Ctrl+C)'" @click="copy">
          <i class="bi bi-clipboard" />
        </button>
        <button type="button" class="builder-node-button text-danger" :title="t('削除')" @click="store.remove(node.id)">
          <i class="bi bi-trash" />
        </button>
      </template>
    </div>
    <div v-if="node.visibility" class="builder-node-flags">
      <i v-if="node.visibility.hideOn?.length" class="bi bi-eye-slash" :title="hiddenHere ? t('この端末では表示しません。') : t('表示しない端末を指定しています。')" />
      <i v-if="period !== 'always'" class="bi bi-clock" :class="`is-${period}`" :title="PERIOD_TITLES[period]" />
    </div>
    <BlockPreview :node="node" class="builder-node-body" :class="node.classes" />
    <ResizeHandles v-if="isSelected && element && !store.state.dragging" :node="node" :host="element" />
  </div>
</template>
