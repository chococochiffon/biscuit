<script setup lang="ts">
import { computed } from 'vue'
import { exposedFields } from '../custom'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { BuilderNode } from '../types'
import PropField from './PropField.vue'

// 独自コンポーネントのブロックの「内容」: 部品の差し替えられる項目の入力欄(入力しなければ部品の値のまま)。
// 入力欄は部品の中身のブロックの項目の定義で作り、値はこのブロックの props.values に入れる
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()
store.loadComponents()

const component = computed(() => store.state.components?.find(item => item.id === props.node.props.component && item.kind === 'custom') ?? null)
const values = computed(() => (props.node.props.values as Record<string, unknown> | undefined) ?? {})
const fields = computed(() =>
  exposedFields(component.value?.content?.children ?? []).flatMap((field) => {
    const definition = store.state.registry.blocks[field.node.type]?.props[field.prop]

    return definition ? [{ ...field, definition: { ...definition, label: field.label } }] : []
  }),
)
</script>

<template>
  <section v-if="component" class="mt-3">
    <h3 class="builder-panel-heading">{{ t('差し替える項目') }}</h3>
    <p v-if="fields.length === 0" class="small text-secondary">
      {{ t('「:name」には差し替えられる項目がありません。部品のエディタで項目を選んで公開してください。', { name: component.name }) }}
    </p>
    <template v-else>
      <p class="small text-secondary">{{ t('入力しなければ、部品の値のままです。') }}</p>
      <PropField
        v-for="field in fields"
        :key="`${node.id}:${field.key}:${store.state.restoreCount}`"
        :node="field.node"
        :name="field.prop"
        :prop="field.definition"
        :override="{ value: values[field.key], fallback: field.node.props[field.prop], set: value => store.updateOverride(node.id, field.key, value) }"
      />
    </template>
  </section>
</template>
