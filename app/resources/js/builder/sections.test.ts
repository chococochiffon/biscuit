import { describe, expect, it } from 'vitest'
import { isFreeSurface } from './layout'
import { canPlace } from './nodes'
import { availableSectionPresets, buildSection, SECTION_PRESETS } from './sections'
import type { BlockDefinition, BuilderNode, Registry } from './types'

function block(children: string[], props: BlockDefinition['props'] = {}): BlockDefinition {
  return { label: '', category: 'basic', icon: '', children, props, styles: [], allowedParents: [] }
}

const CONTENT = ['heading', 'text', 'image', 'button', 'article-list', 'gallery']

// biscuit の BlockRegistry::toArray() のうち、ひな形に使う部分
const registry: Registry = {
  rootChildren: ['section', 'global'],
  blocks: {
    'section': block(['container', 'row', ...CONTENT], { backgroundImage: { label: '', type: 'image', default: null } }),
    'container': block(['row', ...CONTENT]),
    'row': block(['column'], { gap: { label: '', type: 'int', default: 3 } }),
    'column': block(CONTENT, { span: { label: '', type: 'int', default: 12 }, spanMobile: { label: '', type: 'int', default: null } }),
    'heading': block([], { text: { label: '', type: 'string', default: '見出し' }, level: { label: '', type: 'int', default: 2 } }),
    'text': block([], { html: { label: '', type: 'richtext', default: '<p>テキスト</p>' } }),
    'image': block([], { src: { label: '', type: 'image', default: null }, alt: { label: '', type: 'string', default: '' } }),
    'button': block([], { text: { label: '', type: 'string', default: 'ボタン' }, variant: { label: '', type: 'enum', default: 'primary' } }),
    'article-list': block([], { limit: { label: '', type: 'int', default: 6 } }),
    'gallery': block([], { limit: { label: '', type: 'int', default: 8 } }),
  },
  styles: {},
}

function walk(node: BuilderNode, visit: (node: BuilderNode, parent: BuilderNode) => void): void {
  for (const child of node.children ?? []) {
    visit(child, node)
    walk(child, visit)
  }
}

describe('section presets', () => {
  it('どのひな形も、置ける場所だけにブロックを置き、ID はすべて別になる', () => {
    for (const preset of SECTION_PRESETS) {
      const section = buildSection(registry, preset.key, 1)!
      const ids = [section.id]

      expect(section.type).toBe('section')
      walk(section, (node, parent) => {
        expect(canPlace(registry, parent.type, node.type)).toBe(true)
        // 定義の既定値に、ひな形の値を重ねる
        expect(Object.keys(node.props)).toEqual(expect.arrayContaining(Object.keys(registry.blocks[node.type].props)))
        ids.push(node.id)
      })
      expect(new Set(ids).size).toBe(ids.length)
    }
  })

  it('作るたびに新しい ID を振る', () => {
    expect(buildSection(registry, 'hero', 1)!.id).not.toBe(buildSection(registry, 'hero', 1)!.id)
  })

  it('カラムはスマートフォンでは 1 列にする', () => {
    const columns: BuilderNode[] = []
    walk(buildSection(registry, 'features', 1)!, node => node.type === 'column' && columns.push(node))

    expect(columns).toHaveLength(3)
    expect(columns.map(column => [column.props.span, column.props.spanMobile])).toEqual([[4, 12], [4, 12], [4, 12]])
  })

  it('中のブロックが定義にないひな形は使えず、セクションを置けないエディタではどれも使えない', () => {
    const { gallery: _gallery, ...blocks } = registry.blocks
    const withoutGallery = { ...registry, blocks }

    expect(availableSectionPresets(registry, 1)).toHaveLength(SECTION_PRESETS.length)
    expect(availableSectionPresets(withoutGallery, 1).map(preset => preset.key)).not.toContain('gallery')
    expect(buildSection(withoutGallery, 'gallery', 1)).toBeNull()

    const component = { ...registry, rootChildren: ['container', 'heading'] }
    expect(availableSectionPresets(component, 1)).toEqual([])
    expect(buildSection(component, 'blank', 1)).toBeNull()
    expect(buildSection(registry, 'unknown', 1)).toBeNull()
  })

  it('v2 のひな形は、自由配置の面の直下のブロックだけが位置を持ち、行・カラムを使わない', () => {
    const { container: _container, row: _row, column: _column, ...v2Blocks } = registry.blocks
    const v2: Registry = {
      ...registry,
      blocks: {
        ...v2Blocks,
        section: block(['box', ...CONTENT], registry.blocks.section.props),
        box: { ...block(['box', ...CONTENT]), layoutHeight: true },
        image: { ...registry.blocks.image, layoutHeight: true },
      },
    }

    expect(availableSectionPresets(v2, 2)).toHaveLength(SECTION_PRESETS.length)
    // v1 の作り(行・カラム)は v2 の定義では作れない
    expect(availableSectionPresets(v2, 1)).toEqual([])

    for (const preset of SECTION_PRESETS) {
      const section = buildSection(v2, preset.key, 2)!

      expect(section.layout).toBeUndefined()
      walk(section, (node, parent) => {
        expect(canPlace(v2, parent.type, node.type)).toBe(true)
        if (isFreeSurface(parent.type, 2, false)) {
          expect(node.layout?.desktop).toBeDefined()
          expect(node.layout!.desktop.x + node.layout!.desktop.w).toBeLessThanOrEqual(100)
          expect(Number.isInteger(node.layout!.desktop.y)).toBe(true)
        }
        else {
          expect(node.layout).toBeUndefined()
        }
      })
    }
  })
})
