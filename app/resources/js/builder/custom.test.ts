import { describe, expect, it } from 'vitest'
import { applyOverrides, exposedFields, isExposable, setExposed, setOverride } from './custom'
import type { BuilderNode } from './types'

function node(type: string, props: Record<string, unknown>, extra: Partial<BuilderNode> = {}): BuilderNode {
  return { id: `${type}_01K000000000000000000000${type.length.toString().padStart(2, '0')}`, type, props, styles: {}, ...extra }
}

describe('独自コンポーネント', () => {
  const heading = node('heading', { text: 'カードの見出し', level: 3 }, { exposed: { text: '見出し' } })
  const image = node('image', { src: 'image/builder/a.png' }, { exposed: { src: '画像' } })
  const row = node('row', {}, { children: [image] })

  it('差し替えられる項目を木の並び順で集める', () => {
    expect(exposedFields([heading, row]).map(field => [field.key, field.label])).toEqual([
      [`${heading.id}.text`, '見出し'],
      [`${image.id}.src`, '画像'],
    ])
  })

  it('差し替えた値を差し替えられる項目にだけ当てはめ、空の値は部品の値のまま', () => {
    const [appliedHeading, appliedRow] = applyOverrides([heading, row], {
      [`${heading.id}.text`]: '差し替えた見出し',
      [`${heading.id}.level`]: 1,
      [`${image.id}.src`]: '',
    })

    expect(appliedHeading.props).toEqual({ text: '差し替えた見出し', level: 3 })
    expect(appliedRow.children?.[0].props.src).toBe('image/builder/a.png')
    // 元の中身は変えない
    expect(heading.props.text).toBe('カードの見出し')
  })

  it('選択肢をデータから作る項目・差し替えた値の項目は差し替えられる項目にできない', () => {
    expect(isExposable({ label: '', type: 'string', default: '' })).toBe(true)
    expect(isExposable({ label: '', type: 'int', default: null, source: 'gallery-categories' })).toBe(false)
    expect(isExposable({ label: '', type: 'overrides', default: {} })).toBe(false)
  })

  it('差し替えられる項目にする・やめる(空なら exposed ごと消す)', () => {
    const target = node('heading', { text: '' })

    expect(setExposed(target, 'text', '見出し')).toBe(true)
    expect(target.exposed).toEqual({ text: '見出し' })
    expect(setExposed(target, 'text', '見出し')).toBe(false)
    expect(setExposed(target, 'text', null)).toBe(true)
    expect(target.exposed).toBeUndefined()
  })

  it('差し替えた値を変え、空にしたらキーを消す', () => {
    const block = node('custom', { component: 1, values: {} })

    expect(setOverride(block, 'heading_X.text', '1 枚目')).toBe(true)
    expect(block.props.values).toEqual({ 'heading_X.text': '1 枚目' })
    expect(setOverride(block, 'heading_X.text', '')).toBe(true)
    expect(block.props.values).toEqual({})
    expect(setOverride(block, 'heading_X.text', null)).toBe(false)
  })
})
