<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import DropList from './DropList.vue'

// 中央の Canvas: ページの内容を公開側に近い見た目で描き、ブロックの選択・ドラッグ&ドロップを受ける。
// 端末(デスクトップ・タブレット・スマートフォン)ごとに Canvas の幅を変える
const store = useBuilderStore()

const DEVICE_WIDTHS = { desktop: '1200px', tablet: '768px', mobile: '375px' } as const
const width = computed(() => DEVICE_WIDTHS[store.state.device])
</script>

<template>
  <main class="builder-canvas-area" @click="store.select(null)" @mouseleave="store.state.hoveredId = null">
    <div class="builder-canvas" :style="{ maxWidth: width }">
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
