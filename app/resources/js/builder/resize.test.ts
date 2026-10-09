import { describe, expect, it } from 'vitest'
import { applyResize, pixels, resizedValue, resizeTargets, spanFromWidth, spanProp } from './resize'
import type { BlockDefinition, BuilderNode } from './types'

function definition(styles: string[]): BlockDefinition {
  return { label: '', category: 'layout', icon: '', children: [], props: {}, styles, allowedParents: [] }
}

function node(type: string, props: Record<string, unknown> = {}): BuilderNode {
  return { id: `${type}_01K8ZZZZZZZZZZZZZZZZZZZZZZ`, type, props, styles: {} }
}

const SPACING = ['marginTop', 'marginBottom', 'paddingTop', 'paddingBottom', 'paddingLeft', 'paddingRight']

describe('resizeTargets', () => {
  it('ブロックの定義のスタイルから、使えるつまみを決める', () => {
    expect(resizeTargets(node('section'), definition([...SPACING, 'minHeight'])).map(target => target.kind))
      .toEqual(['height', 'paddingTop', 'paddingBottom'])
    expect(resizeTargets(node('container'), definition([...SPACING, 'maxWidth'])))
      .toContainEqual({ kind: 'width', source: 'style', name: 'maxWidth' })
    expect(resizeTargets(node('image'), definition(['marginTop', 'width', 'maxWidth'])))
      .toEqual([{ kind: 'width', source: 'style', name: 'width' }])
    expect(resizeTargets(node('column'), definition(SPACING))[0]).toEqual({ kind: 'width', source: 'prop', name: 'span' })
    expect(resizeTargets(node('spacer'), definition([]))).toEqual([{ kind: 'height', source: 'prop', name: 'height' }])
    expect(resizeTargets(node('heading'), definition(['marginTop', 'fontSize']))).toEqual([])
  })
})

describe('resizedValue', () => {
  it('高さ・余白は動かした分だけ変え、0 未満にしない', () => {
    const height = { kind: 'height', source: 'style', name: 'minHeight' } as const

    expect(resizedValue(height, 300, 120)).toBe(420)
    expect(resizedValue(height, 300, -500)).toBe(0)
  })

  it('幅は中央に寄せた要素なら 2 倍で変え、親の幅を超えない', () => {
    const width = { kind: 'width', source: 'style', name: 'maxWidth' } as const

    expect(resizedValue(width, 600, 50)).toBe(650)
    expect(resizedValue(width, 600, 50, { centered: true })).toBe(700)
    expect(resizedValue(width, 600, 900, { maxWidth: 1000 })).toBe(1000)
  })

  it('カラムの幅は、行の幅に対する割合を 12 分割の目盛りに吸い付かせる', () => {
    const span = { kind: 'width', source: 'prop', name: 'span' } as const

    expect(resizedValue(span, 600, 100, { rowWidth: 1200 })).toBe(7)
    expect(resizedValue(span, 600, -1000, { rowWidth: 1200 })).toBe(1)
    expect(resizedValue(span, 600, 1000, { rowWidth: 1200, maxWidth: 1200 })).toBe(12)
  })
})

describe('applyResize', () => {
  it('スタイルは選んでいる端末に px で入れる', () => {
    const section = node('section')
    const target = { kind: 'height', source: 'style', name: 'minHeight' } as const

    expect(applyResize(section, 'desktop', target, 420.4)).toBe(true)
    expect(applyResize(section, 'mobile', target, 200)).toBe(true)
    expect(section.styles).toEqual({ minHeight: '420px' })
    expect(section.responsive).toEqual({ mobile: { minHeight: '200px' } })
    expect(applyResize(section, 'desktop', target, 420)).toBe(false)
  })

  it('カラムの幅は端末の項目に、スペーサーの高さは範囲に収めて入れる', () => {
    const column = node('column', { span: 6 })
    const span = { kind: 'width', source: 'prop', name: 'span' } as const

    applyResize(column, 'tablet', span, 4)
    expect(column.props).toEqual({ span: 6, spanTablet: 4 })

    const spacer = node('spacer', { height: 32 })
    applyResize(spacer, 'desktop', { kind: 'height', source: 'prop', name: 'height' }, 999)
    expect(spacer.props.height).toBe(400)
  })
})

describe('helpers', () => {
  it('px の値・カラムの幅・端末の項目', () => {
    expect(pixels(12.6)).toBe('13px')
    expect(pixels(-3)).toBe('0px')
    expect(pixels(123456)).toBe('9999px')
    expect(spanFromWidth(300, 1200)).toBe(3)
    expect(spanFromWidth(300, 0)).toBe(12)
    expect(spanProp('desktop')).toBe('span')
    expect(spanProp('mobile')).toBe('spanMobile')
  })
})
