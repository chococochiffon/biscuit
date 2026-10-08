<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, provide, ref, shallowRef } from 'vue'
import { ROOT_ID, type Measure, type MeasuredNode, type Rect } from '../convert'
import { useBuilderStore } from '../store'
import { themeVariables } from '../theme'
import type { BuilderContent } from '../types'
import BlockPreview from './BlockPreview.vue'
import { contentVersionKey, previewDeviceKey } from './keys'

// 行・カラムで流し込む配置(v1)の内容を、画面の外にデスクトップの幅(1200px)で描いて、ブロックごとの位置と大きさを測る。
// 測った値から convert.ts が自由配置(v2)の内容を組み立てる(store の toFreeLayout())。画像・フォント・見本のデータを少し待ってから測る
const store = useBuilderStore()

const content = shallowRef<BuilderContent | null>(null)
const stage = ref<HTMLElement | null>(null)
const style = computed(() => ({ width: '1200px', ...themeVariables(store.state.theme) }))

provide(contentVersionKey, computed(() => content.value?.version ?? 1))
provide(previewDeviceKey, computed(() => 'desktop' as const))

// 画像・見本のデータを待つ時間の上限(ms)
const IMAGE_TIMEOUT = 3000
const SETTLE_DELAY = 400

function toRect(rect: DOMRect): Rect {
  return { left: rect.left, top: rect.top, width: rect.width, height: rect.height }
}

/**
 * 内側の余白・枠線を除いた枠。
 */
function contentRect(element: HTMLElement): Rect {
  const rect = element.getBoundingClientRect()
  const computed = getComputedStyle(element)
  const left = (Number.parseFloat(computed.paddingLeft) || 0) + (Number.parseFloat(computed.borderLeftWidth) || 0)
  const right = (Number.parseFloat(computed.paddingRight) || 0) + (Number.parseFloat(computed.borderRightWidth) || 0)
  const top = (Number.parseFloat(computed.paddingTop) || 0) + (Number.parseFloat(computed.borderTopWidth) || 0)
  const bottom = (Number.parseFloat(computed.paddingBottom) || 0) + (Number.parseFloat(computed.borderBottomWidth) || 0)

  return { left: rect.left + left, top: rect.top + top, width: rect.width - left - right, height: rect.height - top - bottom }
}

function waitForImages(element: HTMLElement): Promise<void> {
  const images = Array.from(element.querySelectorAll('img')).filter(image => !image.complete)
  const loaded = Promise.all(images.map(image => new Promise<void>((resolve) => {
    image.addEventListener('load', () => resolve(), { once: true })
    image.addEventListener('error', () => resolve(), { once: true })
  })))

  return Promise.race([loaded.then(() => undefined), new Promise<void>(resolve => setTimeout(resolve, IMAGE_TIMEOUT))])
}

async function measure(target: BuilderContent): Promise<Measure> {
  content.value = target
  await nextTick()

  const element = stage.value

  if (!element) {
    content.value = null

    return () => null
  }

  await waitForImages(element)
  await document.fonts?.ready
  await new Promise(resolve => setTimeout(resolve, SETTLE_DELAY))
  await waitForImages(element)

  const measured = new Map<string, MeasuredNode>()
  measured.set(ROOT_ID, { rect: toRect(element.getBoundingClientRect()), content: contentRect(element) })

  for (const node of element.querySelectorAll<HTMLElement>('[data-preview-id]')) {
    const image = node.querySelector('img')
    measured.set(node.dataset.previewId!, {
      rect: toRect(node.getBoundingClientRect()),
      content: contentRect(node),
      image: image ? toRect(image.getBoundingClientRect()) : undefined,
    })
  }

  content.value = null

  return id => measured.get(id) ?? null
}

store.setMeasurer(measure)
onBeforeUnmount(() => store.setMeasurer(null))
</script>

<template>
  <div v-if="content" ref="stage" class="builder-convert-stage builder-canvas builder-theme" :style="style" aria-hidden="true">
    <BlockPreview v-for="child in content.children" :key="child.id" :node="child" :class="child.classes" :data-preview-id="child.id" readonly />
  </div>
</template>
