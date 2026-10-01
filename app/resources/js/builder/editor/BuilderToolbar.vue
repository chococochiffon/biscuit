<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'

// 上のツールバー: 戻る・ページ名・公開の状態・メッセージと、下書き保存・公開・変更の破棄
defineProps<{
  backUrl: string
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

function publish(): void {
  if (window.confirm(t('今の内容を公開します。よろしいですか?'))) {
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
      <span v-if="state.dirty" class="small text-secondary ms-2">{{ t('保存していない変更があります') }}</span>
    </div>
    <div
      v-if="state.message"
      class="builder-toolbar-message small"
      :class="`text-${state.message.type}`"
      role="status"
    >
      {{ state.message.text }}
    </div>
    <div class="builder-toolbar-actions">
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
