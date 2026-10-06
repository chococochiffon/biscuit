import type { BlockDefinition, BuilderNode } from './types'

// Canvas で選んだブロックのすぐ上に出す操作バー(QuickBar)の道具。ブロックの定義(使える props・スタイル)から決める
// (画面から切り離した判定。quickbar.test.ts で確かめる)

export type QuickTool =
  // 見出しのレベル(H1〜H6)
  | 'level'
  // 文字の揃え(左・中央・右)
  | 'align'
  // 太字(見出し)
  | 'bold'
  // 文字の色
  | 'color'
  // リンク先と開き方
  | 'link'
  // ボタンの見た目
  | 'variant'
  // 画像の差し替え
  | 'image'
  // 背景色(セクション・コンテナ・カラム)
  | 'background'
  // 背景画像(セクション)
  | 'backgroundImage'

// 背景を持つ、中にブロックを置く種類(文字の色ではなく背景を出す)
const LAYOUT_TYPES = ['section', 'container', 'column']

/**
 * ブロックで使える操作バーの道具(並べる順)。
 */
export function quickTools(node: BuilderNode, definition: BlockDefinition | undefined): QuickTool[] {
  if (!definition) {
    return []
  }

  const has = (prop: string) => prop in definition.props
  const styles = definition.styles
  const layout = LAYOUT_TYPES.includes(node.type)
  const tools: QuickTool[] = []

  if (node.type === 'heading' && has('level')) {
    tools.push('level')
  }
  if (node.type === 'button' && has('variant')) {
    tools.push('variant')
  }
  if (has('src') && node.type === 'image') {
    tools.push('image')
  }
  if (has('backgroundImage')) {
    tools.push('backgroundImage')
  }
  if (layout && styles.includes('backgroundColor')) {
    tools.push('background')
  }
  if (styles.includes('textAlign')) {
    tools.push('align')
  }
  if (node.type === 'heading' && styles.includes('fontWeight')) {
    tools.push('bold')
  }
  if (!layout && styles.includes('color')) {
    tools.push('color')
  }
  if (has('href')) {
    tools.push('link')
  }

  return tools
}

/**
 * 太字か(文字の太さが 600 以上)。
 */
export function isBold(fontWeight: string | null): boolean {
  return fontWeight !== null && Number(fontWeight) >= 600
}

/**
 * 色の入力欄(input type=color)に入れられる値(#rrggbb。テーマの色・短い形は入れられないため、それ以外は既定の黒)。
 */
export function colorInputValue(value: string | null): string {
  if (value === null) {
    return '#000000'
  }

  if (/^#[0-9a-f]{6}$/i.test(value)) {
    return value.toLowerCase()
  }

  const short = value.match(/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i)

  return short ? `#${short[1]}${short[1]}${short[2]}${short[2]}${short[3]}${short[3]}`.toLowerCase() : '#000000'
}
