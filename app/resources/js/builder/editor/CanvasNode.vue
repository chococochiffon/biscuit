<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import { columnSpan } from '../styles'
import type { BuilderNode } from '../types'
import BlockPreview from './BlockPreview.vue'

// Canvas のブロック 1 つ。クリックで選択し、マウスを乗せる・選択すると枠と名前を出す。
// 名前の部分をつかんでドラッグすると別の場所へ移せ、選択中は前後への移動・複製・削除のボタンも出す。
// カラムは行(Bootstrap の .row)の直下に並ぶため、この要素に幅のクラス(col-*)を付ける
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()

const definition = computed(() => store.definition(props.node.type))
const isSelected = computed(() => store.state.selectedId === props.node.id)
const isHovered = computed(() => store.state.hoveredId === props.node.id)
const isDragging = computed(() => store.state.dragging?.kind === 'move' && store.state.dragging.id === props.node.id)
const hasError = computed(() => `nodes.${props.node.id}` in store.state.errors)
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
</script>

<template>
  <div
    class="builder-node"
    :class="[columnClass, { 'is-selected': isSelected, 'is-hovered': isHovered && !isSelected, 'is-dragging': isDragging, 'has-error': hasError }]"
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
        <button type="button" class="builder-node-button text-danger" :title="t('削除')" @click="store.remove(node.id)">
          <i class="bi bi-trash" />
        </button>
      </template>
    </div>
    <BlockPreview :node="node" />
  </div>
</template>
