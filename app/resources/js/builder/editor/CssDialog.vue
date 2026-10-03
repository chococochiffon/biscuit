<script setup lang="ts">
import { computed, ref } from 'vue'
import { customCssErrors } from '../customCss'
import { t } from '../i18n'
import { useBuilderStore } from '../store'

// Custom CSS の画面(スーパー管理者だけ): このページ(コンポーネント)だけに効く CSS を書く。
// CSS はビルダーの部分(エディタは Canvas)の中にネストして効かせるため、ビルダーの外へは効かない。反映すると元に戻すで戻せる
const store = useBuilderStore()

const draft = ref(store.state.content.css ?? '')
const errors = computed(() => customCssErrors(draft.value))

function close(): void {
  store.state.cssOpen = false
}

function apply(): void {
  if (errors.value.length === 0) {
    store.updateCss(draft.value)
    close()
  }
}
</script>

<template>
  <div class="builder-dialog-backdrop" @click.self="close">
    <div class="builder-dialog" role="dialog" aria-modal="true" :aria-label="t('このページの CSS')">
      <header class="builder-dialog-header">
        <h2 class="h6 mb-0">{{ t('このページの CSS') }}</h2>
        <button type="button" class="btn-close" :aria-label="t('閉じる')" @click="close" />
      </header>

      <div class="builder-dialog-body">
        <p class="small text-secondary">
          {{ t('このページだけに効く CSS です(ビルダーの部分の外へは効きません)。サイト共通の CSS はテーマの画面で書けます。ブロックにはスタイルのタブで追加のクラス名を付けられます。') }}
        </p>
        <textarea
          v-model="draft"
          class="form-control font-monospace small"
          :class="{ 'is-invalid': errors.length > 0 }"
          rows="14"
          spellcheck="false"
          :aria-label="t('このページの CSS')"
          placeholder=".my-card { border-radius: 12px; }"
        />
        <ul v-if="errors.length > 0" class="small text-danger mt-2 mb-0 ps-3">
          <li v-for="error in errors" :key="error">{{ error }}</li>
        </ul>
        <p class="small text-secondary mt-2 mb-3">
          {{ t('@import・サイト外の url()・< と \\ は使えず、@keyframes・@font-face と body・html への指定は効きません。') }}
        </p>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-primary" :disabled="errors.length > 0" @click="apply">{{ t('反映する') }}</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" @click="close">{{ t('キャンセル') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
