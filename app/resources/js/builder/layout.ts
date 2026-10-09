import type { BuilderContent, BuilderNode, Device, LayoutBox, Registry, ResponsiveDevice } from './types'

// 自由配置(内容の v2)の計算(画面から切り離した関数。layout.test.ts で確かめる)。
// 面(セクション・ボックスと、独自コンポーネントの一番外側)の直下のブロックは layout(端末ごとの x・y・w・h)を持ち、
// Canvas は公開側(chococo の utils/builderLayout.ts)と同じく、面の 1 つのマスに重ねて上と左の余白で置く。
// - タブレット: 位置がなければデスクトップの値
// - スマートフォン: 面の子のどれも位置を持たなければ、デスクトップの y → x の順に縦 1 列(左右・先頭の上・間は 16px)

// 自由配置を使う内容の版(biscuit の SchemaMigrator::FREE_LAYOUT_VERSION)
export const FREE_LAYOUT_VERSION = 2

// 中のブロックを座標で置く面(biscuit の BlockRegistry::FREE_SURFACES)
export const FREE_SURFACES = ['section', 'box']

// 面の直下のブロックでは使えないスタイル(biscuit の BlockRegistry::FREE_CHILD_EXCLUDED_STYLES。位置と大きさで決める)
export const FREE_CHILD_EXCLUDED_STYLES = ['marginTop', 'marginBottom', 'width', 'maxWidth']

// 縦 1 列に並べるときの間(px)
export const STACK_GAP = 16

// y・h の最大(biscuit の BuilderLayout::MAX_PIXELS)
export const MAX_PIXELS = 20000

// 新しく面に置くブロックの幅(%)。ないものは 50
const DEFAULT_WIDTHS: Record<string, number> = {
  'heading': 60,
  'text': 60,
  'image': 40,
  'button': 25,
  'divider': 100,
  'video': 60,
  'slider': 80,
  'article-list': 100,
  'navigation': 80,
  'breadcrumb': 60,
  'gallery': 100,
  'box': 50,
  'custom': 100,
}

// 新しく面に置くブロックの高さ(px。高さを持てるブロックのうち、決めておくもの)
const DEFAULT_HEIGHTS: Record<string, number> = {
  box: 200,
}

// 位置から高さが分からないブロックの、下に積むときの見込みの高さ(px)
const ESTIMATED_HEIGHT = 80

/**
 * 親(null は一番外側)の直下のブロックを座標で置くか。一番外側は、独自コンポーネント(freeRoot)だけが面になる。
 */
export function isFreeSurface(parentType: string | null, version: number, freeRoot: boolean): boolean {
  if (version < FREE_LAYOUT_VERSION) {
    return false
  }

  return parentType === null ? freeRoot : FREE_SURFACES.includes(parentType)
}

/**
 * 小数 2 桁に丸める(% の値。biscuit は小数 2 桁までを受け付ける)。
 */
export function round2(value: number): number {
  return Math.round(value * 100) / 100
}

/**
 * はみ出さないように収めた位置(幅は 1〜100%、左端は幅の分だけ右を空ける、y・h は 0 以上の整数)。高さを持てないブロックは h を外す。
 */
export function clampBox(box: LayoutBox, allowsHeight: boolean): LayoutBox {
  const w = round2(Math.min(100, Math.max(1, box.w)))
  const x = round2(Math.min(100 - w, Math.max(0, box.x)))
  const clamped: LayoutBox = { x, y: Math.min(MAX_PIXELS, Math.max(0, Math.round(box.y))), w }

  if (allowsHeight && box.h !== undefined) {
    clamped.h = Math.min(MAX_PIXELS, Math.max(1, Math.round(box.h)))
  }

  return clamped
}

/**
 * その端末で使う位置。タブレットは位置がなければデスクトップ。スマートフォンは位置がなければ null(縦 1 列に並べる)。
 */
export function deviceBox(node: BuilderNode, device: Device): LayoutBox | null {
  const layout = node.layout

  if (!layout) {
    return null
  }

  if (device === 'desktop') {
    return layout.desktop
  }

  return layout[device] ?? (device === 'tablet' ? layout.desktop : null)
}

/**
 * 面の子が、その端末で縦 1 列に並ぶか(スマートフォンで、どの子も位置を持たないとき)。
 */
export function isStacked(siblings: BuilderNode[], device: Device): boolean {
  return device === 'mobile' && !siblings.some(node => node.layout?.mobile !== undefined)
}

/**
 * 面の子が、その端末だけの位置を持っているか(タブレット・スマートフォン)。
 */
export function hasDeviceLayout(siblings: BuilderNode[], device: ResponsiveDevice): boolean {
  return siblings.some(node => node.layout?.[device] !== undefined)
}

/**
 * 縦 1 列に並べるときの順番(デスクトップの y → x の順)。
 */
export function stackOrder(siblings: BuilderNode[]): BuilderNode[] {
  return [...siblings]
    .filter(node => node.layout?.desktop)
    .sort((a, b) => a.layout!.desktop.y - b.layout!.desktop.y || a.layout!.desktop.x - b.layout!.desktop.x)
}

/**
 * Canvas でブロックを面の中に置くスタイル(公開側と同じ置き方)。高さは画像なら高さ、ボックスなら最小の高さ。
 */
export function layoutStyle(node: BuilderNode, siblings: BuilderNode[], device: Device): Record<string, string> {
  const height = (box: LayoutBox | null): Record<string, string> => {
    if (box?.h === undefined) {
      return {}
    }

    return node.type === 'box' ? { minHeight: `${box.h}px` } : { height: `${box.h}px` }
  }

  if (isStacked(siblings, device)) {
    const index = stackOrder(siblings).findIndex(sibling => sibling.id === node.id)

    return {
      gridArea: 'auto',
      order: String(Math.max(0, index)),
      margin: `${index === 0 ? STACK_GAP : 0}px ${STACK_GAP}px ${STACK_GAP}px`,
      ...height(node.layout?.desktop ?? null),
    }
  }

  const box = deviceBox(node, device)

  if (!box) {
    return {}
  }

  return {
    gridArea: '1 / 1',
    marginTop: `${box.y}px`,
    marginLeft: `${box.x}%`,
    width: `${box.w}%`,
    ...height(box),
  }
}

/**
 * 新しく面に置くブロックの幅(%)と、決めておく高さ。
 */
export function defaultSize(type: string, allowsHeight: boolean): { w: number, h?: number } {
  const h = allowsHeight ? DEFAULT_HEIGHTS[type] : undefined

  return h === undefined ? { w: DEFAULT_WIDTHS[type] ?? 50 } : { w: DEFAULT_WIDTHS[type] ?? 50, h }
}

/**
 * 面の中のいちばん下のブロックの下(間を空けた y)。位置から高さが分からないブロックは見込みの高さで数える。
 */
export function nextY(boxes: LayoutBox[]): number {
  if (boxes.length === 0) {
    return STACK_GAP
  }

  return Math.max(...boxes.map(box => box.y + (box.h ?? ESTIMATED_HEIGHT))) + STACK_GAP
}

/**
 * 内容を自由配置の決まりにそろえる(変えたら true)。テンプレート・貼り付け・ツリーでの移動などで、どの操作から来ても同じ形にする。
 * - 面の直下のブロックはデスクトップの位置を持つ(なければ、面のいちばん下に既定の大きさで置く)。面の外のブロックは位置を持たない
 * - 高さを持てないブロックの h を外し、はみ出さないように収める
 * - 同じ面の子は、端末ごとに全員が位置を持つか、全員が持たないか(一部だけ持つときは、持たないブロックをその端末のいちばん下に置く)
 */
export function syncLayouts(content: BuilderContent, registry: Registry, freeRoot: boolean): boolean {
  let changed = false

  const walk = (children: BuilderNode[], parentType: string | null) => {
    const free = isFreeSurface(parentType, content.version, freeRoot)

    if (free) {
      changed = syncSurface(children, registry) || changed
    }

    for (const node of children) {
      if (!free && node.layout !== undefined) {
        delete node.layout
        changed = true
      }

      if (node.children) {
        walk(node.children, node.type)
      }
    }
  }
  walk(content.children, null)

  return changed
}

function syncSurface(children: BuilderNode[], registry: Registry): boolean {
  let changed = false
  const allowsHeight = (node: BuilderNode) => registry.blocks[node.type]?.layoutHeight === true

  // デスクトップの位置(なければいちばん下に置く)
  for (const node of children) {
    if (!node.layout?.desktop) {
      const placed = children.filter(other => other !== node && other.layout?.desktop).map(other => other.layout!.desktop)
      node.layout = { ...node.layout, desktop: { x: 0, y: nextY(placed), ...defaultSize(node.type, allowsHeight(node)) } }
      changed = true
    }
  }

  for (const node of children) {
    for (const device of ['desktop', 'tablet', 'mobile'] as const) {
      const box = node.layout![device]

      if (box) {
        const clamped = clampBox(box, allowsHeight(node))

        if (JSON.stringify(clamped) !== JSON.stringify(box)) {
          node.layout![device] = clamped
          changed = true
        }
      }
    }
  }

  // 端末ごとの位置のそろい
  for (const device of ['tablet', 'mobile'] as const) {
    if (!hasDeviceLayout(children, device)) {
      continue
    }

    for (const node of children) {
      if (!node.layout![device]) {
        const placed = children.filter(other => other.layout?.[device]).map(other => other.layout![device]!)
        const desktop = node.layout!.desktop
        node.layout![device] = device === 'tablet'
          ? { ...desktop }
          : clampBox({ x: 0, y: nextY(placed), w: 100, ...(desktop.h === undefined ? {} : { h: desktop.h }) }, allowsHeight(node))
        changed = true
      }
    }
  }

  return changed
}

/**
 * 吸い付く位置。edges(動かしているブロックの端・中央の位置)のうち、candidates(面の端・中央、ほかのブロックの端・中央)に
 * threshold 以内で近いものがあれば、ずらす量と吸い付いた位置(ガイド線)を返す。なければ null。
 */
export function snapOffset(edges: number[], candidates: number[], threshold: number): { offset: number, guide: number } | null {
  let best: { offset: number, guide: number } | null = null

  for (const edge of edges) {
    for (const candidate of candidates) {
      const offset = candidate - edge

      if (Math.abs(offset) <= threshold && (best === null || Math.abs(offset) < Math.abs(best.offset))) {
        best = { offset, guide: candidate }
      }
    }
  }

  return best
}
