import { setExposed, setOverride } from '../custom'
import { setNodeStyle } from '../styles'
import { formatDateTime, setVisibility } from '../visibility'
import type { BuilderVisibility } from '../types'
import type { EditorContext } from './context'

/**
 * ブロックとページの項目の変更(props・スタイル・クラス名・差し替えられる項目・表示条件・Custom CSS)。
 * どれも元に戻せ、続けて同じ項目を変える操作は 1 回にまとめる(mutate の key)。
 */
export function propertyActions(context: EditorContext) {
  const { state, mutate, mutateNode } = context

  function updateProp(id: string, name: string, value: unknown): void {
    mutateNode(id, `prop:${id}:${name}`, (node) => {
      if (node.props[name] === value) {
        return false
      }
      node.props[name] = value

      return true
    })
  }

  /**
   * 選んでいる端末のスタイルを変える(デスクトップは styles、タブレット・スマートフォンは端末の上書き)。null なら指定を外す。
   */
  function updateStyle(id: string, name: string, value: string | null): void {
    mutateNode(id, `style:${id}:${state.device}:${name}`, node => setNodeStyle(node, state.device, name, value))
  }

  /**
   * ブロックの追加のクラス名を変える(空なら消す)。
   */
  function updateClasses(id: string, classes: string[]): void {
    mutateNode(id, `classes:${id}`, (node) => {
      if (JSON.stringify(node.classes ?? []) === JSON.stringify(classes)) {
        return false
      }

      if (classes.length === 0) {
        delete node.classes
      }
      else {
        node.classes = classes
      }

      return true
    })
  }

  /**
   * 独自コンポーネントの中身のブロックの項目を、差し替えられる項目にする(表示名)・やめる(null)。
   */
  function updateExposed(id: string, prop: string, label: string | null): void {
    mutateNode(id, `exposed:${id}:${prop}`, node => setExposed(node, prop, label))
  }

  /**
   * 独自コンポーネントのブロックの差し替えた値を変える(null・空は部品の値のまま)。
   */
  function updateOverride(id: string, key: string, value: unknown): void {
    mutateNode(id, `override:${id}:${key}`, node => setOverride(node, key, value))
  }

  /**
   * 表示条件の一部を変える(すべての端末で表示しない・開始が終了より後になる変更はしない)。変えたら true。
   */
  function updateVisibility(id: string, patch: Partial<BuilderVisibility>): boolean {
    return mutateNode(id, `visibility:${id}:${Object.keys(patch).join(',')}`, node => setVisibility(node, patch))
  }

  /**
   * 今の日時(サイトのタイムゾーン。表示条件の期間と同じ形)。
   */
  function now(): string {
    return formatDateTime(state.timezone)
  }

  /**
   * ページ(コンポーネント)の Custom CSS を変える(空なら消す)。
   */
  function updateCss(css: string): void {
    const next = css.trim()

    mutate('css', () => {
      if ((state.content.css ?? '') === next) {
        return false
      }

      if (next === '') {
        delete state.content.css
      }
      else {
        state.content.css = next
      }

      return true
    })
    delete state.errors.content
  }

  return { updateProp, updateStyle, updateClasses, updateExposed, updateOverride, updateVisibility, now, updateCss }
}
