<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { BuilderNode, GalleryImageSummary } from '../types'
import { usePreviewDevice } from './keys'

// ギャラリーのブロックの Canvas の見本。取得の条件どおりの公開中の画像を biscuit から取ってきて、公開側(chococo)と同じく正方形のタイルで並べる。
// 列数はデスクトップは columns、タブレットは 3 まで、スマートフォンは 2(公開側と同じ)
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()
const device = usePreviewDevice(store)
const images = ref<GalleryImageSummary[] | null>(null)

const condition = computed(() => JSON.stringify([props.node.props.category, props.node.props.limit]))
const showCaption = computed(() => props.node.props.showCaption !== false)
const columns = computed(() => {
  const desktop = typeof props.node.props.columns === 'number' ? props.node.props.columns : 4

  return device.value === 'desktop' ? desktop : device.value === 'tablet' ? Math.min(3, desktop) : 2
})

let timer: ReturnType<typeof setTimeout> | undefined

watch(condition, () => {
  clearTimeout(timer)
  timer = setTimeout(async () => {
    images.value = await store.galleryPreview(props.node.props)
  }, images.value === null ? 0 : 400)
}, { immediate: true })

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div>
    <div v-if="images === null" class="builder-preview-placeholder">{{ t('読み込んでいます...') }}</div>
    <div v-else-if="images.length === 0" class="builder-preview-placeholder">
      <i class="bi bi-images" /> {{ t('条件に合う公開中の画像がありません。') }}
    </div>
    <div v-else class="row g-2" :class="`row-cols-${columns}`">
      <div v-for="image in images" :key="image.id" class="col">
        <div class="builder-gallery-tile rounded">
          <img :src="image.image_url" :alt="image.name">
          <span v-if="showCaption" class="builder-gallery-caption">{{ image.name }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
