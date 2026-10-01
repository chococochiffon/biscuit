<script setup lang="ts">
import { ref } from 'vue'
import { t } from '../i18n'
import BuilderPalette from './BuilderPalette.vue'
import ComponentTree from './ComponentTree.vue'

// 左のパネル: ブロックのパレットと、ページの構造を見るコンポーネントツリーをタブで切り替える
const tab = ref<'palette' | 'tree'>('palette')
</script>

<template>
  <aside class="builder-palette">
    <ul class="nav nav-tabs nav-fill small mb-3">
      <li class="nav-item">
        <button type="button" class="nav-link" :class="{ active: tab === 'palette' }" @click="tab = 'palette'">
          <i class="bi bi-plus-square" /> {{ t('ブロック') }}
        </button>
      </li>
      <li class="nav-item">
        <button type="button" class="nav-link" :class="{ active: tab === 'tree' }" @click="tab = 'tree'">
          <i class="bi bi-diagram-3" /> {{ t('ツリー') }}
        </button>
      </li>
    </ul>
    <BuilderPalette v-if="tab === 'palette'" />
    <ComponentTree v-else />
  </aside>
</template>
