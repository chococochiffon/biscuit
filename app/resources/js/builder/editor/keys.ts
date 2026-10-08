import { computed, inject, type ComputedRef, type InjectionKey } from 'vue'
import type { BuilderStore } from '../store'

// 描いている内容の版。ふだんは編集している内容の版で、コンポーネントの見本(GlobalPreview・CustomPreview)は中身の版を渡し直す
// (セクションの中を自由配置で描くかを決める。ページとコンポーネントの中身で版が違うことがある)
export const contentVersionKey: InjectionKey<ComputedRef<number>> = Symbol('contentVersion')

export function useContentVersion(store: BuilderStore): ComputedRef<number> {
  return inject(contentVersionKey, computed(() => store.state.content.version))
}
