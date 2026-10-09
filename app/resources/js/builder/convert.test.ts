import { describe, expect, it } from 'vitest'
import { convertToFree, ROOT_ID, type MeasuredNode, type Rect } from './convert'
import type { BlockDefinition, BuilderContent, BuilderNode, Registry } from './types'

function block(children: string[], styles: string[] = [], layoutHeight = false): BlockDefinition {
  return { label: '', category: 'basic', icon: '', children, props: {}, styles, allowedParents: [], layoutHeight }
}

// v2 の定義(biscuit の BlockRegistry::toArray(version: 2) のうち、テストに使う部分)
const registry: Registry = {
  rootChildren: ['section', 'global'],
  blocks: {
    section: block(['box', 'heading', 'image', 'text'], ['paddingTop', 'backgroundColor']),
    box: block(['box', 'heading', 'image', 'text'], ['paddingTop', 'paddingLeft', 'backgroundColor', 'borderRadius', 'textAlign'], true),
    heading: block([], ['marginTop', 'marginBottom', 'fontSize', 'textAlign']),
    text: block([], ['marginBottom', 'color']),
    image: block([], ['marginTop', 'width', 'borderRadius'], true),
    global: block([]),
  },
  styles: {},
}

function node(id: string, type: string, extra: Partial<BuilderNode> = {}): BuilderNode {
  return { id: `${type}_${id}`, type, props: {}, styles: {}, ...extra }
}

function rect(left: number, top: number, width: number, height: number): Rect {
  return { left, top, width, height }
}

/**
 * 測った値の表(内側の余白は padding の分だけ内側)。
 */
function measured(entries: Record<string, { rect: Rect, padding?: number, image?: Rect }>) {
  return (id: string): MeasuredNode | null => {
    const entry = entries[id]

    if (!entry) {
      return null
    }

    const padding = entry.padding ?? 0
    const { left, top, width, height } = entry.rect

    return { rect: entry.rect, content: rect(left + padding, top + padding, width - padding * 2, height - padding * 2), image: entry.image }
  }
}

describe('convertToFree', () => {
  // セクション(枠 0,0 から幅 1200・内側の余白 0) > コンテナ(見た目なし) > 行 > カラム 2 つ(片方は背景あり)
  const heading = node('H', 'heading', { props: { text: '見出し' }, styles: { fontSize: '32px', marginBottom: '16px' }, responsive: { mobile: { fontSize: '24px', marginTop: '4px' } } })
  const image = node('I', 'image', { styles: { width: '50%', borderRadius: '8px' } })
  const text = node('T', 'text', { styles: { color: '#333333' } })
  const plainColumn = node('C1', 'column', { props: { span: 6 }, children: [heading, image] })
  const colored = node('C2', 'column', { props: { span: 6 }, styles: { backgroundColor: '#eeeeee', paddingLeft: '16px', marginTop: '8px' }, children: [text] })
  const content: BuilderContent = {
    version: 1,
    children: [
      node('S', 'section', { styles: { paddingTop: '48px' }, children: [
        node('K', 'container', { children: [node('R', 'row', { children: [plainColumn, colored] }), node('P', 'spacer')] }),
      ] }),
      node('G', 'global', { props: { component: 1 } }),
    ],
  }
  const measure = measured({
    section_S: { rect: rect(0, 0, 1200, 500) },
    heading_H: { rect: rect(60, 48, 540, 40) },
    image_I: { rect: rect(60, 100, 540, 300), image: rect(60, 100, 270, 300) },
    column_C2: { rect: rect(600, 48, 540, 200), padding: 16 },
    text_T: { rect: rect(616, 64, 508, 60) },
  })

  it('セクションの中身を測った位置で置き、行・見た目のないコンテナ・カラム・スペーサーを外す', () => {
    const converted = convertToFree(content, registry, measure, false)
    const section = converted.children[0]

    expect(converted.version).toBe(2)
    expect(section.styles).toEqual({ paddingTop: '48px' })
    expect(section.children!.map(child => child.type)).toEqual(['heading', 'image', 'box'])

    const [convertedHeading, convertedImage, box] = section.children!
    // ID・項目はそのまま。自由配置で使えないスタイル(上下の外側の余白・幅)だけ外す
    expect(convertedHeading.id).toBe(heading.id)
    expect(convertedHeading.styles).toEqual({ fontSize: '32px' })
    expect(convertedHeading.responsive).toEqual({ mobile: { fontSize: '24px' } })
    expect(convertedHeading.layout).toEqual({ desktop: { x: 5, y: 48, w: 45 } })
    // 画像は img の大きさで置く(幅のスタイルは外す)
    expect(convertedImage.styles).toEqual({ borderRadius: '8px' })
    expect(convertedImage.layout).toEqual({ desktop: { x: 5, y: 100, w: 22.5 } })
  })

  it('見た目を持つカラムはボックスにし、中は内側の余白を除いた枠からの位置にする', () => {
    const box = convertToFree(content, registry, measure, false).children[0].children![2]

    expect(box.id).toMatch(/^box_/)
    expect(box.styles).toEqual({ backgroundColor: '#eeeeee', paddingLeft: '16px' })
    expect(box.layout).toEqual({ desktop: { x: 50, y: 48, w: 45, h: 200 } })
    expect(box.children![0].layout).toEqual({ desktop: { x: 0, y: 0, w: 100 } })
  })

  it('グローバルコンポーネントはそのまま残し、もとの内容は変えない', () => {
    const converted = convertToFree(content, registry, measure, false)

    expect(converted.children[1]).toEqual(content.children[1])
    expect(content.version).toBe(1)
    expect(content.children[0].children![0].type).toBe('container')
  })

  it('測れなかったブロックは、面のいちばん下に幅いっぱいで置く', () => {
    const converted = convertToFree(content, registry, measured({ section_S: { rect: rect(0, 0, 1200, 500) } }), false)
    const boxes = converted.children[0].children!.map(child => child.layout!.desktop)

    expect(boxes[0]).toEqual({ x: 0, y: 16, w: 100 })
    expect(boxes[1].y).toBeGreaterThan(boxes[0].y)
  })

  it('独自コンポーネントは一番外側を面にする', () => {
    const custom: BuilderContent = { version: 1, children: [node('H', 'heading')] }
    const converted = convertToFree(custom, registry, measured({ [ROOT_ID]: { rect: rect(0, 0, 1000, 200) }, heading_H: { rect: rect(100, 20, 500, 40) } }), true)

    expect(converted.children[0].layout).toEqual({ desktop: { x: 10, y: 20, w: 50 } })
  })

  it('v2 の内容はそのまま返す', () => {
    const v2: BuilderContent = { version: 2, children: [] }

    expect(convertToFree(v2, registry, () => null, false)).toBe(v2)
  })
})
