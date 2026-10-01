<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'

// 左のパレット: ブロックの種類の一覧。Canvas へドラッグして置くか、クリックで選択中のブロックの中(または後ろ)に置く
const store = useBuilderStore()

const groups = computed(() => {
  const blocks = Object.entries(store.state.registry.blocks)

  return [
    { label: t('レイアウト'), items: blocks.filter(([, block]) => block.category === 'layout') },
    { label: t('基本'), items: blocks.filter(([, block]) => block.category === 'basic') },
  ]
})

function startDrag(event: DragEvent, type: string): void {
  event.dataTransfer?.setData('text/plain', type)
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'copy'
  }
  store.state.dragging = { kind: 'new', type }
}
</script>

<template>
  <aside class="builder-palette">
    <section v-for="group in groups" :key="group.label" class="mb-3">
      <h2 class="builder-panel-heading">{{ group.label }}</h2>
      <div class="builder-palette-items">
        <button
          v-for="[type, block] in group.items"
          :key="type"
          type="button"
          class="builder-palette-item"
          draggable="true"
          :title="t('ドラッグして置くか、クリックで追加')"
          @dragstart="startDrag($event, type)"
          @dragend="store.endDrag()"
          @click="store.addNearSelection(type)"
        >
          <i class="bi" :class="`bi-${block.icon}`" />
          <span>{{ block.label }}</span>
        </button>
      </div>
    </section>
    <p class="small text-secondary">
      {{ t('ページの一番外側にはセクションを置き、その中にブロックを並べます。') }}
    </p>
  </aside>
</template>
