<script setup lang="ts">
import { computed } from 'vue'
import { t } from '../i18n'
import { ancestorsOf } from '../nodes'
import { useBuilderStore } from '../store'
import PropField from '../fields/PropField.vue'

// 右のプロパティ: 選択中のブロックの内容(props)を、ブロックの定義から作った入力欄で編集する。
// 上には選択中のブロックまでの階層を出し、クリックで親のブロックを選べる
const store = useBuilderStore()

const node = computed(() => store.selectedNode())
const definition = computed(() => (node.value ? store.definition(node.value.type) : null))
const ancestors = computed(() => (node.value ? ancestorsOf(store.state.content, node.value.id) : []))
const errors = computed(() => (node.value ? store.state.errors[`nodes.${node.value.id}`] ?? [] : store.state.errors.content ?? []))
</script>

<template>
  <aside class="builder-properties">
    <div v-if="node && definition">
      <nav class="builder-properties-path small mb-2" :aria-label="t('階層')">
        <button type="button" class="btn btn-link btn-sm p-0" @click="store.select(null)">{{ t('ページ') }}</button>
        <template v-for="ancestor in ancestors" :key="ancestor.id">
          <span class="text-secondary mx-1">›</span>
          <button type="button" class="btn btn-link btn-sm p-0" @click="store.select(ancestor.id)">
            {{ store.definition(ancestor.type)?.label ?? ancestor.type }}
          </button>
        </template>
        <span class="text-secondary mx-1">›</span>
        <span class="fw-semibold">{{ definition.label }}</span>
      </nav>

      <div v-for="(error, index) in errors" :key="index" class="alert alert-danger small py-2">{{ error }}</div>

      <h2 class="builder-panel-heading">{{ t('内容') }}</h2>
      <PropField
        v-for="(prop, name) in definition.props"
        :key="`${node.id}:${name}`"
        :node="node"
        :name="String(name)"
        :prop="prop"
      />
      <p v-if="Object.keys(definition.props).length === 0" class="small text-secondary">
        {{ t('このブロックには入力する内容がありません。') }}
      </p>
    </div>
    <div v-else>
      <div v-for="(error, index) in errors" :key="index" class="alert alert-danger small py-2">{{ error }}</div>
      <p class="small text-secondary">{{ t('ブロックを選ぶと、ここで内容を編集できます。') }}</p>
    </div>
  </aside>
</template>
