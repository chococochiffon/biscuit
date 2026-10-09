import { describe, expect, it } from 'vitest'
import { clampBox, deviceBox, isFreeSurface, isStacked, layoutStyle, nextY, snapOffset, stackOrder, syncLayouts } from './layout'
import type { BlockDefinition, BuilderContent, BuilderNode, Registry } from './types'

function block(children: string[], layoutHeight = false): BlockDefinition {
  return { label: '', category: 'basic', icon: '', children, props: {}, styles: [], allowedParents: [], layoutHeight }
}

const registry: Registry = {
  rootChildren: ['section'],
  blocks: {
    section: block(['box', 'heading', 'image', 'slider']),
    box: block(['box', 'heading', 'image'], true),
    heading: block([]),
    image: block([], true),
    slider: block(['slide']),
    slide: block([]),
  },
  styles: {},
}

function node(id: string, type: string, extra: Partial<BuilderNode> = {}): BuilderNode {
  return { id: `${type}_${id}`, type, props: {}, styles: {}, ...extra }
}

describe('layout', () => {
  it('v2 のセクション・ボックスと、独自コンポーネントの一番外側だけが面になる', () => {
    expect(isFreeSurface('section', 2, false)).toBe(true)
    expect(isFreeSurface('box', 2, false)).toBe(true)
    expect(isFreeSurface('slider', 2, false)).toBe(false)
    expect(isFreeSurface(null, 2, false)).toBe(false)
    expect(isFreeSurface(null, 2, true)).toBe(true)
    expect(isFreeSurface('section', 1, false)).toBe(false)
  })

  it('はみ出さないように収め、高さを持てないブロックの高さを外す', () => {
    expect(clampBox({ x: 80, y: -5.4, w: 40 }, false)).toEqual({ x: 60, y: 0, w: 40 })
    expect(clampBox({ x: 10.123, y: 10.6, w: 150, h: 99.5 }, true)).toEqual({ x: 0, y: 11, w: 100, h: 100 })
    expect(clampBox({ x: 0, y: 0, w: 0.2, h: 50 }, false)).toEqual({ x: 0, y: 0, w: 1 })
  })

  it('端末の位置: タブレットはデスクトップを引き継ぎ、スマートフォンはなければ縦 1 列', () => {
    const a = node('A', 'heading', { layout: { desktop: { x: 50, y: 100, w: 40 }, mobile: { x: 0, y: 10, w: 100 } } })
    const b = node('B', 'image', { layout: { desktop: { x: 0, y: 100, w: 40, h: 200 } } })

    expect(deviceBox(a, 'tablet')).toEqual({ x: 50, y: 100, w: 40 })
    expect(deviceBox(a, 'mobile')).toEqual({ x: 0, y: 10, w: 100 })
    expect(deviceBox(b, 'mobile')).toBeNull()
    expect(isStacked([a, b], 'mobile')).toBe(false)
    expect(isStacked([b], 'mobile')).toBe(true)
    expect(isStacked([b], 'tablet')).toBe(false)
  })

  it('縦 1 列は y → x の順で、先頭だけ上も空ける', () => {
    const right = node('A', 'heading', { layout: { desktop: { x: 50, y: 100, w: 40 } } })
    const left = node('B', 'image', { layout: { desktop: { x: 0, y: 100, w: 40, h: 200 } } })
    const top = node('C', 'heading', { layout: { desktop: { x: 30, y: 20, w: 40 } } })
    const siblings = [right, left, top]

    expect(stackOrder(siblings).map(item => item.id)).toEqual([top.id, left.id, right.id])
    expect(layoutStyle(top, siblings, 'mobile')).toMatchObject({ gridArea: 'auto', order: '0', margin: '16px 16px 16px' })
    expect(layoutStyle(left, siblings, 'mobile')).toMatchObject({ order: '1', margin: '0px 16px 16px', height: '200px' })
    expect(layoutStyle(right, siblings, 'desktop')).toEqual({ gridArea: '1 / 1', marginTop: '100px', marginLeft: '50%', width: '40%' })

    const box = node('D', 'box', { layout: { desktop: { x: 0, y: 0, w: 50, h: 120 } } })
    expect(layoutStyle(box, [box], 'desktop').minHeight).toBe('120px')
  })

  it('下に積む位置は、いちばん下のブロックの下に間を空けた位置', () => {
    expect(nextY([])).toBe(16)
    expect(nextY([{ x: 0, y: 100, w: 10, h: 200 }, { x: 0, y: 250, w: 10 }])).toBe(346)
  })

  it('決まりにそろえる: 面の子に位置を足し、面の外の位置を外し、端末の位置のそろいを直す', () => {
    const placed = node('A', 'heading', { layout: { desktop: { x: 10, y: 40, w: 50, h: 30 }, mobile: { x: 0, y: 0, w: 100 } } })
    const added = node('B', 'image')
    const slide = node('C', 'slide', { layout: { desktop: { x: 0, y: 0, w: 10 } } })
    const slider = node('D', 'slider', { layout: { desktop: { x: 0, y: 300, w: 80 } }, children: [slide] })
    const content: BuilderContent = { version: 2, children: [node('S', 'section', { children: [placed, added, slider] })] }

    expect(syncLayouts(content, registry, false)).toBe(true)
    // 見出しは高さを持てない
    expect(placed.layout!.desktop).toEqual({ x: 10, y: 40, w: 50 })
    // 足したブロックは、いちばん下に既定の幅で
    expect(added.layout!.desktop).toEqual({ x: 0, y: 396, w: 40 })
    // スマートフォンの位置を持つ面なので、持たないブロックはスマートフォンでもいちばん下に
    expect(added.layout!.mobile).toEqual({ x: 0, y: 96, w: 100 })
    expect(slider.layout!.mobile).toBeDefined()
    // スライドは面の子ではない
    expect(slide.layout).toBeUndefined()

    expect(syncLayouts(content, registry, false)).toBe(false)
  })

  it('v1 の内容には位置を付けない', () => {
    const heading = node('A', 'heading', { layout: { desktop: { x: 0, y: 0, w: 10 } } })
    const content: BuilderContent = { version: 1, children: [node('S', 'section', { children: [heading] })] }

    syncLayouts(content, registry, false)
    expect(heading.layout).toBeUndefined()
  })

  it('吸い付き: いちばん近い候補へ、近すぎなければ吸い付かない', () => {
    expect(snapOffset([100, 150, 200], [0, 147, 203], 6)).toEqual({ offset: -3, guide: 147 })
    expect(snapOffset([100], [120], 6)).toBeNull()
  })
})
