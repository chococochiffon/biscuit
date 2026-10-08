import { t } from '../i18n'
import { FREE_LAYOUT_VERSION } from '../layout'
import { loadThemeFonts } from '../theme'
import type { EditorContext } from './context'

/**
 * biscuit との読み書き: 読み込み・下書きの保存・公開・変更の破棄・プレビューの URL。
 */
export function persistenceActions(context: EditorContext) {
  const { api, state, history, syncHistory, applyState, handleError } = context

  async function load(): Promise<void> {
    try {
      const payload = await api.show()
      state.registry = payload.registry
      state.registries = payload.registries ?? { [String(payload.content.version)]: payload.registry }
      state.imageBaseUrl = payload.image_base_url
      state.breadcrumbs = payload.breadcrumbs
      state.galleryCategories = payload.gallery_categories
      state.timezone = payload.timezone
      state.theme = payload.theme
      state.canEditCss = payload.can_edit_css
      loadThemeFonts(payload.theme)
      applyState(payload, true)
      // 何も置いていないページは、自由配置(v2)で始める(v1 のページは v1 のまま編集する)
      if (state.content.children.length === 0 && state.registries[String(FREE_LAYOUT_VERSION)]) {
        state.content.version = FREE_LAYOUT_VERSION
        context.syncRegistry()
      }
      state.loaded = true
    }
    catch {
      state.loadError = true
    }
  }

  /**
   * 下書きを保存する。手動の保存では、保存中に内容を変えていなければ biscuit が整えた内容(既定値の補完・HTML の無害化)に置き換える。
   * 自動保存(auto)では、入力中の欄の値が変わらないよう内容は置き換えず、メッセージも出さない(ツールバーに保存した時刻を出す)。
   */
  async function save(auto = false): Promise<boolean> {
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
  }

  /**
   * 未保存の変更があれば保存してから公開する。
   */
  async function publish(): Promise<void> {
    if (state.dirty && !(await save())) {
      return
    }

    state.busy = true

    try {
      applyState(await api.publish(state.updatedAt), true)
      state.errors = {}
      state.message = { type: 'success', text: publishedMessage() }
    }
    catch (error) {
      handleError(error, t('公開に失敗しました。'))
    }
    finally {
      state.busy = false
    }
  }

  /**
   * 公開したときの文言(公開側に出す設定になっていなければ、設定のしかたを添える)。
   */
  function publishedMessage(): string {
    if (state.page?.type === 'component') {
      return t('公開しました。このコンポーネントを使っているページに反映しました。')
    }

    if (state.page?.use_builder) {
      return t('公開しました。')
    }

    return state.page?.type === 'top'
      ? t('公開しました。公開側に表示するには、サイト設定の「トップでページビルダーを使う」を「使う」にしてください。')
      : t('公開しました。公開側に表示するには、固定ページの「ページの中身」を「ページビルダーで表示する」にしてください。')
  }

  /**
   * 下書きを公開中の内容に戻す(元に戻せる)。
   */
  async function discard(): Promise<void> {
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
  }

  /**
   * 未保存の変更があれば保存してから、下書きのプレビューの URL(chococo の /builder-preview)を返す。
   */
  async function previewUrl(): Promise<string | null> {
    if ((state.dirty || state.updatedAt === null) && !(await save())) {
      return null
    }

    try {
      return (await api.previewUrl()).url
    }
    catch (error) {
      handleError(error, t('プレビューを開けませんでした。'))

      return null
    }
  }

  return { load, save, publish, discard, previewUrl }
}
