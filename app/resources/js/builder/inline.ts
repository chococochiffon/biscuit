import type { BlockDefinition, BuilderNode } from './types'

// Canvas の上で文字を直接書き換えられるブロック(画面から切り離した判定と整形。inline.test.ts で確かめる)。
// 見出し・ボタンは 1 行の文字(text)、テキストは本文の HTML(html。Quill で書き、保存時に biscuit が無害化する)

const INLINE_PROPS: Record<string, { prop: string, rich: boolean }> = {
  heading: { prop: 'text', rich: false },
  button: { prop: 'text', rich: false },
  text: { prop: 'html', rich: true },
}

/**
 * ブロックの、直接書き換える項目(なければ null)。
 */
export function inlineProp(node: BuilderNode): { prop: string, rich: boolean } | null {
  return INLINE_PROPS[node.type] ?? null
}

/**
 * 1 行の文字の入力を、項目に入れる形にする(改行は空白にし、項目の最大の長さで切る)。
 */
export function normalizeInlineText(text: string, definition: BlockDefinition | undefined, prop: string): string {
  const max = definition?.props[prop]?.max
  const single = text.replace(/[\r\n]+/g, ' ')

  return typeof max === 'number' ? [...single].slice(0, max).join('') : single
}
