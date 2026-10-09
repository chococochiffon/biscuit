<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { hasDeviceLayout, isStacked } from '../layout'
import { findLocation } from '../nodes'
import { useBuilderStore } from '../store'
import type { BuilderNode, LayoutBox } from '../types'

// 自由配置のブロックの位置と大きさ(選んでいる端末の値)。左端・幅は面の幅に対する %、上端・高さは px。
// タブレット・スマートフォンで、その端末だけの位置を持つ面では「自動に戻す」で端末の位置を外せる
// (タブレットはデスクトップの位置、スマートフォンは縦 1 列に戻る)。スマートフォンで縦 1 列のあいだは数値を出さない
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()

const device = computed(() => store.state.device)
const parentId = computed(() => findLocation(store.state.content, props.node.id)?.parent?.id ?? null)
const siblings = computed(() => store.surfaceChildren(parentId.value))
const stacked = computed(() => isStacked(siblings.value, device.value))
const inherited = computed(() => device.value === 'tablet' && !hasDeviceLayout(siblings.value, 'tablet'))
const box = computed<LayoutBox | null>(() => (device.value === 'desktop' ? props.node.layout?.desktop : props.node.layout?.[device.value]) ?? null)
const allowsHeight = computed(() => store.definition(props.node.type)?.layoutHeight === true)

function update(name: keyof LayoutBox, event: Event): void {
  const value = (event.target as HTMLInputElement).value

  if (name === 'h' && value === '') {
    store.updateLayout(props.node.id, device.value, { h: undefined })

    return
  }

  const number = Number(value)

  if (value !== '' && Number.isFinite(number)) {
    store.updateLayout(props.node.id, device.value, { [name]: number })
  }
}

function reset(): void {
  if (device.value !== 'desktop') {
    store.resetDeviceLayout(parentId.value, device.value)
  }
}
</script>

<template>
  <section class="mb-3">
    <h3 class="builder-panel-heading">{{ t('位置と大きさ') }}</h3>
    <p v-if="stacked" class="small text-secondary mb-0">
      {{ t('スマートフォンでは、デスクトップの上から順に縦 1 列に並べています。ブロックをドラッグすると、スマートフォンだけの位置になります。') }}
    </p>
    <p v-else-if="inherited" class="small text-secondary mb-0">
      {{ t('タブレットでは、デスクトップの位置を縮めて使っています。ブロックをドラッグすると、タブレットだけの位置になります。') }}
    </p>
    <template v-else-if="box">
      <div class="builder-layout-fields">
        <label class="small">
          {{ t('左端(%)') }}
          <input type="number" class="form-control form-control-sm" step="0.01" min="0" max="100" :value="box.x" @change="update('x', $event)">
        </label>
        <label class="small">
          {{ t('上端(px)') }}
          <input type="number" class="form-control form-control-sm" step="1" min="0" :value="box.y" @change="update('y', $event)">
        </label>
        <label class="small">
          {{ t('幅(%)') }}
          <input type="number" class="form-control form-control-sm" step="0.01" min="1" max="100" :value="box.w" @change="update('w', $event)">
        </label>
        <label v-if="allowsHeight" class="small">
          {{ t('高さ(px)') }}
          <input type="number" class="form-control form-control-sm" step="1" min="1" :value="box.h ?? ''" :placeholder="t('自動')" @change="update('h', $event)">
        </label>
      </div>
      <button v-if="device !== 'desktop'" type="button" class="btn btn-sm btn-link px-0" @click="reset">
        {{ device === 'tablet' ? t('このまとまりのタブレットの位置を自動に戻す') : t('このまとまりのスマートフォンの位置を自動に戻す') }}
      </button>
    </template>
  </section>
</template>
