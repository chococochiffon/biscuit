// ページビルダーのエディタの入口(resources/views/admin/builder/edit.blade.php が読み込む)。
// 画面の data-config(戻り先と JSON の URL)を受け取って、Vue のエディタを描く
import { createApp } from 'vue'
import BuilderApp from './builder/editor/BuilderApp.vue'
import type { EditorConfig } from './builder/types'
import '../css/builder.css'

const element = document.getElementById('page-builder')

if (element?.dataset.config) {
  const config = JSON.parse(element.dataset.config) as EditorConfig
  createApp(BuilderApp, { config }).mount(element)
}
