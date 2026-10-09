<script setup lang="ts">
import { computed, ref } from 'vue'
import { OPTION_LABELS, URL_PATTERN } from '../fields/options'
import { styleLabel } from '../fields/styleLabels'
import { t } from '../i18n'
import { colorInputValue, isBold, quickTools } from '../quickbar'
import { useBuilderStore } from '../store'
import { styleValueFor } from '../styles'
import type { BuilderNode } from '../types'

// 選んだブロックのすぐ上(右寄せ)に出す操作バー。右のパネルを開かずに、よく使う項目をその場で変える
// (見出しのレベル・ボタンの見た目・画像と背景画像の差し替え・背景色・揃え・太字・文字の色・リンク)。
// スタイルは右のパネルと同じく、選んでいる端末の値を変える。細かい設定は右のパネルで行う
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()

const definition = computed(() => store.definition(props.node.type))
const tools = computed(() => new Set(quickTools(props.node, definition.value)))
const style = (name: string) => styleValueFor(props.node, store.state.device, name).value
const text = (name: string) => (typeof props.node.props[name] === 'string' ? props.node.props[name] as string : '')

const ALIGNS = [
  { value: 'left', icon: 'bi-text-left', label: t('左揃え') },
  { value: 'center', icon: 'bi-text-center', label: t('中央揃え') },
  { value: 'right', icon: 'bi-text-right', label: t('右揃え') },
]

function setStyle(name: string, value: string | null): void {
  store.updateStyle(props.node.id, name, value)
}

function toggleAlign(value: string): void {
  setStyle('textAlign', style('textAlign') === value ? null : value)
}

function toggleBold(): void {
  setStyle('fontWeight', isBold(style('fontWeight')) ? '400' : '700')
}

// 画像の差し替え(画像の src・セクションの背景画像)
const uploading = ref(false)

async function upload(event: Event, prop: string): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''

  if (!file) {
    return
  }

  uploading.value = true
  const path = await store.uploadImage(file)
  uploading.value = false

  if (path) {
    store.updateProp(props.node.id, prop, path)
  }
}

// リンク先の入力(バーの下に出す)
const linkOpen = ref(false)
const linkDraft = ref('')
const linkInvalid = computed(() => linkDraft.value.trim() !== '' && !URL_PATTERN.test(linkDraft.value.trim()))

function openLink(): void {
  linkDraft.value = text('href')
  linkOpen.value = !linkOpen.value
}

function applyLink(): void {
  const href = linkDraft.value.trim()

  if (linkInvalid.value) {
    return
  }

  // 画像のリンクは外せる(null)。ボタンは空にせず # にする(ボタンの既定と同じ)
  store.updateProp(props.node.id, 'href', href !== '' ? href : (props.node.type === 'button' ? '#' : null))
  linkOpen.value = false
}
</script>

<template>
  <div v-if="tools.size > 0" class="builder-quickbar" role="toolbar" :aria-label="t('操作バー')" @click.stop @dblclick.stop @mousedown.stop>
    <select
      v-if="tools.has('level')"
      class="builder-quickbar-select"
      :title="t('見出しのレベル')"
      :value="node.props.level ?? 2"
      @change="store.updateProp(node.id, 'level', Number(($event.target as HTMLSelectElement).value))"
    >
      <option v-for="level in 6" :key="level" :value="level">H{{ level }}</option>
    </select>

    <select
      v-if="tools.has('variant')"
      class="builder-quickbar-select"
      :title="t('ボタンの見た目')"
      :value="text('variant') || 'primary'"
      @change="store.updateProp(node.id, 'variant', ($event.target as HTMLSelectElement).value)"
    >
      <option v-for="option in definition?.props.variant?.options ?? []" :key="option" :value="option">{{ OPTION_LABELS[option] ?? option }}</option>
    </select>

    <label v-if="tools.has('image')" class="builder-quickbar-button" :class="{ disabled: uploading }" :title="uploading ? t('アップロードしています...') : t('画像を差し替える')">
      <i class="bi" :class="uploading ? 'bi-hourglass-split' : 'bi-image'" />
      <input type="file" accept="image/*" class="d-none" :disabled="uploading" @change="upload($event, 'src')">
    </label>

    <template v-if="tools.has('backgroundImage')">
      <label class="builder-quickbar-button" :class="{ disabled: uploading }" :title="uploading ? t('アップロードしています...') : t('背景画像を変える')">
        <i class="bi" :class="uploading ? 'bi-hourglass-split' : 'bi-card-image'" />
        <input type="file" accept="image/*" class="d-none" :disabled="uploading" @change="upload($event, 'backgroundImage')">
      </label>
      <button v-if="node.props.backgroundImage" type="button" class="builder-quickbar-button" :title="t('背景画像を外す')" @click="store.updateProp(node.id, 'backgroundImage', null)">
        <i class="bi bi-x-square" />
      </button>
    </template>

    <label v-if="tools.has('background')" class="builder-quickbar-button" :title="styleLabel('backgroundColor')">
      <i class="bi bi-paint-bucket" />
      <span class="builder-quickbar-swatch" :style="{ background: style('backgroundColor') ? colorInputValue(style('backgroundColor')) : 'transparent' }" />
      <input type="color" class="builder-quickbar-color" :value="colorInputValue(style('backgroundColor'))" @input="setStyle('backgroundColor', ($event.target as HTMLInputElement).value)">
    </label>

    <template v-if="tools.has('align')">
      <button
        v-for="align in ALIGNS"
        :key="align.value"
        type="button"
        class="builder-quickbar-button"
        :class="{ active: style('textAlign') === align.value }"
        :title="align.label"
        :aria-pressed="style('textAlign') === align.value"
        @click="toggleAlign(align.value)"
      >
        <i class="bi" :class="align.icon" />
      </button>
    </template>

    <button v-if="tools.has('bold')" type="button" class="builder-quickbar-button" :class="{ active: isBold(style('fontWeight')) }" :title="t('太字')" @click="toggleBold">
      <i class="bi bi-type-bold" />
    </button>

    <label v-if="tools.has('color')" class="builder-quickbar-button" :title="styleLabel('color')">
      <i class="bi bi-fonts" />
      <span class="builder-quickbar-swatch" :style="{ background: colorInputValue(style('color')) }" />
      <input type="color" class="builder-quickbar-color" :value="colorInputValue(style('color'))" @input="setStyle('color', ($event.target as HTMLInputElement).value)">
    </label>

    <button v-if="tools.has('link')" type="button" class="builder-quickbar-button" :class="{ active: linkOpen }" :title="t('リンク先')" @click="openLink">
      <i class="bi bi-link-45deg" />
    </button>

    <form v-if="linkOpen" class="builder-quickbar-popover" @submit.prevent="applyLink">
      <input
        v-model="linkDraft"
        type="text"
        class="form-control form-control-sm"
        :class="{ 'is-invalid': linkInvalid }"
        placeholder="https://"
        :aria-label="t('リンク先')"
        @keydown.esc.stop="linkOpen = false"
      >
      <div v-if="linkInvalid" class="invalid-feedback">{{ t('https:// などで始まる URL か、/ で始まるサイト内のパスを入力してください。') }}</div>
      <div class="d-flex align-items-center gap-2 mt-2">
        <label v-if="'target' in (definition?.props ?? {})" class="form-check-label small me-auto">
          <input
            type="checkbox"
            class="form-check-input me-1"
            :checked="node.props.target === '_blank'"
            @change="store.updateProp(node.id, 'target', ($event.target as HTMLInputElement).checked ? '_blank' : '_self')"
          >
          {{ t('新しいタブで開く') }}
        </label>
        <button type="submit" class="btn btn-sm btn-primary ms-auto" :disabled="linkInvalid">{{ t('反映する') }}</button>
      </div>
    </form>
  </div>
</template>
