import { ApiError, type BuilderApi } from '../api'
import { createHistory } from '../history'
import { t } from '../i18n'
import { convertToFree, type Measure } from '../convert'
import { FREE_LAYOUT_VERSION, syncLayouts } from '../layout'
import { equalizeColumns, findNode } from '../nodes'
import type { BuilderContent, BuilderNode, BuilderStatePayload } from '../types'
import { createEditorState } from './state'

/**
 * API のエラーの文言(なければ fallback)。
 */
export function errorMessage(error: unknown, fallback: string): string {
  return error instanceof ApiError && error.data.message ? error.data.message : fallback
}

/**
 * store の各操作が共有する状態と、内部の処理(履歴を積む・API の応答を入れる・エラーを出す)。
 */
export function createEditorContext(api: BuilderApi) {
  const state = createEditorState()
  const history = createHistory()
  // v1 の内容を画面の外で描いて測る処理(ConversionStage が登録する。なければ測らずに変換する)
  let measurer: ((content: BuilderContent) => Promise<Measure>) | null = null

  function setMeasurer(value: ((content: BuilderContent) => Promise<Measure>) | null): void {
    measurer = value
  }

  /**
   * 行・カラムで流し込む配置(v1)の内容を、自由配置(v2)の内容にする(v2 の内容・v2 の定義がないエディタではそのまま返す)。
   * 今の見た目を保つため、画面の外で 1200px で描いて測った位置を使う(convert.ts)。
   */
  async function toFreeLayout(content: BuilderContent): Promise<BuilderContent> {
    const registry = state.registries[String(FREE_LAYOUT_VERSION)]

    if (content.version >= FREE_LAYOUT_VERSION || !registry) {
      return content
    }

    let measure: Measure = () => null

    if (measurer) {
      try {
        measure = await measurer(content)
      }
      catch {
        // 測れなければ、上から順に縦に並べる
      }
    }

    return convertToFree(content, registry, measure, hasFreeRoot())
  }

  function syncHistory(): void {
    state.canUndo = history.canUndo()
    state.canRedo = history.canRedo()
  }

  /**
   * 編集している内容の版の定義を使う(テンプレート・版の履歴・元に戻すなどで内容の版が変わることがある)。
   */
  function syncRegistry(): void {
    state.registry = state.registries[String(state.content.version)] ?? state.registry
  }

  /**
   * 独自コンポーネントの一番外側が自由配置の面か(独自コンポーネントのエディタだけ)。
   */
  function hasFreeRoot(): boolean {
    return state.page?.kind === 'custom'
  }

  /**
   * 内容を自由配置の決まりにそろえる(v2 の内容だけ。面の直下のブロックの位置・端末ごとの位置のそろいなど。layout.ts)。
   */
  function normalizeLayouts(): void {
    syncLayouts(state.content, state.registry, hasFreeRoot())
  }

  function changed(): void {
    state.dirty = true
    state.revision++
    state.hasUnpublishedChanges = true
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

    normalizeLayouts()
    history.record(snapshot, key)
    syncHistory()
    changed()

    return true
  }

  /**
   * すでに変えた内容を、snapshot(変える前の内容)から 1 回の操作として履歴に積む(ドラッグで大きさを変えたあとなど)。
   */
  function recordChange(snapshot: string): void {
    normalizeLayouts()
    history.record(snapshot, null)
    syncHistory()
    changed()
  }

  /**
   * 1 つのブロックを変える操作(項目・スタイル・表示条件など)。変えたら、そのブロックのエラーを消す。
   */
  function mutateNode(id: string, key: string, operation: (node: BuilderNode) => boolean): boolean {
    const node = findNode(state.content, id)

    if (!node || !mutate(key, () => operation(node))) {
      return false
    }

    delete state.errors[`nodes.${id}`]

    return true
  }

  /**
   * 履歴の内容に差し替える(元に戻す・やり直す)。
   */
  function restore(snapshot: string): void {
    state.content = JSON.parse(snapshot)
    syncRegistry()
    state.restoreCount++
    clearMissingSelection()
    syncHistory()
    changed()
  }

  /**
   * 選択中のブロックがなくなっていたら、選択を外す。
   */
  function clearMissingSelection(): void {
    if (state.selectedId && !findNode(state.content, state.selectedId)) {
      state.selectedId = null
    }
    if (state.editingId && !findNode(state.content, state.editingId)) {
      state.editingId = null
    }
  }

  /**
   * 行(row)にカラムを入れたら、行のカラムの幅をそろえる(ほかの操作と同じ 1 回の操作として元に戻せる)。
   */
  function equalizeIfAddedToRow(columnId: string, parentId: string | null): void {
    const parent = parentId === null ? null : findNode(state.content, parentId)

    if (parent?.type === 'row' && findNode(state.content, columnId)?.type === 'column') {
      equalizeColumns(parent, columnId)
    }
  }

  /**
   * API の応答の状態を入れる。replaceContent なら内容も置き換える(保存した状態になる)。
   */
  function applyState(payload: BuilderStatePayload, replaceContent: boolean): void {
    state.page = payload.page
    state.published = payload.published
    state.publishedAt = payload.published_at
    state.hasUnpublishedChanges = payload.has_unpublished_changes
    state.updatedAt = payload.updated_at

    if (replaceContent) {
      state.content = payload.content
      syncRegistry()
      state.dirty = false
      clearMissingSelection()
    }
  }

  /**
   * 内容を置き換える(テンプレート・版・ファイルの読み込み。元に戻せる)。選択を外して、message を出す。
   * 内容の版も置き換え、その版の定義に切り替える(v1 のテンプレートを使うと v1 のページになる)。
   * Custom CSS は書ける人のときだけ置き換える(ほかの管理者の保存では biscuit が今の CSS を保つため、画面だけ変わらないようにする)。
   */
  function replaceContent(content: Pick<BuilderContent, 'version' | 'children' | 'css'>, message: string): void {
    const { children, css } = content

    mutate(null, () => {
      state.content.version = content.version
      syncRegistry()
      state.content.children = children

      if (state.canEditCss) {
        if (css) {
          state.content.css = css
        }
        else {
          delete state.content.css
        }
      }

      return true
    })
    state.selectedId = null
    state.message = { type: 'success', text: message }
  }

  /**
   * API のエラーを画面に出す。検証のエラー(422)は該当のブロックを選び、ほかの管理者の保存(409)は自動保存を止める。
   */
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

    state.message = { type: 'danger', text: errorMessage(error, fallback) }
  }

  function definition(type: string) {
    return state.registry.blocks[type]
  }

  function selectedNode(): BuilderNode | null {
    return state.selectedId ? findNode(state.content, state.selectedId) : null
  }

  return { api, state, history, syncHistory, syncRegistry, hasFreeRoot, setMeasurer, toFreeLayout, markChanged: changed, mutate, recordChange, mutateNode, restore, equalizeIfAddedToRow, applyState, replaceContent, handleError, definition, selectedNode }
}

export type EditorContext = ReturnType<typeof createEditorContext>
