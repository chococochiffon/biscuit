<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { BuilderTemplate } from '../types'

// テンプレートの画面: テンプレートを選んで今の内容を置き換える(元に戻せる)・今の内容をテンプレートとして保存する・テンプレートを削除する
const store = useBuilderStore()

const templates = ref<BuilderTemplate[] | null>(null)
const name = ref('')
const description = ref('')
const saving = ref(false)
const saveError = ref<string | null>(null)
const saved = ref(false)

async function load(): Promise<void> {
  templates.value = await store.loadTemplates()
}

function close(): void {
  store.state.templatesOpen = false
}

function use(template: BuilderTemplate): void {
  if (store.state.content.children.length > 0 && !window.confirm(t('今の内容を「:name」の内容に置き換えます。よろしいですか?(元に戻すで戻せます)', { name: template.name }))) {
    return
  }

  store.applyTemplate(template)
  close()
}

async function remove(template: BuilderTemplate): Promise<void> {
  if (window.confirm(t('テンプレート「:name」を削除してよろしいですか?(このテンプレートから作ったページは変わりません)', { name: template.name })) && await store.deleteTemplate(template.id)) {
    await load()
  }
}

async function save(): Promise<void> {
  saving.value = true
  saved.value = false
  saveError.value = await store.saveAsTemplate(name.value.trim(), description.value.trim())
  saving.value = false

  if (saveError.value === null) {
    saved.value = true
    name.value = ''
    description.value = ''
    await load()
  }
}

onMounted(load)
</script>

<template>
  <div class="builder-dialog-backdrop" @click.self="close">
    <div class="builder-dialog" role="dialog" aria-modal="true" :aria-label="t('テンプレート')">
      <header class="builder-dialog-header">
        <h2 class="h6 mb-0">{{ t('テンプレート') }}</h2>
        <button type="button" class="btn-close" :aria-label="t('閉じる')" @click="close" />
      </header>

      <div class="builder-dialog-body">
        <p class="small text-secondary">{{ t('テンプレートを使うと、今の内容をテンプレートの内容に置き換えます。') }}</p>
        <div v-if="templates === null" class="small text-secondary">{{ t('読み込んでいます...') }}</div>
        <p v-else-if="templates.length === 0" class="small text-secondary">{{ t('テンプレートがありません。') }}</p>
        <div v-else class="builder-template-list">
          <div v-for="template in templates" :key="template.id" class="builder-template-item">
            <div class="flex-grow-1" style="min-width: 0">
              <div class="fw-semibold">{{ template.name }}</div>
              <div v-if="template.description" class="small text-secondary">{{ template.description }}</div>
              <div class="small text-secondary">{{ t(':count 個のブロック', { count: template.node_count }) }}</div>
            </div>
            <div class="d-flex gap-2 align-items-start flex-shrink-0">
              <button type="button" class="btn btn-sm btn-primary" @click="use(template)">{{ t('使う') }}</button>
              <button type="button" class="btn btn-sm btn-outline-danger" :title="t('削除')" @click="remove(template)">
                <i class="bi bi-trash" />
              </button>
            </div>
          </div>
        </div>

        <hr>

        <h3 class="builder-panel-heading">{{ t('今のページをテンプレートとして保存') }}</h3>
        <p v-if="store.state.content.children.length === 0" class="small text-secondary">
          {{ t('ブロックがないため、テンプレートにできません。') }}
        </p>
        <form v-else @submit.prevent="save">
          <div class="mb-2">
            <label for="template-name" class="form-label small mb-1">{{ t('テンプレートの名前') }}</label>
            <input id="template-name" v-model="name" type="text" class="form-control form-control-sm" maxlength="100" required>
          </div>
          <div class="mb-2">
            <label for="template-description" class="form-label small mb-1">{{ t('説明(任意)') }}</label>
            <input id="template-description" v-model="description" type="text" class="form-control form-control-sm" maxlength="255">
          </div>
          <div v-if="saveError" class="small text-danger mb-2">{{ saveError }}</div>
          <div v-if="saved" class="small text-success mb-2">{{ t('テンプレートとして保存しました。') }}</div>
          <button type="submit" class="btn btn-sm btn-outline-primary" :disabled="saving || name.trim() === ''">
            {{ t('テンプレートとして保存') }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
