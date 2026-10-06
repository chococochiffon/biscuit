import { setNodeStyle } from './styles'
import type { BlockDefinition, BuilderNode, Device } from './types'

// Canvas で選んだブロックの端をドラッグして大きさを変える(画面から切り離した計算。resize.test.ts で確かめる)。
// 値は選んでいる端末に保存する(スタイルは styles・端末の上書き、カラムの幅は span・spanTablet・spanMobile)

// つまみの種類: 下の端(高さ)・右の端(幅)・上下の内側の余白
export type ResizeKind = 'height' | 'width' | 'paddingTop' | 'paddingBottom'

// 変える値: スタイル(px で保存)か、props の数値(スペーサーの高さ・カラムの幅)
export interface ResizeTarget {
  kind: ResizeKind
  source: 'style' | 'prop'
  name: string
}

// スペーサーの高さの範囲(biscuit の BlockRegistry と同じ)
export const SPACER_MAX_HEIGHT = 400
// スタイルの長さ(px)の上限(StyleRegistry の長さの形は 4 桁まで)
export const MAX_PIXELS = 9999

/**
 * ブロックで使えるつまみ(定義のスタイル・種類から決める)。
 */
export function resizeTargets(node: BuilderNode, definition: BlockDefinition | undefined): ResizeTarget[] {
  const styles = definition?.styles ?? []
  const targets: ResizeTarget[] = []

  if (node.type === 'spacer') {
    targets.push({ kind: 'height', source: 'prop', name: 'height' })
  }
  else if (styles.includes('minHeight')) {
    targets.push({ kind: 'height', source: 'style', name: 'minHeight' })
  }

  if (node.type === 'column') {
    targets.push({ kind: 'width', source: 'prop', name: 'span' })
  }
  else if (styles.includes('width')) {
    targets.push({ kind: 'width', source: 'style', name: 'width' })
  }
  else if (styles.includes('maxWidth')) {
    targets.push({ kind: 'width', source: 'style', name: 'maxWidth' })
  }

  for (const name of ['paddingTop', 'paddingBottom'] as const) {
    if (styles.includes(name)) {
      targets.push({ kind: name, source: 'style', name })
    }
  }

  return targets
}

/**
 * 長さを px のスタイルの値にする(整数に丸め、0〜MAX_PIXELS に収める)。
 */
export function pixels(value: number): string {
  return `${Math.min(MAX_PIXELS, Math.max(0, Math.round(value)))}px`
}

/**
 * カラムの幅(12 分割)を、行の幅に対するカラムの幅から決める(目盛りに吸い付かせ、1〜12 に収める)。
 */
export function spanFromWidth(width: number, rowWidth: number): number {
  if (rowWidth <= 0) {
    return 12
  }

  return Math.min(12, Math.max(1, Math.round((width / rowWidth) * 12)))
}

/**
 * 端末ごとのカラムの幅の項目(デスクトップは span、タブレットは spanTablet、スマートフォンは spanMobile)。
 */
export function spanProp(device: Device): string {
  return device === 'desktop' ? 'span' : device === 'tablet' ? 'spanTablet' : 'spanMobile'
}

/**
 * ドラッグで決めた値をブロックに入れる(スタイルは選んでいる端末、カラムの幅は端末の項目)。変わったら true。
 * value はスタイルなら px の長さ、スペーサーの高さなら px の数、カラムの幅なら 12 分割の数。
 */
export function applyResize(node: BuilderNode, device: Device, target: ResizeTarget, value: number): boolean {
  if (target.source === 'style') {
    return setNodeStyle(node, device, target.name, pixels(value))
  }

  const [name, next] = node.type === 'column'
    ? [spanProp(device), Math.min(12, Math.max(1, Math.round(value)))]
    : [target.name, Math.min(SPACER_MAX_HEIGHT, Math.max(0, Math.round(value)))]

  if (node.props[name] === next) {
    return false
  }
  node.props[name] = next

  return true
}

/**
 * ドラッグした距離から、新しい値を決める。
 * start はドラッグを始めたときの値(高さ・幅・余白は px、カラムの幅は px の幅)。delta は広げる向きに動かした px
 * (高さ・上の余白は下へ、幅は右へ、下の余白は上へが正。向きは呼ぶ側でそろえる)。
 * centered(左右の余白が auto で中央に寄せた要素)は、両側に広がるため幅の変化を 2 倍にする。
 */
export function resizedValue(target: ResizeTarget, start: number, delta: number, options: { rowWidth?: number, centered?: boolean, maxWidth?: number } = {}): number {
  if (target.kind === 'width') {
    const width = Math.max(0, start + delta * (options.centered ? 2 : 1))
    const limited = options.maxWidth !== undefined ? Math.min(width, options.maxWidth) : width

    return target.source === 'prop' ? spanFromWidth(limited, options.rowWidth ?? 0) : limited
  }

  return Math.max(0, start + delta)
}
