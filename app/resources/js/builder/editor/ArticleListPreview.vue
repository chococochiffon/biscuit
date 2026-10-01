<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { ArticleSummary, BuilderNode } from '../types'

// 記事一覧のブロックの Canvas の見本。取得の条件どおりの公開中の記事を biscuit から取ってきて、公開側(chococo)に近い形で並べる。
// 条件を入力している途中で何度も取得しないよう、少し待ってから取得する
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()
const articles = ref<ArticleSummary[] | null>(null)

const condition = computed(() => JSON.stringify([props.node.props.limit, props.node.props.order, props.node.props.parentPath, props.node.props.tag]))
const layout = computed(() => (props.node.props.layout === 'list' ? 'list' : 'card'))
const showExcerpt = computed(() => props.node.props.showExcerpt !== false)
const showDate = computed(() => props.node.props.showDate !== false)

// カードの列数: デスクトップは columns、タブレットは 2 まで、スマートフォンは 1(公開側の row-cols と同じ)
const columns = computed(() => {
  const desktop = typeof props.node.props.columns === 'number' ? props.node.props.columns : 3

  return store.state.device === 'desktop' ? desktop : store.state.device === 'tablet' ? Math.min(2, desktop) : 1
})

let timer: ReturnType<typeof setTimeout> | undefined

watch(condition, () => {
  clearTimeout(timer)
  timer = setTimeout(async () => {
    articles.value = await store.articleListPreview(props.node.props)
  }, articles.value === null ? 0 : 400)
}, { immediate: true })

onBeforeUnmount(() => clearTimeout(timer))

function excerpt(html: string | null): string {
  const text = (html ?? '').replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim()

  return text.length > 60 ? `${text.slice(0, 60)}…` : text
}

function date(iso: string | null): string {
  return iso ? iso.slice(0, 10).replaceAll('-', '/') : ''
}
</script>

<template>
  <div>
    <div v-if="articles === null" class="builder-preview-placeholder">{{ t('読み込んでいます...') }}</div>
    <div v-else-if="articles.length === 0" class="builder-preview-placeholder">
      <i class="bi bi-newspaper" /> {{ t('条件に合う公開中の記事がありません。') }}
    </div>
    <div v-else-if="layout === 'card'" class="row g-3" :class="`row-cols-${columns}`">
      <div v-for="article in articles" :key="article.id" class="col">
        <div class="card h-100">
          <img v-if="article.thumbnail_url" :src="article.thumbnail_url" alt="" class="card-img-top builder-article-thumbnail">
          <div v-else class="builder-article-thumbnail bg-secondary-subtle" />
          <div class="card-body">
            <p class="card-text fw-semibold mb-1">{{ article.title }}</p>
            <p v-if="showExcerpt" class="card-text small text-secondary mb-1">{{ excerpt(article.content) }}</p>
            <small v-if="showDate" class="text-body-secondary">{{ date(article.published_at) }}</small>
          </div>
        </div>
      </div>
    </div>
    <ul v-else class="list-unstyled mb-0">
      <li v-for="article in articles" :key="article.id" class="border-bottom py-2">
        <small v-if="showDate" class="text-body-secondary me-2">{{ date(article.published_at) }}</small>
        <span class="fw-semibold">{{ article.title }}</span>
        <div v-if="showExcerpt" class="small text-secondary">{{ excerpt(article.content) }}</div>
      </li>
    </ul>
  </div>
</template>
