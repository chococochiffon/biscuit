<script setup lang="ts">
import Quill from 'quill'
import { onBeforeUnmount, onMounted, ref } from 'vue'

// テキストのブロックの本文のリッチテキストエディタ(Quill)。公開側で残る書式(biscuit の HtmlSanitizer::clean())だけをツールバーに出す
const props = defineProps<{
  id: string
  modelValue: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const editorElement = ref<HTMLElement | null>(null)
let quill: Quill | null = null

onMounted(() => {
  if (!editorElement.value) {
    return
  }

  quill = new Quill(editorElement.value, {
    theme: 'snow',
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

  quill.clipboard.dangerouslyPasteHTML(props.modelValue)
  quill.root.id = props.id
  quill.on('text-change', () => {
    emit('update:modelValue', quill?.getText().trim() === '' ? '' : quill?.root.innerHTML ?? '')
  })
})

onBeforeUnmount(() => {
  quill = null
})
</script>

<template>
  <div class="builder-richtext">
    <div ref="editorElement" />
  </div>
</template>
