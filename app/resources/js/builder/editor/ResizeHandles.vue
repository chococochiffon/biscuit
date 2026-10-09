<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { styleLabel } from '../fields/styleLabels'
import { t } from '../i18n'
import { applyResize, pixels, resizedValue, resizeTargets, type ResizeKind, type ResizeTarget } from '../resize'
import { useBuilderStore } from '../store'
import { effectiveStyles } from '../styles'
import type { BuilderNode } from '../types'

// 選んだブロックの端に出す、大きさを変えるつまみ。下の端で高さ(セクションの最小の高さ・スペーサーの高さ)、
// 右の端で幅(画像の幅・コンテナなどの最大幅・カラムの幅)、上下の内側の帯で余白を変える。
// ドラッグしている間は履歴を積まずに Canvas へ出し、離したら 1 回の操作として積む(元に戻すと動かす前に戻る)
const props = defineProps<{
  node: BuilderNode
  // CanvasNode の要素(位置の基準。中のブロックの要素は .builder-node-body)
  host: HTMLElement
}>()

const store = useBuilderStore()

const targets = computed(() => resizeTargets(props.node, store.definition(props.node.type)))
const target = (kind: ResizeKind) => targets.value.find(item => item.kind === kind)

// つまみの位置(host の左上からの px)。ブロックの大きさ・スタイルが変わるたびに測り直す
const box = reactive({ height: 0, width: 0, bodyTop: 0, bodyHeight: 0, paddingTop: 0, paddingBottom: 0, widthRight: 0, widthTop: 0, widthHeight: 0 })

function body(): HTMLElement {
  return props.host.querySelector<HTMLElement>(':scope > .builder-node-body') ?? props.host
}

// 幅を変える要素(画像の幅は img、カラムは CanvasNode の要素、ほかはブロックの要素)
function widthElement(): HTMLElement {
  if (props.node.type === 'column') {
    return props.host
  }

  return (target('width')?.name === 'width' ? body().querySelector<HTMLElement>('img') : null) ?? body()
}

function measure(): void {
  const element = body()
  const style = getComputedStyle(element)
  const hostRect = props.host.getBoundingClientRect()
  const bodyRect = element.getBoundingClientRect()
  const widthRect = widthElement().getBoundingClientRect()

  Object.assign(box, {
    height: hostRect.height,
    width: hostRect.width,
    bodyTop: bodyRect.top - hostRect.top,
    bodyHeight: bodyRect.height,
    paddingTop: Number.parseFloat(style.paddingTop) || 0,
    paddingBottom: Number.parseFloat(style.paddingBottom) || 0,
    widthRight: widthRect.right - hostRect.left,
    widthTop: widthRect.top - hostRect.top,
    widthHeight: widthRect.height,
  })
}

const observer = new ResizeObserver(() => measure())

onMounted(() => {
  measure()
  observer.observe(props.host)
  observer.observe(body())
})
onBeforeUnmount(() => observer.disconnect())

// 余白は大きさが変わらないこともあるため、スタイル・端末が変わったら測り直す
watch(() => [JSON.stringify(effectiveStyles(props.node, store.state.device)), store.state.device], () => nextTick(measure))

// ドラッグの途中の状態
const active = ref<{ target: ResizeTarget, startX: number, startY: number, start: number, snapshot: string, options: Parameters<typeof resizedValue>[3] } | null>(null)
const valueText = ref('')

function label(item: ResizeTarget): string {
  if (props.node.type === 'column') {
    return t('幅')
  }

  return item.source === 'prop' ? t('高さ') : styleLabel(item.name)
}

function format(item: ResizeTarget, value: number): string {
  if (props.node.type === 'column' && item.kind === 'width') {
    return `${value} / 12`
  }

  return item.source === 'prop' ? `${Math.round(value)}px` : pixels(value)
}

/**
 * ドラッグを始めたときの値と、幅の計算に使う親の大きさ。
 */
function startValue(item: ResizeTarget): { start: number, options: Parameters<typeof resizedValue>[3] } {
  const element = body()

  switch (item.kind) {
    case 'height':
      return { start: item.source === 'prop' ? Number(props.node.props.height ?? 32) : element.getBoundingClientRect().height, options: {} }
    case 'paddingTop':
      return { start: box.paddingTop, options: {} }
    case 'paddingBottom':
      return { start: box.paddingBottom, options: {} }
    default: {
      const measured = widthElement()
      const parentWidth = (props.node.type === 'column' ? props.host.parentElement : measured.parentElement)?.getBoundingClientRect().width ?? 0
      const computed = getComputedStyle(measured)
      const centered = computed.marginLeft === computed.marginRight && Number.parseFloat(computed.marginLeft) > 0

      return { start: measured.getBoundingClientRect().width, options: { rowWidth: parentWidth, maxWidth: parentWidth || undefined, centered } }
    }
  }
}

function onPointerDown(item: ResizeTarget, event: PointerEvent): void {
  event.preventDefault()
  event.stopPropagation()
  ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)

  const { start, options } = startValue(item)
  active.value = { target: item, startX: event.clientX, startY: event.clientY, start, snapshot: store.beginLiveEdit(), options }
  store.state.resizing = { id: props.node.id, label: label(item) }
  valueText.value = format(item, item.kind === 'width' && props.node.type === 'column' ? resizedValue(item, start, 0, options) : start)
}

function onPointerMove(event: PointerEvent): void {
  const drag = active.value

  if (!drag) {
    return
  }

  const dx = event.clientX - drag.startX
  const dy = event.clientY - drag.startY
  // 広げる向きを正にそろえる(下の余白は、つまみを上へ動かすと広がる)
  const delta = drag.target.kind === 'width' ? dx : drag.target.kind === 'paddingBottom' ? -dy : dy
  const value = resizedValue(drag.target, drag.start, delta, drag.options)

  store.liveEdit(props.node.id, node => applyResize(node, store.state.device, drag.target, value))
  valueText.value = format(drag.target, value)
}

function onPointerUp(event: PointerEvent): void {
  const drag = active.value

  if (!drag) {
    return
  }

  ;(event.currentTarget as HTMLElement).releasePointerCapture(event.pointerId)
  store.endLiveEdit(drag.snapshot, props.node.id)
  active.value = null
  store.state.resizing = null
  nextTick(measure)
}

onBeforeUnmount(() => {
  if (active.value) {
    store.endLiveEdit(active.value.snapshot, props.node.id)
    store.state.resizing = null
  }
})

const handlers = (item: ResizeTarget) => ({
  pointerdown: (event: PointerEvent) => onPointerDown(item, event),
  pointermove: onPointerMove,
  pointerup: onPointerUp,
  pointercancel: onPointerUp,
  click: (event: MouseEvent) => event.stopPropagation(),
})

const isActive = (kind: ResizeKind) => active.value?.target.kind === kind
</script>

<template>
  <!-- 上の内側の余白 -->
  <div
    v-if="target('paddingTop')"
    class="builder-resize-padding is-top"
    :class="{ 'is-active': isActive('paddingTop') }"
    :style="{ top: `${box.bodyTop}px`, height: `${Math.max(6, box.paddingTop)}px` }"
    :title="t('ドラッグして上の余白を変える')"
    v-on="handlers(target('paddingTop')!)"
  />
  <!-- 下の内側の余白 -->
  <div
    v-if="target('paddingBottom')"
    class="builder-resize-padding is-bottom"
    :class="{ 'is-active': isActive('paddingBottom') }"
    :style="{ top: `${box.bodyTop + box.bodyHeight - Math.max(6, box.paddingBottom)}px`, height: `${Math.max(6, box.paddingBottom)}px` }"
    :title="t('ドラッグして下の余白を変える')"
    v-on="handlers(target('paddingBottom')!)"
  />
  <!-- 下の端: 高さ -->
  <div
    v-if="target('height')"
    class="builder-resize-handle is-bottom"
    :class="{ 'is-active': isActive('height') }"
    :style="{ top: `${box.bodyTop + box.bodyHeight}px` }"
    :title="t('ドラッグして高さを変える')"
    v-on="handlers(target('height')!)"
  />
  <!-- 右の端: 幅 -->
  <div
    v-if="target('width')"
    class="builder-resize-handle is-right"
    :class="{ 'is-active': isActive('width') }"
    :style="{ left: `${box.widthRight}px`, top: `${box.widthTop + box.widthHeight / 2}px` }"
    :title="t('ドラッグして幅を変える')"
    v-on="handlers(target('width')!)"
  />
  <div v-if="active && store.state.resizing" class="builder-resize-value">
    {{ store.state.resizing.label }} {{ valueText }}
  </div>
</template>
