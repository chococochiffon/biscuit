<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { availableSectionPresets, type SectionPreset } from '../sections'
import { useBuilderStore } from '../store'

// セクションのひな形を選ぶ画面: 「+ セクションを追加」で開き、選んだひな形のセクションを開いたときの位置に置く(元に戻せる)
const store = useBuilderStore()

const presets = computed(() => availableSectionPresets(store.state.registry))

function close(): void {
  store.state.sectionInsertIndex = null
}

function use(preset: SectionPreset): void {
  if (store.state.sectionInsertIndex !== null) {
    store.addSection(preset.key, store.state.sectionInsertIndex)
  }
  close()
}
</script>

<template>
  <div class="builder-dialog-backdrop" @click.self="close">
    <div class="builder-dialog" role="dialog" aria-modal="true" :aria-label="t('セクションを追加')">
      <header class="builder-dialog-header">
        <h2 class="h6 mb-0">{{ t('セクションを追加') }}</h2>
        <button type="button" class="btn-close" :aria-label="t('閉じる')" @click="close" />
      </header>

      <div class="builder-dialog-body">
        <p class="small text-secondary">{{ t('ひな形を選ぶと、中身の入ったセクションを置きます。文字や画像はあとから書き換えてください。') }}</p>
        <div class="builder-section-presets">
          <button v-for="preset in presets" :key="preset.key" type="button" class="builder-section-preset" @click="use(preset)">
            <i class="bi" :class="preset.icon" />
            <span class="fw-semibold">{{ preset.label }}</span>
            <span class="small text-secondary">{{ preset.description }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
