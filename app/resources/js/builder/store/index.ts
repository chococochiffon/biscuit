import { inject, type InjectionKey } from 'vue'
import type { BuilderApi } from '../api'
import { createEditorContext } from './context'
import { editingActions } from './editing'
import { persistenceActions } from './persistence'
import { propertyActions } from './properties'
import { resourceActions } from './resources'
import { transferActions } from './transfer'

export type { EditorMessage } from './state'

// エディタ全体の状態と、その操作。部品は木を直接書き換えず、ここの操作だけを呼ぶ。
// 状態は state.ts、共通の内部処理は context.ts、操作は関心ごとのファイルに分けている

export function createBuilderStore(api: BuilderApi) {
  const context = createEditorContext(api)

  return {
    state: context.state,
    // エディタに出す機能(インストーラーのエディタでは一部を出さない)
    features: api.features,
    definition: context.definition,
    selectedNode: context.selectedNode,
    ...editingActions(context),
    ...propertyActions(context),
    ...persistenceActions(context),
    ...transferActions(context),
    ...resourceActions(context),
  }
}

export type BuilderStore = ReturnType<typeof createBuilderStore>

export const builderStoreKey: InjectionKey<BuilderStore> = Symbol('builderStore')

/**
 * 部品からエディタの状態を使う(BuilderApp が provide する)。
 */
export function useBuilderStore(): BuilderStore {
  const store = inject(builderStoreKey)

  if (!store) {
    throw new Error('builder store is not provided')
  }

  return store
}
