<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { t } from '../i18n'
import { deviceBox, round2 } from '../layout'
import { findNode } from '../nodes'
import { useBuilderStore } from '../store'
import type { BuilderNode, LayoutBox } from '../types'
import { ensureDeviceLayout, surfaceFrame } from './freeGeometry'
import type { FreePlacement } from './freeMove'

// 自由配置の面のブロックの、大きさを変えるつまみ。右の端で幅、左の端で左端の位置と幅(右端はそのまま)、
// 高さを持てるブロック(画像・ボックス)は下の端で高さ、右下の角で幅と高さを変える。値は選んでいる端末の位置に入れる。
// ドラッグしている間は履歴を積まずに Canvas へ出し、離したら 1 回の操作として積む
const props = defineProps<{
  node: BuilderNode
  host: HTMLElement
  placement: FreePlacement
}>()

const store = useBuilderStore()

type Handle = 'e' | 'w' | 's' | 'se'

const allowsHeight = computed(() => store.definition(props.node.type)?.layoutHeight === true)
const handles = computed<Handle[]>(() => (allowsHeight.value ? ['w', 'e', 's', 'se'] : ['w', 'e']))

const TITLES: Record<Handle, string> = {
  e: t('ドラッグして幅を変える'),
  w: t('ドラッグして幅を変える'),
  s: t('ドラッグして高さを変える'),
  se: t('ドラッグして幅と高さを変える'),
}

// start は動かし始めたときの位置(高さを持たないこともある)、startHeight は高さを変えるときの始まりの値(なければ今の見た目の高さ)
const active = ref<{ handle: Handle, startX: number, startY: number, start: LayoutBox, startHeight: number, width: number, snapshot: string } | null>(null)
const valueText = ref('')

function onPointerDown(handle: Handle, event: PointerEvent): void {
  event.preventDefault()
  event.stopPropagation()
  ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)

  const surface = props.host.parentElement

  if (!surface) {
    return
  }

  const snapshot = store.beginLiveEdit()
  ensureDeviceLayout(store, props.placement.parentId, props.placement.siblings, store.state.device, surface)

  const current = findNode(store.state.content, props.node.id)
  const box = current ? deviceBox(current, store.state.device) : null

  if (!box) {
    store.endLiveEdit(snapshot, props.node.id)

    return
  }

  const startHeight = box.h ?? Math.round(props.host.getBoundingClientRect().height)
  active.value = { handle, startX: event.clientX, startY: event.clientY, start: { ...box }, startHeight, width: surfaceFrame(surface).width, snapshot }
  store.state.resizing = { id: props.node.id, label: '' }
}

function onPointerMove(event: PointerEvent): void {
  const drag = active.value

  if (!drag) {
    return
  }

  const dxPercent = ((event.clientX - drag.startX) / drag.width) * 100
  const dy = event.clientY - drag.startY
  const box: LayoutBox = { ...drag.start }

  if (drag.handle === 'e' || drag.handle === 'se') {
    box.w = Math.min(100 - box.x, Math.max(1, drag.start.w + dxPercent))
  }
  if (drag.handle === 'w') {
    const right = drag.start.x + drag.start.w
    box.x = Math.min(right - 1, Math.max(0, drag.start.x + dxPercent))
    box.w = right - box.x
  }
  if (drag.handle === 's' || drag.handle === 'se') {
    box.h = Math.max(1, drag.startHeight + dy)
  }

  store.liveEdit(props.node.id, (node) => {
    store.setLiveBox(node, store.state.device, box)

    return true
  })

  valueText.value = [`${t('幅')} ${round2(box.w)}%`, box.h === undefined ? null : `${t('高さ')} ${Math.round(box.h)}px`].filter(Boolean).join(' / ')
}

function finish(): void {
  if (active.value) {
    store.endLiveEdit(active.value.snapshot, props.node.id)
    active.value = null
    store.state.resizing = null
  }
}

function onPointerUp(event: PointerEvent): void {
  if (active.value) {
    ;(event.currentTarget as HTMLElement).releasePointerCapture(event.pointerId)
  }
  finish()
}

onBeforeUnmount(finish)

const handlers = (handle: Handle) => ({
  pointerdown: (event: PointerEvent) => onPointerDown(handle, event),
  pointermove: onPointerMove,
  pointerup: onPointerUp,
  pointercancel: onPointerUp,
  click: (event: MouseEvent) => event.stopPropagation(),
})
</script>

<template>
  <div
    v-for="handle in handles"
    :key="handle"
    class="builder-free-handle"
    :class="[`is-${handle}`, { 'is-active': active?.handle === handle }]"
    :title="TITLES[handle]"
    v-on="handlers(handle)"
  />
  <div v-if="active && valueText" class="builder-resize-value">
    {{ valueText }}
  </div>
</template>
