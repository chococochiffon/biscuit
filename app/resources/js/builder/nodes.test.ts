import { describe, expect, it } from 'vitest'
import { ancestorsOf, canPlace, cloneWithNewIds, countNodes, createNode, equalizeColumns, evenSpans, findNode, findPastePosition, insertNode, moveNode, newId, parseClipboard, removeNode, serializeClipboard } from './nodes'
import { blockStyle, columnSpan } from './styles'
import type { BlockDefinition, BuilderContent, BuilderNode, Registry } from './types'

// biscuit の BlockRegistry::toArray() のうち、テストに使う部分
function block(category: BlockDefinition['category'], children: string[], props: BlockDefinition['props'] = {}): BlockDefinition {
  return { label: '', category, icon: '', children, props, styles: [], allowedParents: [] }
}

const BASIC = ['heading', 'text']
const registry: Registry = {
  rootChildren: ['section'],
  blocks: {
    section: block('layout', ['container', 'row', ...BASIC]),
    container: block('layout', ['row', ...BASIC]),
    row: block('layout', ['column']),
    column: block('layout', BASIC, { span: { label: '', type: 'int', default: 12, min: 1, max: 12 } }),
    heading: block('basic', [], { text: { label: '', type: 'string', default: '見出し' }, level: { label: '', type: 'int', default: 2 } }),
    text: block('basic', [], { html: { label: '', type: 'richtext', default: '<p>テキスト</p>' } }),
  },
  styles: {},
}

function node(type: string, children?: BuilderNode[], props: Record<string, unknown> = {}): BuilderNode {
  return { ...createNode(registry, type), ...(children ? { children } : {}), props: { ...createNode(registry, type).props, ...props } }
}

/**
 * セクション(見出し 2 つ)と、セクション > 行 > カラム 2 つの内容。
 */
function content(): { content: BuilderContent, ids: Record<string, string> } {
  const heading1 = node('heading', undefined, { text: '1' })
  const heading2 = node('heading', undefined, { text: '2' })
  const column1 = node('column', [])
  const column2 = node('column', [])
  const row = node('row', [column1, column2])
  const section1 = node('section', [heading1, heading2])
  const section2 = node('section', [row])

  return {
    content: { version: 1, children: [section1, section2] },
    ids: { heading1: heading1.id, heading2: heading2.id, column1: column1.id, column2: column2.id, row: row.id, section1: section1.id, section2: section2.id },
  }
}

describe('ID', () => {
  it('種類_ULID の形で、毎回違う', () => {
    const id = newId('heading')

    expect(id).toMatch(/^heading_[0-9A-HJKMNP-TV-Z]{26}$/)
    expect(newId('heading')).not.toBe(id)
  })

  it('複製は子孫まで ID を振り直す', () => {
    const { content: tree } = content()
    const copy = cloneWithNewIds(tree.children[1])

    expect(copy.id).not.toBe(tree.children[1].id)
    expect(copy.children?.[0].children?.[0].id).not.toBe(tree.children[1].children?.[0].children?.[0].id)
    expect(countNodes([copy])).toBe(4)
  })
})

describe('createNode', () => {
  it('定義の既定値で作り、中に置ける種類だけ children を持つ', () => {
    expect(createNode(registry, 'heading')).toMatchObject({ type: 'heading', props: { text: '見出し', level: 2 }, styles: {} })
    expect(createNode(registry, 'heading').children).toBeUndefined()
    expect(createNode(registry, 'section').children).toEqual([])
  })
})

describe('置ける場所', () => {
  it('ページの直下はセクションだけ、行の中はカラムだけ', () => {
    expect(canPlace(registry, null, 'section')).toBe(true)
    expect(canPlace(registry, null, 'heading')).toBe(false)
    expect(canPlace(registry, 'row', 'column')).toBe(true)
    expect(canPlace(registry, 'row', 'heading')).toBe(false)
    expect(canPlace(registry, 'heading', 'text')).toBe(false)
  })
})

describe('insertNode', () => {
  it('置ける場所の指定した位置に入れる', () => {
    const { content: tree, ids } = content()
    const text = node('text')

    expect(insertNode(registry, tree, ids.section1, 1, text)).toBe(true)
    expect(tree.children[0].children?.map(child => child.id)).toEqual([ids.heading1, text.id, ids.heading2])
  })

  it('置けない場所には入れない', () => {
    const { content: tree, ids } = content()

    expect(insertNode(registry, tree, ids.row, 0, node('heading'))).toBe(false)
    expect(insertNode(registry, tree, null, 0, node('heading'))).toBe(false)
    expect(insertNode(registry, tree, ids.heading1, 0, node('text'))).toBe(false)
    expect(countNodes(tree.children)).toBe(7)
  })
})

describe('moveNode', () => {
  it('同じ親の中で後ろへ並び替える', () => {
    const { content: tree, ids } = content()

    expect(moveNode(registry, tree, ids.heading1, ids.section1, 2)).toBe(true)
    expect(tree.children[0].children?.map(child => child.id)).toEqual([ids.heading2, ids.heading1])
  })

  it('同じ親の中で前へ並び替える', () => {
    const { content: tree, ids } = content()

    expect(moveNode(registry, tree, ids.section2, null, 0)).toBe(true)
    expect(tree.children.map(child => child.id)).toEqual([ids.section2, ids.section1])
  })

  it('別の親(入れ子)へ移す', () => {
    const { content: tree, ids } = content()

    expect(moveNode(registry, tree, ids.heading2, ids.column2, 0)).toBe(true)
    expect(findNode(tree, ids.column2)?.children?.map(child => child.id)).toEqual([ids.heading2])
    expect(tree.children[0].children?.map(child => child.id)).toEqual([ids.heading1])
  })

  it('置けない親・自分の中へは移さない', () => {
    const { content: tree, ids } = content()
    const before = structuredClone(tree)

    expect(moveNode(registry, tree, ids.heading1, ids.row, 0)).toBe(false)
    expect(moveNode(registry, tree, ids.section2, ids.column1, 0)).toBe(false)
    expect(moveNode(registry, tree, ids.row, ids.column1, 0)).toBe(false)
    expect(tree).toEqual(before)
  })
})

describe('removeNode', () => {
  it('子ごと取り除く', () => {
    const { content: tree, ids } = content()

    expect(removeNode(tree, ids.row)?.id).toBe(ids.row)
    expect(findNode(tree, ids.column1)).toBeNull()
    expect(countNodes(tree.children)).toBe(4)
    expect(removeNode(tree, 'unknown')).toBeNull()
  })
})

describe('ancestorsOf', () => {
  it('ページに近い順に祖先を返す', () => {
    const { content: tree, ids } = content()

    expect(ancestorsOf(tree, ids.column2).map(ancestor => ancestor.id)).toEqual([ids.section2, ids.row])
    expect(ancestorsOf(tree, ids.section1)).toEqual([])
  })
})

describe('Canvas のスタイル', () => {
  it('端末の上書きを重ね、画像の幅は img に効かせる', () => {
    const image: BuilderNode = {
      id: newId('image'),
      type: 'image',
      props: {},
      styles: { marginTop: '8px', width: '50%' },
      responsive: { tablet: { marginTop: '4px' }, mobile: { width: '100%' } },
    }

    expect(blockStyle(image, 'desktop')).toEqual({ 'margin-top': '8px' })
    expect(blockStyle(image, 'desktop', 'inner')).toEqual({ width: '50%' })
    expect(blockStyle(image, 'tablet')).toEqual({ 'margin-top': '4px' })
    expect(blockStyle(image, 'mobile', 'inner')).toEqual({ width: '100%' })
  })

  it('カラムの幅は端末ごとの指定がなければ、タブレットはデスクトップと同じ・スマートフォンは 12', () => {
    const column = node('column', [], { span: 6 })

    expect(columnSpan(column, 'desktop')).toBe(6)
    expect(columnSpan(column, 'tablet')).toBe(6)
    expect(columnSpan(column, 'mobile')).toBe(12)
    expect(columnSpan({ ...column, props: { span: 6, spanTablet: 4, spanMobile: 6 } }, 'mobile')).toBe(6)
  })
})

describe('カラムの幅をそろえる', () => {
  it('12 分割をできるだけ均等に分ける', () => {
    expect(evenSpans(1)).toEqual([12])
    expect(evenSpans(2)).toEqual([6, 6])
    expect(evenSpans(3)).toEqual([4, 4, 4])
    expect(evenSpans(4)).toEqual([3, 3, 3, 3])
    expect(evenSpans(5)).toEqual([3, 3, 2, 2, 2])
    expect(evenSpans(0)).toEqual([])
  })

  it('均等だった行にカラムを足すと、すべてのカラムをそろえる', () => {
    const first = node('column', [], { span: 12 })
    const second = node('column', [], { span: 12 })
    const row = node('row', [first, second])

    expect(equalizeColumns(row, second.id)).toBe(true)
    expect(row.children?.map(column => column.props.span)).toEqual([6, 6])

    const third = node('column', [], { span: 12 })
    row.children?.push(third)
    expect(equalizeColumns(row, third.id)).toBe(true)
    expect(row.children?.map(column => column.props.span)).toEqual([4, 4, 4])
  })

  it('幅を自分で変えた行は変えない', () => {
    const added = node('column', [], { span: 12 })
    const row = node('row', [node('column', [], { span: 8 }), node('column', [], { span: 4 }), added])

    expect(equalizeColumns(row, added.id)).toBe(false)
    expect(row.children?.map(column => column.props.span)).toEqual([8, 4, 12])
  })

  it('タブレット・スマートフォンの幅は変えない', () => {
    const first = node('column', [], { span: 12, spanMobile: 6 })
    const added = node('column', [], { span: 12 })
    const row = node('row', [first, added])

    equalizeColumns(row, added.id)
    expect(first.props).toMatchObject({ span: 6, spanMobile: 6 })
  })
})

describe('コピー・貼り付け', () => {
  it('コピーした文字列から、子孫まで新しい ID を振ったブロックを取り出す', () => {
    const { content: tree } = content()
    const pasted = parseClipboard(registry, serializeClipboard(tree.children[1], 1), 1)

    expect(pasted?.type).toBe('section')
    expect(pasted?.id).not.toBe(tree.children[1].id)
    expect(pasted?.children?.[0].children?.[0].id).not.toBe(tree.children[1].children?.[0].children?.[0].id)
    expect(pasted?.children?.[0].children?.[0].type).toBe('column')
  })

  it('ページビルダーのブロックでない文字列・新しい版・知らない種類・置けない入れ子は取り出さない', () => {
    const heading = node('heading')
    const badNesting = node('section', [node('column', [])])

    expect(parseClipboard(registry, 'こんにちは', 1)).toBeNull()
    expect(parseClipboard(registry, JSON.stringify({ node: heading }), 1)).toBeNull()
    expect(parseClipboard(registry, serializeClipboard(heading, 2), 1)).toBeNull()
    expect(parseClipboard(registry, serializeClipboard({ ...heading, type: 'unknown' }, 1), 1)).toBeNull()
    expect(parseClipboard(registry, serializeClipboard(badNesting, 1), 1)).toBeNull()
    expect(parseClipboard(registry, serializeClipboard({ ...heading, children: [] }, 1), 1)).toBeNull()
  })

  it('選択中のブロックの後ろに貼り付ける', () => {
    const { content: tree, ids } = content()

    expect(findPastePosition(registry, tree, ids.heading1, 'heading')).toEqual({ parentId: ids.section1, index: 1 })
  })

  it('後ろに置けなければ選択中のブロックの中(末尾)に貼り付ける', () => {
    const { content: tree, ids } = content()

    expect(findPastePosition(registry, tree, ids.column1, 'heading')).toEqual({ parentId: ids.column1, index: 0 })
    expect(findPastePosition(registry, tree, ids.section1, 'heading')).toEqual({ parentId: ids.section1, index: 2 })
  })

  it('中にも置けなければ、置ける祖先の後ろに貼り付ける', () => {
    const { content: tree, ids } = content()

    expect(findPastePosition(registry, tree, ids.column1, 'row')).toEqual({ parentId: ids.section2, index: 1 })
    expect(findPastePosition(registry, tree, ids.heading2, 'section')).toEqual({ parentId: null, index: 1 })
  })

  it('選択していなければページの末尾、どこにも置けなければ null', () => {
    const { content: tree, ids } = content()

    expect(findPastePosition(registry, tree, null, 'section')).toEqual({ parentId: null, index: 2 })
    expect(findPastePosition(registry, tree, null, 'heading')).toBeNull()
    expect(findPastePosition(registry, tree, ids.heading1, 'column')).toBeNull()
  })
})
