<script setup lang="ts">
import { ref } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { BuilderImportResult } from '../types'

// 書き出し・読み込みの画面: 今の内容を画像・グローバルコンポーネントの中身と一緒にファイルに書き出す・書き出したファイルを読み込んで今の内容を置き換える
// (元に戻せる)。読み込んだ結果(登録し直した画像の数・展開したコンポーネントなど)はこの画面に出す
const store = useBuilderStore()

const busy = ref(false)
const error = ref<string | null>(null)
const result = ref<BuilderImportResult | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

function close(): void {
  store.state.transferOpen = false
}

async function exportFile(): Promise<void> {
  busy.value = true
  error.value = await store.exportFile()
  busy.value = false
}

async function importFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''

  if (!file || (store.state.content.children.length > 0 && !window.confirm(t('今の内容を「:name」の内容に置き換えます。よろしいですか?(元に戻すで戻せます)', { name: file.name })))) {
    return
  }

  busy.value = true
  error.value = null
  result.value = null

  try {
    result.value = await store.importFile(file)
  }
  catch (exception) {
    error.value = exception instanceof Error ? exception.message : t('読み込みに失敗しました。')
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="builder-dialog-backdrop" @click.self="close">
    <div class="builder-dialog" role="dialog" aria-modal="true" :aria-label="t('書き出し・読み込み')">
      <header class="builder-dialog-header">
        <h2 class="h6 mb-0">{{ t('書き出し・読み込み') }}</h2>
        <button type="button" class="btn-close" :aria-label="t('閉じる')" @click="close" />
      </header>

      <div class="builder-dialog-body">
        <h3 class="builder-panel-heading">{{ t('ファイルに書き出す') }}</h3>
        <p class="small text-secondary">
          {{ t('今の内容(保存していない変更を含む)を、使っている画像・グローバルコンポーネントの中身と一緒に 1 つのファイル(JSON)に書き出します。別のサイトへ移すときやバックアップに使えます。') }}
        </p>
        <button type="button" class="btn btn-sm btn-outline-primary" :disabled="busy || store.state.content.children.length === 0" @click="exportFile">
          <i class="bi bi-download" /> {{ t('ファイルに書き出す') }}
        </button>

        <hr>

        <h3 class="builder-panel-heading">{{ t('ファイルから読み込む') }}</h3>
        <p class="small text-secondary">
          {{ t('書き出したファイルを読み込んで、今の内容を置き換えます(元に戻すで戻せます)。画像は登録し直し、同じ名前のグローバルコンポーネントがなければ中身をページに展開します。') }}
        </p>
        <input ref="fileInput" type="file" accept=".json,application/json" class="d-none" @change="importFile">
        <button type="button" class="btn btn-sm btn-outline-primary" :disabled="busy" @click="fileInput?.click()">
          <i class="bi bi-upload" /> {{ t('ファイルを選んで読み込む') }}
        </button>

        <div v-if="busy" class="small text-secondary mt-2">{{ t('処理しています...') }}</div>
        <div v-if="error" class="small text-danger mt-2" role="alert">{{ error }}</div>
        <div v-if="result" class="small mt-2" role="status">
          <div class="text-success">
            {{ t('読み込みました。') }}
            <template v-if="result.images > 0">{{ t('画像を :count 件登録しました。', { count: result.images }) }}</template>
          </div>
          <ul v-if="result.warnings.length" class="text-warning-emphasis mb-0 mt-1 ps-3">
            <li v-for="warning in result.warnings" :key="warning">{{ warning }}</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>
