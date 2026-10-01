<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import { blockStyle } from '../styles'
import type { BuilderNode } from '../types'
import ArticleListPreview from './ArticleListPreview.vue'
import BreadcrumbPreview from './BreadcrumbPreview.vue'
import DropList from './DropList.vue'
import GalleryPreview from './GalleryPreview.vue'
import NavigationPreview from './NavigationPreview.vue'

// Canvas に描くブロックの中身。公開側(chococo の components/builder/blocks)と同じ Bootstrap の要素で近い見た目にする。
// 中にブロックを置ける種類(セクション・コンテナ・行・カラム)は、子の並びを DropList で描く
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()

const style = computed(() => blockStyle(props.node, store.state.device))
const innerStyle = computed(() => blockStyle(props.node, store.state.device, 'inner'))
const children = computed(() => props.node.children ?? [])

const text = (name: string) => (typeof props.node.props[name] === 'string' ? props.node.props[name] as string : '')
const int = (name: string, fallback: number) => (typeof props.node.props[name] === 'number' ? props.node.props[name] as number : fallback)

const sectionStyle = computed(() => {
  const backgroundImage = store.imageUrl(props.node.props.backgroundImage)

  return backgroundImage
    ? { ...style.value, backgroundImage: `url("${backgroundImage}")`, backgroundSize: 'cover', backgroundPosition: 'center center' }
    : style.value
})
const imageUrl = computed(() => store.imageUrl(props.node.props.src))
const headingTag = computed(() => `h${Math.min(6, Math.max(1, int('level', 2)))}`)
</script>

<template>
  <DropList
    v-if="node.type === 'section'"
    tag="section"
    :parent-id="node.id"
    :children="children"
    class="builder-preview-section"
    :style="sectionStyle"
  />
  <DropList
    v-else-if="node.type === 'container'"
    :parent-id="node.id"
    :children="children"
    class="container"
    :style="style"
  />
  <DropList
    v-else-if="node.type === 'row'"
    :parent-id="node.id"
    :children="children"
    direction="horizontal"
    class="row"
    :class="`g-${int('gap', 3)}`"
    :style="style"
    :empty-label="t('ここにカラムをドラッグ')"
  />
  <DropList
    v-else-if="node.type === 'column'"
    :parent-id="node.id"
    :children="children"
    class="builder-preview-column"
    :style="style"
  />
  <component :is="headingTag" v-else-if="node.type === 'heading'" :style="style">
    {{ text('text') || t('(空の見出し)') }}
  </component>
  <!-- eslint-disable-next-line vue/no-v-html -- エディタで入力した本文(保存時に biscuit が無害化する) -->
  <div v-else-if="node.type === 'text'" class="rich-content" :style="style" v-html="text('html') || `<p class='text-secondary'>${t('(空のテキスト)')}</p>`" />
  <div v-else-if="node.type === 'image'" :style="style">
    <img v-if="imageUrl" :src="imageUrl" :alt="text('alt')" class="img-fluid" :style="innerStyle">
    <div v-else class="builder-preview-placeholder">
      <i class="bi bi-image" /> {{ t('画像を選んでください') }}
    </div>
  </div>
  <div v-else-if="node.type === 'button'" :style="style">
    <span class="btn" :class="`btn-${text('variant') || 'primary'}`" :style="innerStyle">{{ text('text') }}</span>
  </div>
  <div v-else-if="node.type === 'spacer'" class="builder-preview-spacer" :style="{ height: `${int('height', 32)}px` }" />
  <hr v-else-if="node.type === 'divider'" class="builder-preview-divider" :style="style">
  <ArticleListPreview v-else-if="node.type === 'article-list'" :node="node" :style="style" />
  <NavigationPreview v-else-if="node.type === 'navigation'" :node="node" :style="style" />
  <BreadcrumbPreview v-else-if="node.type === 'breadcrumb'" :node="node" :style="style" />
  <GalleryPreview v-else-if="node.type === 'gallery'" :node="node" :style="style" />
  <div v-else class="builder-preview-placeholder">
    {{ t('この種類のブロックは表示できません。') }}
  </div>
</template>
