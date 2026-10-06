import { describe, expect, it } from 'vitest'
import { inlineProp, normalizeInlineText } from './inline'
import type { BlockDefinition, BuilderNode } from './types'

function node(type: string): BuilderNode {
  return { id: `${type}_01K8ZZZZZZZZZZZZZZZZZZZZZZ`, type, props: {}, styles: {} }
}

describe('inlineProp', () => {
  it('見出し・ボタンは文字、テキストは本文の HTML を直接書き換える', () => {
    expect(inlineProp(node('heading'))).toEqual({ prop: 'text', rich: false })
    expect(inlineProp(node('button'))).toEqual({ prop: 'text', rich: false })
    expect(inlineProp(node('text'))).toEqual({ prop: 'html', rich: true })
    expect(inlineProp(node('image'))).toBeNull()
  })
})

describe('normalizeInlineText', () => {
  const definition: BlockDefinition = {
    label: '', category: 'basic', icon: '', children: [], styles: [], allowedParents: [],
    props: { text: { label: '', type: 'string', default: '', max: 5 } },
  }

  it('改行を空白にし、最大の長さで切る(日本語も 1 文字ずつ数える)', () => {
    expect(normalizeInlineText('ab\ncd', definition, 'text')).toBe('ab cd')
    expect(normalizeInlineText('あいうえおかき', definition, 'text')).toBe('あいうえお')
    expect(normalizeInlineText('長い文字でも切らない', undefined, 'text')).toBe('長い文字でも切らない')
  })
})
