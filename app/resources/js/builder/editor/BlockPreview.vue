<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import { FREE_LAYOUT_VERSION } from '../layout'
import { blockStyle, columnSpan } from '../styles'
import type { BuilderNode } from '../types'
import ArticleListPreview from './ArticleListPreview.vue'
import BreadcrumbPreview from './BreadcrumbPreview.vue'
import DropList from './DropList.vue'
import FreeSurface from './FreeSurface.vue'
import { useContentVersion, usePreviewDevice } from './keys'
import CustomPreview from './CustomPreview.vue'
import GlobalPreview from './GlobalPreview.vue'
import { videoEmbedUrl } from '../video'
import GalleryPreview from './GalleryPreview.vue'
import InlineRichText from './InlineRichText.vue'
import InlineText from './InlineText.vue'
import NavigationPreview from './NavigationPreview.vue'

// Canvas に描くブロックの中身。公開側(chococo の components/builder/blocks)と同じ Bootstrap の要素で近い見た目にする。
// 中にブロックを置ける種類(セクション・コンテナ・行・カラム・スライダー)は、子の並びを DropList で描く。
// 自由配置(内容の v2)のセクション・ボックスは、中を面(FreeSurface)で描く。
// readonly のときは選択・ドラッグを受けずに描くだけにする(グローバルコンポーネントの中身の見本)
const props = defineProps<{
  node: BuilderNode
  readonly?: boolean
}>()

const store = useBuilderStore()
const device = usePreviewDevice(store)

const style = computed(() => blockStyle(props.node, device.value))
const innerStyle = computed(() => blockStyle(props.node, device.value, 'inner'))
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
const isContainer = computed(() => ['section', 'container', 'row', 'column'].includes(props.node.type))
// 自由配置(v2)の内容か(コンポーネントの見本では中身の版)
const contentVersion = useContentVersion(store)
const isFree = computed(() => contentVersion.value >= FREE_LAYOUT_VERSION)
// ボックスの要素のスタイル(背景画像は section と同じく props)
const boxStyle = computed(() => {
  const backgroundImage = store.imageUrl(props.node.props.backgroundImage)

  return backgroundImage
    ? { ...style.value, backgroundImage: `url("${backgroundImage}")`, backgroundSize: 'cover', backgroundPosition: 'center center' }
    : style.value
})

// 読み取り専用で描くときの、中にブロックを置ける種類の要素のクラス(行の直下のカラムは幅のクラスをこの要素に付ける)
const readonlyClass = computed(() => {
  switch (props.node.type) {
    case 'section':
      return 'builder-preview-section'
    case 'container':
      return 'container'
    case 'row':
      return ['row', `g-${int('gap', 3)}`]
    default:
      return ['builder-preview-column', `col-${columnSpan(props.node, device.value)}`]
  }
})

const videoUrl = computed(() => videoEmbedUrl(props.node.props.url))

// スライダーの縦横比(16x9 → 16 / 9)と、読み取り専用で描くときに出す最初のスライドの画像
const sliderAspect = computed(() => text('aspect') || '16x9')
const firstSlideUrl = computed(() => store.imageUrl(children.value[0]?.props.src))
const headingTag = computed(() => `h${Math.min(6, Math.max(1, int('level', 2)))}`)
// Canvas の上で文字を直接書き換えているか(読み取り専用の見本では書き換えない)
const editing = computed(() => !props.readonly && store.state.editingId === props.node.id)
</script>

<template>
  <!-- 自由配置(v2)のセクション: 中身の幅を最大 1200px にした面に、ブロックを座標で置く -->
  <section v-if="isFree && node.type === 'section'" class="builder-preview-section" :style="sectionStyle">
    <FreeSurface :parent-id="node.id" :children="children" :readonly="readonly" class="builder-section-surface" />
  </section>
  <FreeSurface
    v-else-if="node.type === 'box'"
    :parent-id="node.id"
    :children="children"
    :readonly="readonly"
    class="builder-preview-box"
    :style="boxStyle"
  />
  <component
    :is="node.type === 'section' ? 'section' : 'div'"
    v-else-if="readonly && isContainer"
    :class="readonlyClass"
    :style="node.type === 'section' ? sectionStyle : style"
  >
    <BlockPreview v-for="child in children" :key="child.id" :node="child" :class="child.classes" :data-preview-id="child.id" readonly />
  </component>
  <div v-else-if="readonly && node.type === 'slider'" :style="style">
    <div class="ratio" :class="`ratio-${sliderAspect}`">
      <img v-if="firstSlideUrl" :src="firstSlideUrl" alt="" class="builder-preview-slide-image">
    </div>
  </div>
  <DropList
    v-else-if="node.type === 'section'"
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
  <!-- スライダーは、中のスライドを横に並べて描く(切り替えの見た目はプレビュー・公開側で確かめる) -->
  <DropList
    v-else-if="node.type === 'slider'"
    :parent-id="node.id"
    :children="children"
    direction="horizontal"
    class="builder-preview-slider"
    :style="{ ...style, '--builder-slide-aspect': sliderAspect.replace('x', ' / ') }"
    :empty-label="t('ここにスライドをドラッグ')"
  />
  <div v-else-if="node.type === 'slide'" class="builder-preview-slide">
    <img v-if="imageUrl" :src="imageUrl" :alt="text('alt')" class="builder-preview-slide-image">
    <div v-else class="builder-preview-slide-empty">
      <i class="bi bi-image" /> {{ t('画像を選んでください') }}
    </div>
  </div>
  <InlineText v-else-if="editing && node.type === 'heading'" :node="node" prop="text" :tag="headingTag" :style="style" />
  <component :is="headingTag" v-else-if="node.type === 'heading'" :style="style">
    {{ text('text') || t('(空の見出し)') }}
  </component>
  <!-- eslint-disable-next-line vue/no-v-html -- エディタで入力した本文(保存時に biscuit が無害化する) -->
  <InlineRichText v-else-if="editing && node.type === 'text'" :node="node" :style="style" />
  <div v-else-if="node.type === 'text'" class="rich-content" :style="style" v-html="text('html') || `<p class='text-secondary'>${t('(空のテキスト)')}</p>`" />
  <div v-else-if="node.type === 'image'" :style="style">
    <img v-if="imageUrl" :src="imageUrl" :alt="text('alt')" class="img-fluid" :style="innerStyle">
    <div v-else class="builder-preview-placeholder">
      <i class="bi bi-image" /> {{ t('画像を選んでください') }}
    </div>
  </div>
  <div v-else-if="node.type === 'button'" :style="style">
    <InlineText v-if="editing" :node="node" prop="text" tag="span" class="btn" :class="`btn-${text('variant') || 'primary'}`" :style="innerStyle" />
    <span v-else class="btn" :class="`btn-${text('variant') || 'primary'}`" :style="innerStyle">{{ text('text') }}</span>
  </div>
  <div v-else-if="node.type === 'spacer'" class="builder-preview-spacer" :style="{ height: `${int('height', 32)}px` }" />
  <hr v-else-if="node.type === 'divider'" class="builder-preview-divider" :style="style">
  <div v-else-if="node.type === 'video'" :style="style">
    <div v-if="videoUrl" class="ratio builder-preview-video" :class="`ratio-${text('aspect') || '16x9'}`">
      <iframe :src="videoUrl" :title="text('title')" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" />
      <!-- iframe がクリックを受けるとブロックを選べないため、上に透明な覆いを重ねる(動画は公開側・プレビューで再生する) -->
      <div class="builder-preview-video-cover" />
    </div>
    <div v-else class="builder-preview-placeholder">
      <i class="bi bi-play-btn" /> {{ t('動画の URL を入力してください') }}
    </div>
  </div>
  <ArticleListPreview v-else-if="node.type === 'article-list'" :node="node" :style="style" />
  <NavigationPreview v-else-if="node.type === 'navigation'" :node="node" :style="style" />
  <BreadcrumbPreview v-else-if="node.type === 'breadcrumb'" :node="node" :style="style" />
  <GalleryPreview v-else-if="node.type === 'gallery'" :node="node" :style="style" />
  <GlobalPreview v-else-if="node.type === 'global'" :node="node" />
  <CustomPreview v-else-if="node.type === 'custom'" :node="node" :style="style" />
  <div v-else class="builder-preview-placeholder">
    {{ t('この種類のブロックは表示できません。') }}
  </div>
</template>
