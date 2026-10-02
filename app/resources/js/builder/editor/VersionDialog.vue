<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { BuilderVersionSummary } from '../types'

// 版の履歴の画面: 公開した内容の一覧(新しい順)から版を選び、今の下書きを置き換える(元に戻せる。公開側に出すには改めて公開する)
const store = useBuilderStore()

const versions = ref<BuilderVersionSummary[] | null>(null)
const restoring = ref<number | null>(null)

function close(): void {
  store.state.versionsOpen = false
}

function publishedAt(version: BuilderVersionSummary): string {
  return new Date(version.published_at).toLocaleString(document.documentElement.lang || undefined, {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function restore(version: BuilderVersionSummary): Promise<void> {
  const label = publishedAt(version)

  if (!window.confirm(t('今の下書きを :date の版の内容に置き換えます。よろしいですか?(元に戻すで戻せます)', { date: label }))) {
    return
  }

  restoring.value = version.id

  if (await store.restoreVersion(version.id, label)) {
    close()
  }

  restoring.value = null
}

onMounted(async () => {
  versions.value = await store.loadVersions()
})
</script>

<template>
  <div class="builder-dialog-backdrop" @click.self="close">
    <div class="builder-dialog" role="dialog" aria-modal="true" :aria-label="t('版の履歴')">
      <header class="builder-dialog-header">
        <h2 class="h6 mb-0">{{ t('版の履歴') }}</h2>
        <button type="button" class="btn-close" :aria-label="t('閉じる')" @click="close" />
      </header>

      <div class="builder-dialog-body">
        <p class="small text-secondary">
          {{ t('公開するたびに、公開した内容を版として残します。版を下書きに読み込んでから公開すると、その版に戻せます。') }}
        </p>
        <div v-if="versions === null" class="small text-secondary">{{ t('読み込んでいます...') }}</div>
        <p v-else-if="versions.length === 0" class="small text-secondary">{{ t('まだ公開した版がありません。') }}</p>
        <div v-else class="builder-template-list">
          <div v-for="version in versions" :key="version.id" class="builder-template-item">
            <div class="flex-grow-1" style="min-width: 0">
              <div class="fw-semibold">
                {{ publishedAt(version) }}
                <span v-if="version.current" class="badge text-bg-success ms-1">{{ t('公開中') }}</span>
              </div>
              <div class="small text-secondary">
                {{ version.administrator ?? t('(公開した管理者は不明)') }} ・ {{ t(':count 個のブロック', { count: version.node_count }) }}
              </div>
            </div>
            <div class="flex-shrink-0">
              <button type="button" class="btn btn-sm btn-outline-primary" :disabled="restoring !== null" @click="restore(version)">
                {{ t('下書きに読み込む') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
