<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { Device } from '../types'

// 上のツールバー: 戻る・ページ名・公開の状態・保存の状態・メッセージ、元に戻す/やり直す、端末の切り替え、
// テンプレート・版の履歴・書き出しと読み込み・プレビュー・下書き保存・公開・変更の破棄
defineProps<{
  backUrl: string
  // プレビューを開けるか(グローバルコンポーネントのエディタにはない)
  canPreview: boolean
}>()

const store = useBuilderStore()
const state = store.state

const status = computed(() => {
  if (!state.published) {
    return { label: t('未公開'), color: 'secondary' }
  }

  return state.hasUnpublishedChanges
    ? { label: t('公開中(未公開の変更あり)'), color: 'warning' }
    : { label: t('公開中'), color: 'success' }
})

const DEVICES: { device: Device, icon: string, label: string }[] = [
  { device: 'desktop', icon: 'bi-display', label: t('デスクトップ') },
  { device: 'tablet', icon: 'bi-tablet', label: t('タブレット') },
  { device: 'mobile', icon: 'bi-phone', label: t('スマートフォン') },
]

const savedAt = computed(() =>
  state.lastSavedAt ? state.lastSavedAt.toLocaleTimeString(document.documentElement.lang || undefined, { hour: '2-digit', minute: '2-digit' }) : null,
)

/**
 * プレビューを新しいタブで開く。保存などを待ってから開くとポップアップを止められるため、先に空のタブを開いておく。
 */
async function openPreview(): Promise<void> {
  const tab = window.open('', '_blank')
  const url = await store.previewUrl()

  if (url && tab) {
    tab.location.href = url
  }
  else {
    tab?.close()
  }
}

function publish(): void {
  const message = state.page?.type === 'component'
    ? t('今の内容を公開すると、このコンポーネントを使っているすべてのページに反映されます。よろしいですか?')
    : t('今の内容を公開します。よろしいですか?')

  if (window.confirm(message)) {
    store.publish()
  }
}

function discard(): void {
  if (window.confirm(t('未公開の変更を破棄して、公開中の内容に戻します。よろしいですか?'))) {
    store.discard()
  }
}
</script>

<template>
  <header class="builder-toolbar">
    <a :href="backUrl" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left" /> {{ t('戻る') }}
    </a>
    <div class="builder-toolbar-title">
      <span class="fw-semibold">{{ state.page?.title }}</span>
      <span v-if="state.loaded" class="badge ms-2" :class="`text-bg-${status.color}`">{{ status.label }}</span>
      <span v-if="state.conflict" class="small text-danger ms-2">{{ t('自動保存を止めています') }}</span>
      <span v-else-if="state.dirty" class="small text-secondary ms-2">{{ t('保存していない変更があります') }}</span>
      <span v-else-if="savedAt" class="small text-secondary ms-2">{{ t(':time に保存しました', { time: savedAt }) }}</span>
    </div>
    <div
      v-if="state.message"
      class="builder-toolbar-message small"
      :class="`text-${state.message.type}`"
      role="status"
    >
      {{ state.message.text }}
    </div>
    <div class="btn-group btn-group-sm" role="group" :aria-label="t('元に戻す・やり直す')">
      <button type="button" class="btn btn-outline-secondary" :disabled="!state.canUndo" :title="t('元に戻す') + ' (Ctrl+Z)'" @click="store.undo()">
        <i class="bi bi-arrow-counterclockwise" />
      </button>
      <button type="button" class="btn btn-outline-secondary" :disabled="!state.canRedo" :title="t('やり直す') + ' (Ctrl+Shift+Z)'" @click="store.redo()">
        <i class="bi bi-arrow-clockwise" />
      </button>
    </div>
    <div class="btn-group btn-group-sm" role="group" :aria-label="t('端末')">
      <button
        v-for="item in DEVICES"
        :key="item.device"
        type="button"
        class="btn btn-outline-secondary"
        :class="{ active: state.device === item.device }"
        :title="item.label"
        :aria-pressed="state.device === item.device"
        @click="state.device = item.device"
      >
        <i class="bi" :class="item.icon" />
      </button>
    </div>
    <div class="builder-toolbar-actions">
      <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="!state.loaded" @click="state.templatesOpen = true">
        <i class="bi bi-files" /> {{ t('テンプレート') }}
      </button>
      <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="!state.loaded" @click="state.versionsOpen = true">
        <i class="bi bi-clock-history" /> {{ t('版の履歴') }}
      </button>
      <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="!state.loaded" @click="state.transferOpen = true">
        <i class="bi bi-arrow-left-right" /> {{ t('書き出し・読み込み') }}
      </button>
      <button v-if="canPreview" type="button" class="btn btn-sm btn-outline-secondary" :disabled="state.busy || !state.loaded" @click="openPreview">
        <i class="bi bi-eye" /> {{ t('プレビュー') }}
      </button>
      <button
        v-if="state.published && state.hasUnpublishedChanges"
        type="button"
        class="btn btn-sm btn-outline-danger"
        :disabled="state.busy"
        @click="discard"
      >
        {{ t('変更を破棄') }}
      </button>
      <button type="button" class="btn btn-sm btn-outline-primary" :disabled="state.busy || !state.loaded" @click="store.save()">
        {{ t('下書き保存') }}
      </button>
      <button type="button" class="btn btn-sm btn-primary" :disabled="state.busy || !state.loaded" @click="publish">
        {{ t('公開') }}
      </button>
    </div>
  </header>
</template>
