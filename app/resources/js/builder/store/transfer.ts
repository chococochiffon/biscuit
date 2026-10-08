import { t } from '../i18n'
import { cloneWithNewIds } from '../nodes'
import { formatDateTime } from '../visibility'
import type { BuilderImportResult, BuilderTemplate, BuilderVersionSummary } from '../types'
import { errorMessage, type EditorContext } from './context'

/**
 * 内容の置き換えと持ち出し: テンプレート・版の履歴・ファイルの書き出しと読み込み。置き換えはどれも元に戻せる。
 */
export function transferActions(context: EditorContext) {
  const { api, state, replaceContent, handleError } = context

  /**
   * 失敗したら fallback の文言を出して null を返す読み込み(一覧など)。
   */
  async function loadOrNull<T>(request: () => Promise<T>, fallback: string): Promise<T | null> {
    try {
      return await request()
    }
    catch (error) {
      handleError(error, fallback)

      return null
    }
  }

  /**
   * テンプレートの内容で今の内容を置き換える。ブロックには新しい ID を振る。
   */
  function applyTemplate(template: BuilderTemplate): void {
    replaceContent({ ...template.content, children: template.content.children.map(cloneWithNewIds) }, t('テンプレート「:name」を使いました。', { name: template.name }))
  }

  function loadTemplates(): Promise<BuilderTemplate[] | null> {
    return loadOrNull(() => api.templates(), t('テンプレートを読み込めませんでした。'))
  }

  /**
   * 今の内容をテンプレートとして保存する。保存できなければ、理由の文言を返す。
   */
  async function saveAsTemplate(name: string, description: string): Promise<string | null> {
    try {
      await api.saveTemplate(name, description, state.content)

      return null
    }
    catch (error) {
      return errorMessage(error, t('テンプレートの保存に失敗しました。'))
    }
  }

  async function deleteTemplate(id: number): Promise<boolean> {
    try {
      await api.deleteTemplate(id)

      return true
    }
    catch (error) {
      handleError(error, t('テンプレートの削除に失敗しました。'))

      return false
    }
  }

  function loadVersions(): Promise<BuilderVersionSummary[] | null> {
    return loadOrNull(() => api.versions(), t('版の履歴を読み込めませんでした。'))
  }

  /**
   * 版の内容で今の下書きを置き換える(公開側に出すには改めて公開する)。読み込めたら true。
   */
  async function restoreVersion(id: number, label: string): Promise<boolean> {
    const version = await loadOrNull(() => api.version(id), t('版を読み込めませんでした。'))

    if (!version) {
      return false
    }

    replaceContent(version.content, t(':date の版を下書きに読み込みました。公開側に出すには「公開」を押してください。', { date: label }))

    return true
  }

  /**
   * 今の内容(保存していない変更を含む)を、画像・グローバルコンポーネントの中身と一緒にファイルに書き出す(ブラウザでダウンロードする)。
   * 書き出せなければ、理由の文言を返す。
   */
  async function exportFile(): Promise<string | null> {
    try {
      const file = await api.exportContent(state.content, state.page?.title ?? '')
      const title = (state.page?.title ?? 'page').replace(/[\\/:*?"<>|\s]+/g, '-')
      const stamp = formatDateTime(state.timezone).replace(/[-:]/g, '').replace('T', '-')
      const link = document.createElement('a')
      link.href = URL.createObjectURL(new Blob([JSON.stringify(file, null, 2)], { type: 'application/json' }))
      link.download = `builder-${title}-${stamp}.json`
      link.click()
      URL.revokeObjectURL(link.href)

      return null
    }
    catch (error) {
      return errorMessage(error, t('書き出しに失敗しました。'))
    }
  }

  /**
   * 書き出したファイルを読み込み、今の内容を置き換える。読み込めなければ、理由の文言を投げる。
   */
  async function importFile(file: File): Promise<BuilderImportResult> {
    let result: BuilderImportResult

    try {
      result = await api.importFile(file, state.page?.type === 'component' ? state.page.kind ?? 'global' : 'page')
    }
    catch (error) {
      throw new Error(errorMessage(error, t('読み込みに失敗しました。')))
    }

    replaceContent(result.content, t('ファイル「:name」を読み込みました。', { name: file.name }))

    return result
  }

  return { applyTemplate, loadTemplates, saveAsTemplate, deleteTemplate, loadVersions, restoreVersion, exportFile, importFile }
}
