import { describe, expect, it } from 'vitest'
import { blockStyle, isValidStyleValue, setNodeStyle, styleValueFor } from './styles'
import { themeVariables } from './theme'
import type { BuilderNode } from './types'

function heading(): BuilderNode {
  return { id: 'heading_01K8ZZZZZZZZZZZZZZZZZZZZZZ', type: 'heading', props: {}, styles: { fontSize: '32px' } }
}

describe('isValidStyleValue', () => {
  it('種類ごとに許した形だけを受け付ける', () => {
    expect(isValidStyleValue('length', '16px')).toBe(true)
    expect(isValidStyleValue('length', '1.5rem')).toBe(true)
    expect(isValidStyleValue('length', '16')).toBe(false)
    expect(isValidStyleValue('length', 'calc(1px + 2px)')).toBe(false)
    expect(isValidStyleValue('color', '#336699')).toBe(true)
    expect(isValidStyleValue('color', 'red')).toBe(false)
    expect(isValidStyleValue('number', '1.8')).toBe(true)
    expect(isValidStyleValue(['left', 'center'], 'center')).toBe(true)
    expect(isValidStyleValue(['left', 'center'], 'right')).toBe(false)
    expect(isValidStyleValue(undefined, '16px')).toBe(false)
  })
})

describe('setNodeStyle', () => {
  it('デスクトップは styles を変え、null で指定を外す', () => {
    const node = heading()

    expect(setNodeStyle(node, 'desktop', 'color', '#ff0000')).toBe(true)
    expect(node.styles).toEqual({ fontSize: '32px', color: '#ff0000' })
    expect(setNodeStyle(node, 'desktop', 'color', '#ff0000')).toBe(false)
    expect(setNodeStyle(node, 'desktop', 'color', null)).toBe(true)
    expect(node.styles).toEqual({ fontSize: '32px' })
  })

  it('タブレット・スマートフォンは端末の上書きを変え、空になったら取り除く', () => {
    const node = heading()

    setNodeStyle(node, 'mobile', 'fontSize', '22px')
    setNodeStyle(node, 'tablet', 'fontSize', '28px')
    expect(node.styles).toEqual({ fontSize: '32px' })
    expect(node.responsive).toEqual({ mobile: { fontSize: '22px' }, tablet: { fontSize: '28px' } })

    setNodeStyle(node, 'mobile', 'fontSize', null)
    expect(node.responsive).toEqual({ tablet: { fontSize: '28px' } })

    setNodeStyle(node, 'tablet', 'fontSize', null)
    expect(node.responsive).toBeUndefined()
  })
})

describe('styleValueFor', () => {
  it('その端末の指定か、大きい画面から引き継いだ値かを返す', () => {
    const node = heading()
    node.responsive = { tablet: { fontSize: '28px' } }

    expect(styleValueFor(node, 'desktop', 'fontSize')).toEqual({ value: '32px', source: 'own' })
    expect(styleValueFor(node, 'tablet', 'fontSize')).toEqual({ value: '28px', source: 'own' })
    expect(styleValueFor(node, 'mobile', 'fontSize')).toEqual({ value: '28px', source: 'inherited' })
    expect(styleValueFor(node, 'mobile', 'color')).toEqual({ value: null, source: 'none' })
  })
})

describe('テーマの色', () => {
  it('色のスタイルはテーマの色(theme:名前)も受け付け、CSS の変数にする', () => {
    const node: BuilderNode = { id: 'heading_01K0000000000000000000000A', type: 'heading', props: {}, styles: { color: 'theme:primary', backgroundColor: '#ffffff' } }

    expect(isValidStyleValue('color', 'theme:accent')).toBe(true)
    expect(isValidStyleValue('color', 'theme:unknown')).toBe(false)
    expect(isValidStyleValue('length', 'theme:primary')).toBe(false)
    expect(blockStyle(node, 'desktop')).toEqual({ 'color': 'var(--builder-theme-primary)', 'background-color': '#ffffff' })
  })

  it('テーマを CSS の変数にする(フォントは選んだものだけ)', () => {
    expect(themeVariables({ colors: { primary: '#e99540' }, fonts: { heading: { key: 'noto-serif-jp', family: "'Noto Serif JP', serif", href: '' }, body: null } })).toEqual({
      '--builder-theme-primary': '#e99540',
      '--builder-theme-heading-font': "'Noto Serif JP', serif",
    })
  })
})
