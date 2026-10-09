<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { normalizeInlineText } from '../inline'
import { useBuilderStore } from '../store'
import type { BuilderNode } from '../types'

// Canvas の上で、見出し・ボタンの 1 行の文字を直接書き換える(contenteditable)。
// 打つたびに項目へ入れ(続けた入力は 1 回の操作にまとまる)、Enter・ほかへのクリックで終え、Esc で書き換える前に戻して終える。
// 入力中に Vue が中身を描き直すと文字の位置(カーソル)が飛ぶため、中身は最初に一度だけ入れる
const props = defineProps<{
  node: BuilderNode
  prop: string
  tag: string
}>()

const store = useBuilderStore()
const element = ref<HTMLElement | null>(null)
const original = typeof props.node.props[props.prop] === 'string' ? props.node.props[props.prop] as string : ''

onMounted(() => {
  const el = element.value!
  el.textContent = original
  el.focus()

  // 文字をすべて選んだ状態で始める(そのまま打てば置き換わる)
  const range = document.createRange()
  range.selectNodeContents(el)
  const selection = window.getSelection()
  selection?.removeAllRanges()
  selection?.addRange(range)
})

function update(): void {
  const el = element.value!
  const text = normalizeInlineText(el.textContent ?? '', store.definition(props.node.type), props.prop)

  if (text !== el.textContent) {
    el.textContent = text
  }
  store.updateProp(props.node.id, props.prop, text)
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter') {
    event.preventDefault()
    element.value?.blur()
  }
  else if (event.key === 'Escape') {
    event.preventDefault()
    store.updateProp(props.node.id, props.prop, original)
    store.finishInlineEdit()
  }
}

// 貼り付けは書式を外した文字だけにする
function onPaste(event: ClipboardEvent): void {
  event.preventDefault()
  const text = event.clipboardData?.getData('text/plain') ?? ''
  document.execCommand('insertText', false, text.replace(/[\r\n]+/g, ' '))
}
</script>

<template>
  <component
    :is="tag"
    ref="element"
    class="builder-inline-text"
    contenteditable="plaintext-only"
    spellcheck="false"
    @input="update"
    @keydown.stop="onKeydown"
    @paste="onPaste"
    @blur="store.finishInlineEdit()"
    @click.stop
    @dblclick.stop
  />
</template>
