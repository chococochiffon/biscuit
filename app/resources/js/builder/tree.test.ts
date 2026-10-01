import { describe, expect, it } from 'vitest'
import { flattenTree, resolveTreeDrop, summaryOf } from './tree'
import type { BuilderNode } from './types'

function node(type: string, children?: BuilderNode[], props: Record<string, unknown> = {}): BuilderNode {
  return { id: `${type}_${Math.random().toString(36).slice(2)}`, type, props, styles: {}, ...(children ? { children } : {}) }
}

describe('flattenTree', () => {
  it('親から子の順に深さ・親・位置を付けて並べ、畳んだブロックの子孫は除く', () => {
    const heading = node('heading')
    const column = node('column', [node('text')])
    const row = node('row', [column])
    const section = node('section', [heading, row])

    const rows = flattenTree([section], new Set())
    expect(rows.map(r => [r.node.type, r.depth, r.index])).toEqual([
      ['section', 0, 0], ['heading', 1, 0], ['row', 1, 1], ['column', 2, 0], ['text', 3, 0],
    ])
    expect(rows[3].parentId).toBe(row.id)
    expect(rows[0].hasChildren).toBe(true)
    expect(rows[1].hasChildren).toBe(false)

    expect(flattenTree([section], new Set([row.id])).map(r => r.node.type)).toEqual(['section', 'heading', 'row'])
  })
})

describe('summaryOf', () => {
  it('ブロックの中身を短く書き出す', () => {
    expect(summaryOf(node('heading', undefined, { text: 'ようこそ' }))).toBe('ようこそ')
    expect(summaryOf(node('text', undefined, { html: '<p>本文&nbsp;です</p><p>2 行目</p>' }))).toBe('本文 です 2 行目')
    expect(summaryOf(node('column', [], { span: 6 }))).toBe('6/12')
    expect(summaryOf(node('heading', undefined, { text: 'あ'.repeat(30) }), 10)).toBe(`${'あ'.repeat(10)}…`)
    expect(summaryOf(node('divider'))).toBe('')
  })
})

describe('resolveTreeDrop', () => {
  const section = node('section', [node('heading')])
  const sectionRow = { node: section, depth: 0, parentId: null, index: 1, hasChildren: true }
  const heading = section.children![0]
  const headingRow = { node: heading, depth: 1, parentId: section.id, index: 0, hasChildren: false }

  it('中に置ける行は、真ん中なら中の末尾、上下の端なら前・後ろ', () => {
    const anywhere = () => true

    expect(resolveTreeDrop(sectionRow, 0.5, anywhere)).toEqual({ position: 'inside', parentId: section.id, index: 1 })
    expect(resolveTreeDrop(sectionRow, 0.1, anywhere)).toEqual({ position: 'before', parentId: null, index: 1 })
    expect(resolveTreeDrop(sectionRow, 0.9, anywhere)).toEqual({ position: 'after', parentId: null, index: 2 })
  })

  it('中に置けない行は、上半分なら前・下半分なら後ろ', () => {
    const anywhere = () => true

    expect(resolveTreeDrop(headingRow, 0.4, anywhere)).toEqual({ position: 'before', parentId: section.id, index: 0 })
    expect(resolveTreeDrop(headingRow, 0.6, anywhere)).toEqual({ position: 'after', parentId: section.id, index: 1 })
  })

  it('選んだ位置に置けなければほかの位置を試し、どこにも置けなければ null', () => {
    // セクションの中にだけ置ける(例: 見出しをドラッグ中)
    const onlyInSection = (parentId: string | null) => parentId === section.id

    expect(resolveTreeDrop(sectionRow, 0.1, onlyInSection)).toEqual({ position: 'inside', parentId: section.id, index: 1 })
    // ページの直下にだけ置ける(例: セクションをドラッグ中)
    expect(resolveTreeDrop(headingRow, 0.4, parentId => parentId === null)).toBeNull()
    // 中に置けないセクションの行は、上半分なら前・下半分なら後ろ
    expect(resolveTreeDrop(sectionRow, 0.3, parentId => parentId === null)).toEqual({ position: 'before', parentId: null, index: 1 })
    expect(resolveTreeDrop(sectionRow, 0.7, parentId => parentId === null)).toEqual({ position: 'after', parentId: null, index: 2 })
  })
})
