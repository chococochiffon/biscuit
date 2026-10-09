<script setup lang="ts">
import Quill from 'quill'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useBuilderStore } from '../store'
import type { BuilderNode } from '../types'

// Canvas の上で、テキストのブロックの本文を直接書き換える(Quill の bubble。文字を選ぶと書式のバーが浮かぶ)。
// 書式は右のパネルと同じく、公開側で残るもの(biscuit の HtmlSanitizer::clean())だけを出す。
// 書式のバーのリンクの入力欄に移っても終えないよう、終えるのはほかのブロックを選んだとき・Canvas の外のクリック・Esc
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()
const element = ref<HTMLElement | null>(null)
let quill: Quill | null = null

onMounted(() => {
  quill = new Quill(element.value!, {
    theme: 'bubble',
    // 書式のバーを Canvas の中に収める(左右のパネルの下に隠れないように)
    bounds: element.value!.closest<HTMLElement>('.builder-canvas') ?? document.body,
    modules: {
      toolbar: [
        ['bold', 'italic', 'underline', 'strike'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        [{ align: [] }],
        ['link'],
        ['clean'],
      ],
    },
  })

  quill.clipboard.dangerouslyPasteHTML(typeof props.node.props.html === 'string' ? props.node.props.html : '')
  quill.on('text-change', () => {
    store.updateProp(props.node.id, 'html', quill?.getText().trim() === '' ? '' : quill?.root.innerHTML ?? '')
  })
  quill.root.classList.add('rich-content')
  quill.root.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      event.preventDefault()
      store.finishInlineEdit()
    }
  })
  quill.focus()
  quill.setSelection(quill.getLength(), 0)
})

onBeforeUnmount(() => {
  quill = null
})
</script>

<template>
  <div class="builder-inline-richtext" @click.stop @dblclick.stop>
    <div ref="element" />
  </div>
</template>
