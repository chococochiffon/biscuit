import type { BuilderNode } from './types'

// コンポーネントツリー(左のパネルの「ツリー」)の表示とドロップ位置の計算(画面から切り離した処理で tree.test.ts で確かめる)

export interface TreeRow {
  node: BuilderNode
  // ページの直下が 0
  depth: number
  // 親(null はページの直下)と、その中の位置
  parentId: string | null
  index: number
  hasChildren: boolean
}

/**
 * 木を、表示する行の並び(親から子の順)にする。畳んだブロックの子孫は含めない。
 */
export function flattenTree(nodes: BuilderNode[], collapsed: ReadonlySet<string>, depth = 0, parentId: string | null = null): TreeRow[] {
  return nodes.flatMap((node, index) => {
    const children = node.children ?? []
    const row: TreeRow = { node, depth, parentId, index, hasChildren: children.length > 0 }

    return collapsed.has(node.id) ? [row] : [row, ...flattenTree(children, collapsed, depth + 1, node.id)]
  })
}

/**
 * ツリーの行に添える短い説明(見出し・ボタンの文字、テキストの書き出し、画像の代替テキスト、カラムの幅)。
 */
export function summaryOf(node: BuilderNode, maxLength = 24): string {
  const text = (value: unknown) => (typeof value === 'string' ? value : '')
  let summary = ''

  switch (node.type) {
    case 'heading':
    case 'button':
      summary = text(node.props.text)
      break
    case 'text':
      summary = text(node.props.html).replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim()
      break
    case 'image':
      summary = text(node.props.alt)
      break
    case 'column':
      summary = typeof node.props.span === 'number' ? `${node.props.span}/12` : ''
      break
  }

  return summary.length > maxLength ? `${summary.slice(0, maxLength)}…` : summary
}

export type TreeDropPosition = 'before' | 'after' | 'inside'

export interface TreeDrop {
  position: TreeDropPosition
  parentId: string | null
  index: number
}

/**
 * ドラッグ中のものを行のどこに落とすか。ratio は行の中のマウスの縦の位置(上端 0〜下端 1)。
 * 中に置ける行は、真ん中(上下 25% を除く)なら中の末尾、上なら前、下なら後ろ。中に置けない行は上半分なら前、下半分なら後ろ。
 * 選んだ位置に置けなければ、ほかの位置を順に試し、どこにも置けなければ null。
 */
export function resolveTreeDrop(row: TreeRow, ratio: number, canDropInto: (parentId: string | null) => boolean): TreeDrop | null {
  const inside: TreeDrop = { position: 'inside', parentId: row.node.id, index: row.node.children?.length ?? 0 }
  const before: TreeDrop = { position: 'before', parentId: row.parentId, index: row.index }
  const after: TreeDrop = { position: 'after', parentId: row.parentId, index: row.index + 1 }
  const canHold = row.node.children !== undefined && canDropInto(row.node.id)

  const candidates = canHold
    ? ratio < 0.25 ? [before, inside, after] : ratio > 0.75 ? [after, inside, before] : [inside, before, after]
    : ratio < 0.5 ? [before, after] : [after, before]

  return candidates.find(drop => (drop.position === 'inside' ? canHold : canDropInto(drop.parentId))) ?? null
}
