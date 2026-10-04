import { describe, expect, it, vi } from 'vitest'
import { ApiError, type BuilderApi } from './api'
import { createBuilderStore } from './store'
import { createNode } from './nodes'
import type { BlockDefinition, BuilderContent, BuilderStatePayload, BuilderTemplate, Registry, ShowPayload } from './types'

// biscuit の BlockRegistry::toArray() のうち、テストに使う部分
function block(children: string[], props: BlockDefinition['props'] = {}): BlockDefinition {
  return { label: '', category: 'basic', icon: '', children, props, styles: [], allowedParents: [] }
}

const registry: Registry = {
  rootChildren: ['section'],
  blocks: {
    section: block(['row', 'heading']),
    row: block(['column']),
    column: block(['heading'], { span: { label: '', type: 'int', default: 12, min: 1, max: 12 } }),
    heading: block([], { text: { label: '', type: 'string', default: '見出し' } }),
  },
  styles: {},
}

function statePayload(content: BuilderContent, updatedAt: string | null = '2026-10-04T00:00:00+09:00'): BuilderStatePayload {
  return {
    page: { type: 'single_page', id: 1, title: '会社概要', path: '/about', use_builder: true },
    content,
    published: false,
    published_at: null,
    has_unpublished_changes: false,
    updated_at: updatedAt,
  }
}

function showPayload(content: BuilderContent = { version: 1, children: [] }): ShowPayload {
  return {
    ...statePayload(content),
    registry,
    image_base_url: 'https://admin.example.com/storage/',
    breadcrumbs: [],
    gallery_categories: [],
    timezone: 'Asia/Tokyo',
    theme: { colors: {}, fonts: { heading: null, body: null }, css: null },
    can_edit_css: true,
  }
}

/**
 * 偽の API。保存は送られた内容をそのまま返す。
 */
function fakeApi(overrides: Partial<Record<keyof BuilderApi, unknown>> = {}) {
  const api = {
    features: { publish: true, versions: true, transfer: true, templates: true, components: true, css: true },
    show: vi.fn(async () => showPayload()),
    save: vi.fn(async (content: BuilderContent) => statePayload(JSON.parse(JSON.stringify(content)), '2026-10-04T00:00:01+09:00')),
    publish: vi.fn(async () => ({ ...statePayload({ version: 1, children: [] }), published: true })),
    discard: vi.fn(async () => statePayload({ version: 1, children: [] })),
    previewUrl: vi.fn(async () => ({ url: 'https://example.com/builder-preview?x', expires_at: '' })),
    components: vi.fn(async () => []),
    templates: vi.fn(async () => []),
    saveTemplate: vi.fn(async () => ({})),
    deleteTemplate: vi.fn(async () => ({})),
    versions: vi.fn(async () => []),
    version: vi.fn(),
    exportContent: vi.fn(),
    importFile: vi.fn(async (): Promise<unknown> => ({})),
    articleList: vi.fn(async () => ({ articles: [{ id: 1 }] })),
    navigation: vi.fn(async () => ({ items: [] })),
    gallery: vi.fn(async () => ({ images: [] })),
    uploadImage: vi.fn(async () => ({ path: 'image/builder/a.png', url: '' })),
  }

  // 上書きしても、テストからは vi.fn() の型のまま扱う
  return Object.assign(api, overrides)
}

async function loadedStore(api = fakeApi()) {
  const store = createBuilderStore(api as unknown as BuilderApi)
  await store.load()

  return { store, api }
}

describe('builder store', () => {
  it('読み込みで定義・状態を入れる。失敗したら loadError', async () => {
    const { store } = await loadedStore()

    expect(store.state.loaded).toBe(true)
    expect(store.state.page?.title).toBe('会社概要')
    expect(store.definition('heading')).toBeDefined()

    const failed = createBuilderStore(fakeApi({ show: vi.fn(async () => { throw new Error('x') }) }) as unknown as BuilderApi)
    await failed.load()
    expect(failed.state.loadError).toBe(true)
  })

  it('追加・移動・削除・複製と、元に戻す・やり直す', async () => {
    const { store } = await loadedStore()

    expect(store.add('section', null, 0)).toBe(true)
    const section = store.state.content.children[0]
    expect(store.state.selectedId).toBe(section.id)
    expect(store.state.dirty).toBe(true)
    expect(store.state.canUndo).toBe(true)

    // 置けない場所には置かない
    expect(store.add('heading', null, 0)).toBe(false)

    store.addNearSelection('heading')
    const heading = section.children![0]
    expect(heading.type).toBe('heading')

    store.duplicate(heading.id)
    expect(section.children).toHaveLength(2)
    expect(store.state.selectedId).toBe(section.children![1].id)
    expect(section.children![1].id).not.toBe(heading.id)

    store.select(heading.id)
    store.moveSelectedBy(1)
    expect(section.children![1].id).toBe(heading.id)

    store.remove(heading.id)
    expect(store.state.content.children[0].children).toHaveLength(1)

    store.undo()
    expect(store.state.content.children[0].children).toHaveLength(2)
    expect(store.state.canRedo).toBe(true)
    store.redo()
    expect(store.state.content.children[0].children).toHaveLength(1)
  })

  it('行にカラムを足すと幅をそろえる', async () => {
    const { store } = await loadedStore()
    store.add('section', null, 0)
    const sectionId = store.state.content.children[0].id
    store.add('row', sectionId, 0)
    const row = store.state.content.children[0].children![0]

    store.add('column', row.id, 0)
    store.add('column', row.id, 1)

    expect(row.children!.map(column => column.props.span)).toEqual([6, 6])
  })

  it('選択中のブロックの親を選ぶ', async () => {
    const { store } = await loadedStore()
    store.add('section', null, 0)
    const section = store.state.content.children[0]
    store.add('heading', section.id, 0)

    store.selectParent()
    expect(store.state.selectedId).toBe(section.id)
    store.selectParent()
    expect(store.state.selectedId).toBeNull()
  })

  it('コピー・切り取り・貼り付け', async () => {
    const { store } = await loadedStore()
    store.add('section', null, 0)
    const section = store.state.content.children[0]
    store.add('heading', section.id, 0)

    const text = store.copySelected()
    expect(text).not.toBeNull()
    expect(store.paste(text!)).toBe(true)
    expect(section.children).toHaveLength(2)

    store.copySelected(true)
    expect(section.children).toHaveLength(1)

    expect(store.paste('ほかの文字')).toBe(false)
    expect(store.state.message?.type).toBe('warning')
  })

  it('項目の変更は同じ項目なら 1 回の操作にまとめ、そのブロックのエラーを消す', async () => {
    const { store } = await loadedStore()
    store.add('section', null, 0)
    const section = store.state.content.children[0]
    store.add('heading', section.id, 0)
    const heading = section.children![0]
    store.state.errors = { [`nodes.${heading.id}`]: ['エラー'] }

    store.updateProp(heading.id, 'text', 'あ')
    store.updateProp(heading.id, 'text', 'あい')
    expect(heading.props.text).toBe('あい')
    expect(store.state.errors).toEqual({})

    store.undo()
    expect(store.state.content.children[0].children![0].props.text).toBe('見出し')

    store.redo()
    const redone = store.state.content.children[0].children![0]
    store.updateStyle(redone.id, 'color', '#ff0000')
    expect(redone.styles.color).toBe('#ff0000')
    store.updateStyle(redone.id, 'color', null)
    expect(redone.styles.color).toBeUndefined()

    store.updateClasses(redone.id, ['lead'])
    expect(redone.classes).toEqual(['lead'])
    store.updateClasses(redone.id, [])
    expect(redone.classes).toBeUndefined()

    expect(store.updateVisibility(redone.id, { hideOn: ['mobile'] })).toBe(true)
    expect(redone.visibility).toEqual({ hideOn: ['mobile'] })

    store.updateCss(' .a { color: red; } ')
    expect(store.state.content.css).toBe('.a { color: red; }')
    store.updateCss('')
    expect(store.state.content.css).toBeUndefined()
  })

  it('差し替えられる項目と、差し替えた値', async () => {
    const { store } = await loadedStore()
    store.add('section', null, 0)
    const section = store.state.content.children[0]
    store.add('heading', section.id, 0)
    const heading = section.children![0]

    store.updateExposed(heading.id, 'text', '見出しの文字')
    expect(heading.exposed).toEqual({ text: '見出しの文字' })
    store.updateExposed(heading.id, 'text', null)
    expect(heading.exposed).toBeUndefined()

    store.updateOverride(heading.id, 'x.text', '差し替え')
    expect(heading.props.values).toEqual({ 'x.text': '差し替え' })
    store.updateOverride(heading.id, 'x.text', '')
    expect(heading.props.values).toEqual({})
    expect(store.state.canUndo).toBe(true)
  })

  it('一覧の読み込みとテンプレートの削除。失敗したら null・false と文言', async () => {
    const api = fakeApi()
    const { store } = await loadedStore(api)

    expect(await store.loadVersions()).toEqual([])
    expect(await store.loadTemplates()).toEqual([])
    expect(await store.deleteTemplate(1)).toBe(true)

    api.versions.mockRejectedValueOnce(new Error('x'))
    expect(await store.loadVersions()).toBeNull()
    expect(store.state.message?.text).toBe('版の履歴を読み込めませんでした。')
    api.templates.mockRejectedValueOnce(new Error('x'))
    expect(await store.loadTemplates()).toBeNull()
    api.deleteTemplate.mockRejectedValueOnce(new Error('x'))
    expect(await store.deleteTemplate(1)).toBe(false)
    expect(store.state.message?.text).toBe('テンプレートの削除に失敗しました。')
  })

  it('ドラッグで置けるかと、置いたときの追加・移動', async () => {
    const { store } = await loadedStore()
    store.add('section', null, 0)
    const section = store.state.content.children[0]

    store.state.dragging = { kind: 'new', type: 'heading' }
    expect(store.canDropInto(null)).toBe(false)
    expect(store.canDropInto(section.id)).toBe(true)
    store.state.dropTarget = { parentId: section.id, index: 0 }
    store.drop()
    expect(section.children).toHaveLength(1)
    expect(store.state.dragging).toBeNull()

    // 自分の中へは動かせない
    store.state.dragging = { kind: 'move', id: section.id, type: 'section' }
    expect(store.canDropInto(section.id)).toBe(false)
    store.endDrag()
  })

  it('保存は整えた内容に置き換え、自動保存では置き換えない。保存中に変えたら dirty のまま', async () => {
    const api = fakeApi()
    const { store } = await loadedStore(api)
    store.add('section', null, 0)

    expect(await store.save()).toBe(true)
    expect(store.state.dirty).toBe(false)
    expect(store.state.updatedAt).toBe('2026-10-04T00:00:01+09:00')
    expect(store.state.message?.type).toBe('success')

    store.state.message = null
    store.add('section', null, 1)
    const before = store.state.content
    await store.save(true)
    expect(store.state.content).toBe(before)
    expect(store.state.message).toBeNull()
    expect(store.state.lastSavedAt).not.toBeNull()

    let resolve!: (value: BuilderStatePayload) => void
    api.save.mockImplementationOnce(() => new Promise(r => { resolve = r }))
    store.add('section', null, 2)
    const saving = store.save()
    store.add('section', null, 3)
    resolve(statePayload({ version: 1, children: [] }, 'later'))
    await saving
    expect(store.state.dirty).toBe(true)
    expect(store.state.content.children).toHaveLength(4)
  })

  it('検証のエラー(422)はノードを選び、ほかの管理者の保存(409)は conflict にする', async () => {
    const api = fakeApi()
    const { store } = await loadedStore(api)
    store.add('section', null, 0)
    const id = store.state.content.children[0].id

    api.save.mockRejectedValueOnce(new ApiError(422, { message: '正しくありません', errors: { [`nodes.${id}`]: ['だめ'] } }))
    store.select(null)
    expect(await store.save()).toBe(false)
    expect(store.state.selectedId).toBe(id)
    expect(store.state.errors[`nodes.${id}`]).toEqual(['だめ'])
    expect(store.state.message).toEqual({ type: 'danger', text: '正しくありません' })

    api.save.mockRejectedValueOnce(new ApiError(409, {}))
    expect(await store.save()).toBe(false)
    expect(store.state.conflict).toBe(true)

    api.save.mockRejectedValueOnce(new Error('network'))
    await store.save()
    expect(store.state.message).toEqual({ type: 'danger', text: '下書きの保存に失敗しました。' })
  })

  it('公開は未保存の変更を先に保存する。変更の破棄は元に戻せる', async () => {
    const api = fakeApi()
    const { store } = await loadedStore(api)
    store.add('section', null, 0)

    await store.publish()
    expect(api.save).toHaveBeenCalledTimes(1)
    expect(store.state.published).toBe(true)
    expect(store.state.message?.text).toBe('公開しました。')

    // 公開の応答の内容(空)に置き換わっている
    expect(store.state.content.children).toHaveLength(0)
    store.add('section', null, 0)
    await store.discard()
    expect(store.state.content.children).toHaveLength(0)
    store.undo()
    expect(store.state.content.children).toHaveLength(1)
  })

  it('プレビューは未保存の変更を保存してから URL を返す', async () => {
    const api = fakeApi()
    const { store } = await loadedStore(api)
    store.add('section', null, 0)

    expect(await store.previewUrl()).toBe('https://example.com/builder-preview?x')
    expect(api.save).toHaveBeenCalledTimes(1)
  })

  it('テンプレート・版・ファイルで内容を置き換える(元に戻せる。CSS は書ける人のときだけ)', async () => {
    const heading = createNode(registry, 'heading')
    const section = { ...createNode(registry, 'section'), children: [heading] }
    const api = fakeApi({
      version: vi.fn(async () => ({ content: { version: 1, children: [section], css: '.v{}' } })),
      importFile: vi.fn(async () => ({ content: { version: 1, children: [section] }, warnings: [] })),
    })
    const { store } = await loadedStore(api)
    const template: BuilderTemplate = { id: 1, name: 'LP', description: null, content: { version: 1, children: [section], css: '.t{}' } } as BuilderTemplate

    store.applyTemplate(template)
    expect(store.state.content.children[0].id).not.toBe(section.id)
    expect(store.state.content.css).toBe('.t{}')
    expect(store.state.selectedId).toBeNull()
    expect(store.state.message?.text).toBe('テンプレート「LP」を使いました。')

    expect(await store.restoreVersion(3, '10/04 09:00')).toBe(true)
    expect(store.state.content.children[0].id).toBe(section.id)
    expect(store.state.content.css).toBe('.v{}')

    await store.importFile(new File(['{}'], 'page.json'))
    expect(store.state.content.css).toBeUndefined()
    expect(store.state.message?.text).toBe('ファイル「page.json」を読み込みました。')

    store.undo()
    expect(store.state.content.css).toBe('.v{}')

    // CSS を書けない人は、CSS を置き換えない
    store.state.canEditCss = false
    store.applyTemplate(template)
    expect(store.state.content.css).toBe('.v{}')

    api.importFile.mockRejectedValueOnce(new ApiError(422, { message: '形が違います' }))
    await expect(store.importFile(new File(['x'], 'x.json'))).rejects.toThrow('形が違います')
  })

  it('テンプレートの保存の失敗は理由の文言を返す', async () => {
    const api = fakeApi()
    const { store } = await loadedStore(api)

    expect(await store.saveAsTemplate('LP', '')).toBeNull()
    api.saveTemplate.mockRejectedValueOnce(new ApiError(422, { message: '名前が重なっています' }))
    expect(await store.saveAsTemplate('LP', '')).toBe('名前が重なっています')
    api.saveTemplate.mockRejectedValueOnce(new Error('network'))
    expect(await store.saveAsTemplate('LP', '')).toBe('テンプレートの保存に失敗しました。')
  })

  it('見本は同じ条件では取得し直さず、失敗したら空にして次は取得し直す', async () => {
    const api = fakeApi()
    const { store } = await loadedStore(api)

    expect(await store.articleListPreview({ limit: 3 })).toEqual([{ id: 1 }])
    await store.articleListPreview({ limit: 3 })
    expect(api.articleList).toHaveBeenCalledTimes(1)
    expect(api.articleList).toHaveBeenCalledWith({ limit: '3', order: 'newest', parentPath: '', tag: '' })

    api.gallery.mockRejectedValueOnce(new Error('x'))
    expect(await store.galleryPreview({})).toEqual([])
    await store.galleryPreview({})
    expect(api.gallery).toHaveBeenCalledTimes(2)

    await store.navigationPreview('pages')
    await store.navigationPreview('other')
    expect(api.navigation.mock.calls).toEqual([['pages'], ['site']])

    await store.loadComponents()
    await store.loadComponents()
    expect(api.components).toHaveBeenCalledTimes(1)
  })

  it('画像のアップロードと URL', async () => {
    const api = fakeApi()
    const { store } = await loadedStore(api)

    expect(await store.uploadImage(new File(['x'], 'a.png'))).toBe('image/builder/a.png')
    expect(store.imageUrl('image/builder/a.png')).toBe('https://admin.example.com/storage/image/builder/a.png')
    expect(store.imageUrl('')).toBeNull()
    expect(store.imageUrl(null)).toBeNull()
  })
})
