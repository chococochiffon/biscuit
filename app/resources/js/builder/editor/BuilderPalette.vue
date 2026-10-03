<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'

// パレット(左のパネルの「ブロック」): ブロックの種類の一覧と、公開中の独自コンポーネント(部品ごと)。
// Canvas へドラッグして置くか、クリックで選択中のブロックの中(または後ろ)に置く
const store = useBuilderStore()

// 独自コンポーネントは、ブロックの種類(custom)ではなく公開中の部品ごとに並べる
const customComponents = computed(() =>
  'custom' in store.state.registry.blocks ? (store.state.components ?? []).filter(component => component.kind === 'custom' && component.published) : [],
)

if ('custom' in store.state.registry.blocks) {
  store.loadComponents()
}

const groups = computed(() => {
  const blocks = Object.entries(store.state.registry.blocks).filter(([type]) => type !== 'custom')

  return [
    { label: t('レイアウト'), items: blocks.filter(([, block]) => block.category === 'layout') },
    { label: t('基本'), items: blocks.filter(([, block]) => block.category === 'basic') },
    { label: t('コンテンツ'), items: blocks.filter(([, block]) => block.category === 'cms') },
  ].filter(group => group.items.length > 0)
})

function startDrag(event: DragEvent, type: string, props?: Record<string, unknown>): void {
  event.dataTransfer?.setData('text/plain', type)
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'copy'
  }
  store.state.dragging = { kind: 'new', type, props }
}
</script>

<template>
  <div>
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
    <section v-if="customComponents.length > 0" class="mb-3">
      <h2 class="builder-panel-heading">{{ t('独自コンポーネント') }}</h2>
      <div class="builder-palette-items">
        <button
          v-for="component in customComponents"
          :key="component.id"
          type="button"
          class="builder-palette-item"
          draggable="true"
          :title="t('ドラッグして置くか、クリックで追加')"
          @dragstart="startDrag($event, 'custom', { component: component.id })"
          @dragend="store.endDrag()"
          @click="store.addNearSelection('custom', { component: component.id })"
        >
          <i class="bi bi-boxes" />
          <span>{{ component.name }}</span>
        </button>
      </div>
    </section>
    <p class="small text-secondary">
      {{ t('ページの一番外側にはセクションを置き、その中にブロックを並べます。') }}
    </p>
  </div>
</template>
