import { describe, expect, it } from 'vitest'
import { colorInputValue, isBold, quickTools } from './quickbar'
import type { BlockDefinition, BuilderNode, PropDefinition } from './types'

function definition(props: string[], styles: string[]): BlockDefinition {
  const prop: PropDefinition = { label: '', type: 'string', default: null }

  return { label: '', category: 'basic', icon: '', children: [], props: Object.fromEntries(props.map(name => [name, prop])), styles, allowedParents: [] }
}

function node(type: string): BuilderNode {
  return { id: `${type}_01K8ZZZZZZZZZZZZZZZZZZZZZZ`, type, props: {}, styles: {} }
}

describe('quickTools', () => {
  it('ブロックの定義から、使える道具を決める', () => {
    expect(quickTools(node('heading'), definition(['text', 'level'], ['marginTop', 'color', 'fontWeight', 'textAlign'])))
      .toEqual(['level', 'align', 'bold', 'color'])
    expect(quickTools(node('button'), definition(['text', 'href', 'target', 'variant'], ['textAlign', 'color'])))
      .toEqual(['variant', 'align', 'color', 'link'])
    expect(quickTools(node('image'), definition(['src', 'alt', 'href'], ['width', 'textAlign'])))
      .toEqual(['image', 'align', 'link'])
    expect(quickTools(node('section'), definition(['backgroundImage'], ['backgroundColor', 'color', 'textAlign'])))
      .toEqual(['backgroundImage', 'background', 'align'])
    expect(quickTools(node('row'), definition(['gap'], ['marginTop']))).toEqual([])
    expect(quickTools(node('heading'), undefined)).toEqual([])
  })
})

describe('helpers', () => {
  it('太字と、色の入力欄の値', () => {
    expect(isBold('700')).toBe(true)
    expect(isBold('500')).toBe(false)
    expect(isBold(null)).toBe(false)
    expect(colorInputValue('#FF0000')).toBe('#ff0000')
    expect(colorInputValue('#0af')).toBe('#00aaff')
    expect(colorInputValue('theme:primary')).toBe('#000000')
    expect(colorInputValue(null)).toBe('#000000')
  })
})
