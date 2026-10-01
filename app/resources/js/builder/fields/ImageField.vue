<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'

// 画像の項目。画像を選ぶとアップロードし、保存したパス(image/builder/...)を値にする
const props = defineProps<{
  id: string
  modelValue: string | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string | null]
}>()

const store = useBuilderStore()
const uploading = ref(false)
const previewUrl = computed(() => store.imageUrl(props.modelValue))

async function upload(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) {
    return
  }

  uploading.value = true
  const path = await store.uploadImage(file)
  uploading.value = false
  input.value = ''

  if (path) {
    emit('update:modelValue', path)
  }
}
</script>

<template>
  <div>
    <img v-if="previewUrl" :src="previewUrl" alt="" class="img-thumbnail d-block mb-2 builder-image-field-preview">
    <div class="d-flex gap-2 align-items-center">
      <label :for="id" class="btn btn-sm btn-outline-secondary mb-0" :class="{ disabled: uploading }">
        {{ uploading ? t('アップロードしています...') : t('画像を選ぶ') }}
      </label>
      <input :id="id" type="file" accept="image/*" class="d-none" :disabled="uploading" @change="upload">
      <button v-if="modelValue" type="button" class="btn btn-sm btn-link text-danger p-0" @click="emit('update:modelValue', null)">
        {{ t('画像を外す') }}
      </button>
    </div>
  </div>
</template>
