<script setup lang="ts">
import { computed, onBeforeUnmount, watchEffect } from 'vue'
import { scopedCss } from '../customCss'
import type { BuilderNode } from '../types'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import { themeVariables } from '../theme'
import DropList from './DropList.vue'

// 中央の Canvas: ページの内容を公開側に近い見た目で描き、ブロックの選択・ドラッグ&ドロップを受ける。
// 端末(デスクトップ・タブレット・スマートフォン)ごとに Canvas の幅を変え、テーマ(色・フォント)を効かせる
const store = useBuilderStore()

const DEVICE_WIDTHS = { desktop: '1200px', tablet: '768px', mobile: '375px' } as const
const width = computed(() => DEVICE_WIDTHS[store.state.device])
// テーマの色・フォントを CSS の変数にして Canvas に置く(色のスタイルの theme:名前・ボタンの色・フォントに使う)
const canvasStyle = computed(() => ({ maxWidth: width.value, ...themeVariables(store.state.theme) }))

// Custom CSS(サイト共通 → 置いたコンポーネント → このページの順)を Canvas の要素の中にネストして効かせる(エディタの画面には効かない)
const componentCss = computed(() => {
  const ids = new Set<number>()
  const walk = (nodes: BuilderNode[]) => nodes.forEach((node) => {
    if ((node.type === 'global' || node.type === 'custom') && typeof node.props.component === 'number') {
      ids.add(node.props.component)
    }
    walk(node.children ?? [])
  })
  walk(store.state.content.children)

  return [...ids].map(id => store.state.components?.find(component => component.id === id)?.content?.css)
})
const styleElement = document.createElement('style')
styleElement.dataset.builderCustomCss = ''
document.head.append(styleElement)

watchEffect(() => {
  styleElement.textContent = [store.state.theme.css, ...componentCss.value, store.state.content.css]
    .map(css => scopedCss('.builder-canvas', css))
    .join('')
})

onBeforeUnmount(() => styleElement.remove())
</script>

<template>
  <main class="builder-canvas-area" @click="store.select(null)" @mouseleave="store.state.hoveredId = null">
    <div class="builder-canvas builder-theme" :style="canvasStyle">
      <div v-if="store.state.content.children.length === 0" class="builder-canvas-start">
        <button type="button" class="btn btn-outline-primary" @click.stop="store.state.templatesOpen = true">
          <i class="bi bi-files" /> {{ t('テンプレートから始める') }}
        </button>
      </div>
      <DropList
        :parent-id="null"
        :children="store.state.content.children"
        is-root
        class="builder-canvas-root"
        :empty-label="t('左のパレットからセクションをここにドラッグしてください。')"
      />
    </div>
  </main>
</template>
