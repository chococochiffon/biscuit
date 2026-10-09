import { clampBox, FREE_CHILD_EXCLUDED_STYLES, FREE_LAYOUT_VERSION, nextY, round2, syncLayouts } from './layout'
import { newId } from './nodes'
import type { BuilderContent, BuilderNode, BuilderStyles, LayoutBox, Registry } from './types'

// 行・カラムで流し込む配置(v1)の内容を、自由配置(v2)の内容に変換する(画面から切り離した関数。convert.test.ts で確かめる)。
// 座標には描いたときの位置と大きさが要るため、エディタが v1 の内容を画面の外に 1200px で描いて測り(ConversionStage.vue)、
// その測った値(measure)からここで木を組み立て直す。
// - セクション: そのまま。中のブロックは、セクションの中身の枠からの位置にする
// - コンテナ・カラム: 背景・余白などの見た目を持つものはボックス(測った高さを最小の高さに)、持たないものは外して中身を上げる
// - 行: 外して中身(カラム)を上げる。スペーサー: 外す(間は位置に表れる)
// - ほかのブロック: ID・項目・スタイルはそのまま、自由配置では使えないスタイル(上下の外側の余白・幅)だけ外す。画像は img の大きさで置く
// 測れなかったブロック(この端末で隠すブロックなど)は、面のいちばん下に置く。タブレット・スマートフォンの位置は作らない(既定の並べ方にする)

export interface Rect {
  left: number
  top: number
  width: number
  height: number
}

export interface MeasuredNode {
  // ブロックの要素の枠
  rect: Rect
  // 内側の余白を除いた枠(セクション・ボックスにした要素の、中のブロックの位置の基準)
  content: Rect
  // 画像のブロックの img の枠
  image?: Rect
}

// ノードの ID → 測った値(測れなければ null)。一番外側(独自コンポーネント)の面は ROOT_ID
export type Measure = (id: string) => MeasuredNode | null

export const ROOT_ID = '__root__'

// 外さずにボックスにする、コンテナ・カラムの見た目のスタイル
const VISUAL_STYLES = ['backgroundColor', 'paddingTop', 'paddingBottom', 'paddingLeft', 'paddingRight', 'borderRadius', 'textAlign']

/**
 * v1 の内容を v2 の内容にする(v2 以上ならそのまま返す)。registry は v2 の定義、freeRoot は独自コンポーネント(一番外側も面)。
 */
export function convertToFree(content: BuilderContent, registry: Registry, measure: Measure, freeRoot: boolean): BuilderContent {
  if (content.version >= FREE_LAYOUT_VERSION) {
    return content
  }

  const source = JSON.parse(JSON.stringify(content)) as BuilderContent

  const converted: BuilderContent = {
    ...source,
    version: FREE_LAYOUT_VERSION,
    children: freeRoot
      ? place(source.children, measure(ROOT_ID)?.content ?? null, registry, measure)
      : source.children.flatMap(node => convertRoot(node, registry, measure)),
  }

  syncLayouts(converted, registry, freeRoot)

  return converted
}

/**
 * ページの直下のブロック(セクション・グローバルコンポーネント)。
 */
function convertRoot(node: BuilderNode, registry: Registry, measure: Measure): BuilderNode[] {
  if (node.type !== 'section') {
    return node.type in registry.blocks ? [node] : []
  }

  return [{ ...node, children: place(node.children ?? [], measure(node.id)?.content ?? null, registry, measure) }]
}

/**
 * 面(frame)の中に置くブロックの並び。
 */
function place(nodes: BuilderNode[], frame: Rect | null, registry: Registry, measure: Measure): BuilderNode[] {
  const placed: BuilderNode[] = []

  for (const node of nodes) {
    if (node.type === 'spacer') {
      continue
    }

    if (node.type === 'row' || ((node.type === 'container' || node.type === 'column') && !hasVisual(node))) {
      placed.push(...place(node.children ?? [], frame, registry, measure))
      continue
    }

    if (node.type === 'container' || node.type === 'column') {
      placed.push(toBox(node, frame, registry, measure))
      continue
    }

    if (!(node.type in registry.blocks)) {
      continue
    }

    const measured = measure(node.id)
    const rect = node.type === 'image' && measured?.image ? measured.image : measured?.rect
    const leaf: BuilderNode = { ...node, styles: freeStyles(node.styles, registry.blocks[node.type].styles) }

    if (node.responsive) {
      leaf.responsive = Object.fromEntries(Object.entries(node.responsive).map(([device, styles]) => [device, freeStyles(styles, registry.blocks[node.type].styles)]))
    }

    const box = boxIn(rect ?? null, frame, placed)
    leaf.layout = { desktop: box }
    placed.push(leaf)
  }

  return placed
}

/**
 * コンテナ・カラムを、同じ見た目のボックスにする(中のブロックはボックスの中身の枠からの位置。測った高さを最小の高さに)。
 */
function toBox(node: BuilderNode, frame: Rect | null, registry: Registry, measure: Measure): BuilderNode {
  const measured = measure(node.id)
  const allowed = registry.blocks.box?.styles ?? []
  const box: BuilderNode = {
    id: newId('box'),
    type: 'box',
    props: { backgroundImage: null },
    styles: freeStyles(node.styles, allowed),
    children: place(node.children ?? [], measured?.content ?? null, registry, measure),
  }
  const position = boxIn(measured?.rect ?? null, frame, [])

  if (measured) {
    position.h = Math.max(1, Math.round(measured.rect.height))
  }

  box.layout = { desktop: position }

  for (const key of ['visibility', 'classes'] as const) {
    if (node[key] !== undefined) {
      box[key] = node[key] as never
    }
  }

  return box
}

function hasVisual(node: BuilderNode): boolean {
  return VISUAL_STYLES.some(name => node.styles[name] !== undefined)
    || Object.values(node.responsive ?? {}).some(styles => VISUAL_STYLES.some(name => styles[name] !== undefined))
    || (node.classes?.length ?? 0) > 0
    || node.visibility !== undefined
}

/**
 * 自由配置で使えるスタイルだけを残す(ブロックの定義にあるもので、上下の外側の余白・幅を除く)。
 */
function freeStyles(styles: BuilderStyles, allowed: string[]): BuilderStyles {
  return Object.fromEntries(Object.entries(styles).filter(([name]) => allowed.includes(name) && !FREE_CHILD_EXCLUDED_STYLES.includes(name)))
}

/**
 * 測った枠を、面の中の位置にする。測れなければ(枠がない・大きさがない)面のいちばん下に幅いっぱいで置く。
 */
function boxIn(rect: Rect | null, frame: Rect | null, siblings: BuilderNode[]): LayoutBox {
  if (!rect || !frame || rect.width <= 0 || frame.width <= 0) {
    return { x: 0, y: nextY(siblings.flatMap(node => (node.layout ? [node.layout.desktop] : []))), w: 100 }
  }

  return clampBox({
    x: round2(((rect.left - frame.left) / frame.width) * 100),
    y: Math.round(rect.top - frame.top),
    w: round2((rect.width / frame.width) * 100),
  }, false)
}
