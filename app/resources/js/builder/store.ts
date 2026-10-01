import { inject, reactive, type InjectionKey } from 'vue'
import { ApiError, type BuilderApi } from './api'
import { createHistory } from './history'
import { t } from './i18n'
import { canPlace, containsNode, createNode, findLocation, findNode, insertNode, moveNode, removeNode } from './nodes'
import { setNodeStyle } from './styles'
import type { BuilderContent, BuilderNode, BuilderStatePayload, Device, Dragging, DropTarget, PageInfo, Registry } from './types'

// エディタ全体の状態と、その操作。部品は木を直接書き換えず、ここの操作だけを呼ぶ

export interface EditorMessage {
  type: 'success' | 'danger' | 'warning'
  text: string
}

export function createBuilderStore(api: BuilderApi) {
  const state = reactive({
    loaded: false,
    loadError: false,
    page: null as PageInfo | null,
    registry: { rootChildren: [], blocks: {}, styles: {} } as Registry,
    imageBaseUrl: '',
    content: { version: 1, children: [] } as BuilderContent,
    device: 'desktop' as Device,
    selectedId: null as string | null,
    hoveredId: null as string | null,
    dragging: null as Dragging | null,
    dropTarget: null as DropTarget | null,
    published: false,
    publishedAt: null as string | null,
    hasUnpublishedChanges: false,
    updatedAt: null as string | null,
    // 最後に保存してから内容を変えたか
    dirty: false,
    // 内容を変えるたびに増やす(保存中に変えたかの判定に使う)
    revision: 0,
    busy: false,
    // 保存・公開で返ったエラー(ノードの ID → 文言。ノードを特定できないものは content)
    errors: {} as Record<string, string[]>,
    message: null as EditorMessage | null,
    canUndo: false,
    canRedo: false,
    // Undo/Redo で内容を差し替えるたびに増やす(入力欄を作り直して、差し替えた値を出すのに使う)
    restoreCount: 0,
    // 最後に保存した日時(自動保存の表示用)
    lastSavedAt: null as Date | null,
    // ほかの管理者が先に保存した(読み込み直すまで自動保存を止める)
    conflict: false,
  })

  const history = createHistory()

  function syncHistory(): void {
    state.canUndo = history.canUndo()
    state.canRedo = history.canRedo()
  }

  /**
   * 内容を変える操作を、取り消せるように履歴を積んでから行う。key は続けて同じ項目を変える操作をまとめるためのもの。
   * 操作が何も変えなかった(false を返した)ら履歴は積まない。
   */
  function mutate(key: string | null, operation: () => boolean): boolean {
    const snapshot = JSON.stringify(state.content)

    if (!operation()) {
      return false
    }

    history.record(snapshot, key)
    syncHistory()
    changed()

    return true
  }

  function restore(snapshot: string): void {
    state.content = JSON.parse(snapshot)
    state.restoreCount++

    if (state.selectedId && !findNode(state.content, state.selectedId)) {
      state.selectedId = null
    }

    syncHistory()
    changed()
  }

  function applyState(payload: BuilderStatePayload, replaceContent: boolean): void {
    state.page = payload.page
    state.published = payload.published
    state.publishedAt = payload.published_at
    state.hasUnpublishedChanges = payload.has_unpublished_changes
    state.updatedAt = payload.updated_at

    if (replaceContent) {
      state.content = payload.content
      state.dirty = false

      if (state.selectedId && !findNode(state.content, state.selectedId)) {
        state.selectedId = null
      }
    }
  }

  function changed(): void {
    state.dirty = true
    state.revision++
    state.hasUnpublishedChanges = true
  }

  function handleError(error: unknown, fallback: string): void {
    if (error instanceof ApiError && error.status === 422 && error.data.errors) {
      state.errors = error.data.errors
      const firstNode = Object.keys(error.data.errors).find(key => key.startsWith('nodes.'))
      if (firstNode) {
        state.selectedId = firstNode.slice('nodes.'.length)
      }
      state.message = { type: 'danger', text: error.data.message ?? fallback }

      return
    }

    if (error instanceof ApiError && error.status === 409) {
      state.conflict = true
      state.message = { type: 'danger', text: error.data.message ?? t('ほかの管理者が先に保存しました。画面を読み込み直してください。') }

      return
    }

    state.message = { type: 'danger', text: error instanceof ApiError && error.data.message ? error.data.message : fallback }
  }

  return {
    state,

    async load(): Promise<void> {
      try {
        const payload = await api.show()
        state.registry = payload.registry
        state.imageBaseUrl = payload.image_base_url
        applyState(payload, true)
        state.loaded = true
      }
      catch {
        state.loadError = true
      }
    },

    definition(type: string) {
      return state.registry.blocks[type]
    },

    selectedNode(): BuilderNode | null {
      return state.selectedId ? findNode(state.content, state.selectedId) : null
    },

    select(id: string | null): void {
      state.selectedId = id
    },

    /**
     * 選択中のブロックの親を選ぶ(ページの直下なら選択を外す)。
     */
    selectParent(): void {
      if (state.selectedId) {
        state.selectedId = findLocation(state.content, state.selectedId)?.parent?.id ?? null
      }
    },

    /**
     * 新しいブロックを親(null はページの直下)の index の位置に置き、選択する。
     */
    add(type: string, parentId: string | null, index: number): boolean {
      const node = createNode(state.registry, type)

      if (!mutate(null, () => insertNode(state.registry, state.content, parentId, index, node))) {
        return false
      }

      state.selectedId = node.id

      return true
    },

    /**
     * パレットのクリックで新しいブロックを置く。選択中のブロックの中(末尾)に置けなければその後ろ、どちらもだめならページの末尾。
     */
    addNearSelection(type: string): boolean {
      const selected = this.selectedNode()

      if (selected?.children && canPlace(state.registry, selected.type, type)) {
        return this.add(type, selected.id, selected.children.length)
      }

      const location = selected ? findLocation(state.content, selected.id) : null

      if (location && canPlace(state.registry, location.parent?.type ?? null, type)) {
        return this.add(type, location.parent?.id ?? null, location.index + 1)
      }

      if (canPlace(state.registry, null, type)) {
        return this.add(type, null, state.content.children.length)
      }

      state.message = { type: 'warning', text: t('「:block」は、選んでいるブロックの中や後ろには置けません。', { block: this.definition(type).label }) }

      return false
    },

    move(id: string, parentId: string | null, index: number): boolean {
      return mutate(null, () => moveNode(state.registry, state.content, id, parentId, index))
    },

    /**
     * 選択中のブロックを兄弟の中で 1 つ前・後ろへ動かす。
     */
    moveSelectedBy(offset: -1 | 1): void {
      const location = state.selectedId ? findLocation(state.content, state.selectedId) : null

      if (!location) {
        return
      }

      const index = location.index + offset

      if (index < 0 || index >= location.siblings.length) {
        return
      }

      this.move(location.siblings[location.index].id, location.parent?.id ?? null, offset > 0 ? index + 1 : index)
    },

    remove(id: string): void {
      if (mutate(null, () => removeNode(state.content, id) !== null) && state.selectedId && !findNode(state.content, state.selectedId)) {
        state.selectedId = null
      }
    },

    updateProp(id: string, name: string, value: unknown): void {
      const node = findNode(state.content, id)

      if (node && node.props[name] !== value) {
        mutate(`prop:${id}:${name}`, () => {
          node.props[name] = value

          return true
        })
        delete state.errors[`nodes.${id}`]
      }
    },

    /**
     * 選んでいる端末のスタイルを変える(デスクトップは styles、タブレット・スマートフォンは端末の上書き)。null なら指定を外す。
     */
    updateStyle(id: string, name: string, value: string | null): void {
      const node = findNode(state.content, id)

      if (node && mutate(`style:${id}:${state.device}:${name}`, () => setNodeStyle(node, state.device, name, value))) {
        delete state.errors[`nodes.${id}`]
      }
    },

    undo(): void {
      const previous = history.undo(JSON.stringify(state.content))

      if (previous !== null) {
        restore(previous)
      }
    },

    redo(): void {
      const next = history.redo(JSON.stringify(state.content))

      if (next !== null) {
        restore(next)
      }
    },

    /**
     * ドラッグ中のものを、親(null はページの直下)の中に置けるか。置いてあるブロックは自分の中へは置けない。
     */
    canDropInto(parentId: string | null): boolean {
      const dragging = state.dragging

      if (!dragging) {
        return false
      }

      const parentType = parentId === null ? null : findNode(state.content, parentId)?.type ?? null

      if (parentId !== null && parentType === null) {
        return false
      }

      if (dragging.kind === 'move' && parentId !== null) {
        const node = findNode(state.content, dragging.id)
        if (!node || containsNode(node, parentId)) {
          return false
        }
      }

      return canPlace(state.registry, parentType, dragging.type)
    },

    drop(): void {
      const { dragging, dropTarget } = state

      if (dragging && dropTarget) {
        if (dragging.kind === 'new') {
          this.add(dragging.type, dropTarget.parentId, dropTarget.index)
        }
        else if (this.move(dragging.id, dropTarget.parentId, dropTarget.index)) {
          state.selectedId = dragging.id
        }
      }

      this.endDrag()
    },

    endDrag(): void {
      state.dragging = null
      state.dropTarget = null
    },

    /**
     * 下書きを保存する。手動の保存では、保存中に内容を変えていなければ biscuit が整えた内容(既定値の補完・HTML の無害化)に置き換える。
     * 自動保存(auto)では、入力中の欄の値が変わらないよう内容は置き換えず、メッセージも出さない(ツールバーに保存した時刻を出す)。
     */
    async save(auto = false): Promise<boolean> {
      if (state.busy) {
        return false
      }

      state.busy = true
      const revision = state.revision

      try {
        const payload = await api.save(state.content, state.updatedAt)
        const unchanged = revision === state.revision
        applyState(payload, !auto && unchanged)
        if (unchanged) {
          state.dirty = false
        }
        state.lastSavedAt = new Date()
        state.errors = {}
        if (!auto) {
          state.message = { type: 'success', text: t('下書きを保存しました。') }
        }

        return true
      }
      catch (error) {
        handleError(error, t('下書きの保存に失敗しました。'))

        return false
      }
      finally {
        state.busy = false
      }
    },

    /**
     * 未保存の変更があれば保存してから公開する。
     */
    async publish(): Promise<void> {
      if (state.dirty && !(await this.save())) {
        return
      }

      state.busy = true

      try {
        applyState(await api.publish(state.updatedAt), true)
        state.errors = {}
        state.message = {
          type: 'success',
          text: state.page?.use_builder
            ? t('公開しました。')
            : state.page?.type === 'top'
              ? t('公開しました。公開側に表示するには、サイト設定の「トップでページビルダーを使う」を「使う」にしてください。')
              : t('公開しました。公開側に表示するには、固定ページの「ページの中身」を「ページビルダーで表示する」にしてください。'),
        }
      }
      catch (error) {
        handleError(error, t('公開に失敗しました。'))
      }
      finally {
        state.busy = false
      }
    },

    /**
     * 下書きを公開中の内容に戻す。
     */
    async discard(): Promise<void> {
      state.busy = true

      try {
        const snapshot = JSON.stringify(state.content)
        applyState(await api.discard(state.updatedAt), true)
        history.record(snapshot)
        syncHistory()
        state.errors = {}
        state.message = { type: 'success', text: t('公開中の内容に戻しました。') }
      }
      catch (error) {
        handleError(error, t('変更の破棄に失敗しました。'))
      }
      finally {
        state.busy = false
      }
    },

    /**
     * 未保存の変更があれば保存してから、下書きのプレビューの URL(chococo の /builder-preview)を返す。
     */
    async previewUrl(): Promise<string | null> {
      if ((state.dirty || state.updatedAt === null) && !(await this.save())) {
        return null
      }

      try {
        return (await api.previewUrl()).url
      }
      catch (error) {
        handleError(error, t('プレビューを開けませんでした。'))

        return null
      }
    },

    async uploadImage(file: File): Promise<string | null> {
      try {
        return (await api.uploadImage(file)).path
      }
      catch (error) {
        handleError(error, t('画像のアップロードに失敗しました。'))

        return null
      }
    },

    imageUrl(path: unknown): string | null {
      return typeof path === 'string' && path !== '' ? `${state.imageBaseUrl.replace(/\/$/, '')}/${path}` : null
    },
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
