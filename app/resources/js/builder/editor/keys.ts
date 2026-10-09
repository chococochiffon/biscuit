import { computed, inject, type ComputedRef, type InjectionKey } from 'vue'
import type { BuilderStore } from '../store'
import type { Device } from '../types'

// 描いている内容の版。ふだんは編集している内容の版で、コンポーネントの見本(GlobalPreview・CustomPreview)は中身の版を渡し直す
// (セクションの中を自由配置で描くかを決める。ページとコンポーネントの中身で版が違うことがある)
export const contentVersionKey: InjectionKey<ComputedRef<number>> = Symbol('contentVersion')

export function useContentVersion(store: BuilderStore): ComputedRef<number> {
  return inject(contentVersionKey, computed(() => store.state.content.version))
}

// 見本を描く端末。ふだんはツールバーで選んでいる端末で、v1 の内容を測って変換するとき(ConversionStage)はデスクトップに固定する
export const previewDeviceKey: InjectionKey<ComputedRef<Device>> = Symbol('previewDevice')

export function usePreviewDevice(store: BuilderStore): ComputedRef<Device> {
  return inject(previewDeviceKey, computed(() => store.state.device))
}
