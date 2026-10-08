import { t } from './i18n'
import { createNode } from './nodes'
import { FREE_LAYOUT_VERSION } from './layout'
import type { BuilderNode, BuilderStyles, LayoutBox, Registry, ResponsiveDevice } from './types'

// セクションのひな形: 「+ セクションを追加」で選ぶ、中身の入ったセクション。
// ブロックはエディタの定義(createNode の既定値)から作り、ひな形ごとの値だけを重ねる(画面から切り離した関数。sections.test.ts で確かめる)。
// 内容の版ごとに作りが違う: v1 はコンテナ・行・カラムで流し込み(build)、v2 は自由配置でブロックを座標で置く(buildFree)

export interface SectionPreset {
  key: string
  label: string
  description: string
  // Bootstrap Icons の名前
  icon: string
  build: (registry: Registry) => BuilderNode
  buildFree: (registry: Registry) => BuilderNode
}

interface NodeOptions {
  props?: Record<string, unknown>
  styles?: BuilderStyles
  responsive?: Partial<Record<ResponsiveDevice, BuilderStyles>>
  children?: BuilderNode[]
  // 自由配置(v2)のデスクトップの位置
  at?: LayoutBox
}

/**
 * 定義の既定値のブロックに、ひな形の値を重ねる。
 */
function block(registry: Registry, type: string, options: NodeOptions = {}): BuilderNode {
  const node = createNode(registry, type)
  Object.assign(node.props, options.props ?? {})
  Object.assign(node.styles, options.styles ?? {})

  if (options.responsive) {
    node.responsive = options.responsive
  }
  if (options.children) {
    node.children = options.children
  }
  if (options.at) {
    node.layout = { desktop: options.at }
  }

  return node
}

/**
 * 自由配置(v2)のセクション。中のブロックは at で置く(下の余白はいちばん下のブロックの下に空ける)。
 */
function freeSection(registry: Registry, styles: BuilderStyles, children: BuilderNode[]): BuilderNode {
  return block(registry, 'section', { styles: { paddingBottom: '48px', ...styles }, children })
}

const at = (x: number, y: number, w: number, h?: number): LayoutBox => (h === undefined ? { x, y, w } : { x, y, w, h })

/**
 * セクション > コンテナの中にブロックを並べる(ひな形の共通の外側)。
 */
function section(registry: Registry, styles: BuilderStyles, children: BuilderNode[], containerStyles: BuilderStyles = {}): BuilderNode {
  return block(registry, 'section', {
    styles: { paddingTop: '64px', paddingBottom: '64px', ...styles },
    children: [block(registry, 'container', { styles: containerStyles, children })],
  })
}

function heading(registry: Registry, text: string, styles: BuilderStyles = {}): BuilderNode {
  return block(registry, 'heading', { props: { text, level: 2 }, styles: { marginBottom: '16px', ...styles } })
}

function text(registry: Registry, html: string, styles: BuilderStyles = {}): BuilderNode {
  return block(registry, 'text', { props: { html }, styles })
}

/**
 * 行の中にカラムを並べる。スマートフォンでは 1 列(幅 12)にする。
 */
function row(registry: Registry, columns: { span: number, children: BuilderNode[] }[]): BuilderNode {
  return block(registry, 'row', {
    children: columns.map(column => block(registry, 'column', { props: { span: column.span, spanMobile: 12 }, children: column.children })),
  })
}

export const SECTION_PRESETS: SectionPreset[] = [
  {
    key: 'blank',
    label: t('空のセクション'),
    description: t('中身のないセクション。ブロックを自由に置きます。'),
    icon: 'bi-square',
    build: registry => section(registry, {}, []),
    buildFree: registry => block(registry, 'section', { styles: { minHeight: '320px' } }),
  },
  {
    key: 'hero',
    label: t('メインビジュアル'),
    description: t('大きな見出し・説明・ボタンを中央に置きます。'),
    icon: 'bi-megaphone',
    build: registry => section(registry, { paddingTop: '96px', paddingBottom: '96px', backgroundColor: 'theme:light', textAlign: 'center' }, [
      block(registry, 'heading', {
        props: { text: t('ここにキャッチコピー'), level: 1 },
        styles: { fontSize: '44px', fontWeight: '700', marginBottom: '16px' },
        responsive: { mobile: { fontSize: '30px' } },
      }),
      text(registry, `<p>${t('サイトやサービスの紹介を書きます。')}</p>`, { fontSize: '18px', marginBottom: '24px' }),
      block(registry, 'button', { props: { text: t('詳しく見る') } }),
    ], { maxWidth: '960px' }),
    buildFree: registry => freeSection(registry, { minHeight: '480px', backgroundColor: 'theme:light', textAlign: 'center' }, [
      block(registry, 'heading', {
        props: { text: t('ここにキャッチコピー'), level: 1 },
        styles: { fontSize: '44px', fontWeight: '700' },
        responsive: { mobile: { fontSize: '30px' } },
        at: at(10, 120, 80),
      }),
      block(registry, 'text', { props: { html: `<p>${t('サイトやサービスの紹介を書きます。')}</p>` }, styles: { fontSize: '18px' }, at: at(20, 220, 60) }),
      block(registry, 'button', { props: { text: t('詳しく見る') }, at: at(35, 300, 30) }),
    ]),
  },
  {
    key: 'text',
    label: t('見出しと文章'),
    description: t('見出しと文章を 1 つずつ置きます。'),
    icon: 'bi-text-paragraph',
    build: registry => section(registry, {}, [
      heading(registry, t('見出し')),
      text(registry, `<p>${t('ここに文章を書きます。')}</p>`),
    ], { maxWidth: '960px' }),
    buildFree: registry => freeSection(registry, {}, [
      block(registry, 'heading', { props: { text: t('見出し'), level: 2 }, at: at(10, 64, 80) }),
      block(registry, 'text', { props: { html: `<p>${t('ここに文章を書きます。')}</p>` }, at: at(10, 130, 80) }),
    ]),
  },
  {
    key: 'image-text',
    label: t('画像と文章'),
    description: t('左に画像、右に見出し・文章・ボタンを並べます。'),
    icon: 'bi-layout-split',
    build: registry => section(registry, {}, [
      row(registry, [
        { span: 6, children: [block(registry, 'image', { styles: { width: '100%' } })] },
        {
          span: 6,
          children: [
            heading(registry, t('見出し')),
            text(registry, `<p>${t('ここに文章を書きます。')}</p>`, { marginBottom: '16px' }),
            block(registry, 'button', { props: { text: t('詳しく見る') } }),
          ],
        },
      ]),
    ]),
    buildFree: registry => freeSection(registry, {}, [
      block(registry, 'image', { at: at(5, 64, 42, 320) }),
      block(registry, 'heading', { props: { text: t('見出し'), level: 2 }, at: at(53, 96, 42) }),
      block(registry, 'text', { props: { html: `<p>${t('ここに文章を書きます。')}</p>` }, at: at(53, 164, 42) }),
      block(registry, 'button', { props: { text: t('詳しく見る') }, at: at(53, 280, 30) }),
    ]),
  },
  {
    key: 'features',
    label: t('3 つの特徴'),
    description: t('見出しの下に、特徴を 3 列で並べます。'),
    icon: 'bi-grid-3x2-gap',
    build: registry => section(registry, {}, [
      heading(registry, t('特徴'), { textAlign: 'center', marginBottom: '32px' }),
      row(registry, [1, 2, 3].map(number => ({
        span: 4,
        children: [
          block(registry, 'heading', { props: { text: t('特徴 :number', { number }), level: 3 }, styles: { fontSize: '20px', marginBottom: '8px' } }),
          text(registry, `<p>${t('特徴の説明を書きます。')}</p>`),
        ],
      }))),
    ]),
    buildFree: registry => freeSection(registry, {}, [
      block(registry, 'heading', { props: { text: t('特徴'), level: 2 }, styles: { textAlign: 'center' }, at: at(10, 64, 80) }),
      ...[5, 36.67, 68.33].map((x, index) => block(registry, 'box', {
        styles: { backgroundColor: 'theme:light', borderRadius: '8px' },
        at: at(x, 140, 26.67, 200),
        children: [
          block(registry, 'heading', { props: { text: t('特徴 :number', { number: index + 1 }), level: 3 }, styles: { fontSize: '20px' }, at: at(8, 24, 84) }),
          block(registry, 'text', { props: { html: `<p>${t('特徴の説明を書きます。')}</p>` }, at: at(8, 72, 84) }),
        ],
      })),
    ]),
  },
  {
    key: 'cta',
    label: t('呼びかけ'),
    description: t('短い見出しとボタンで、問い合わせなどへ誘います。'),
    icon: 'bi-hand-index',
    build: registry => section(registry, { paddingTop: '48px', paddingBottom: '48px', backgroundColor: 'theme:primary', color: '#ffffff', textAlign: 'center' }, [
      heading(registry, t('お気軽にお問い合わせください'), { color: '#ffffff' }),
      block(registry, 'button', { props: { text: t('お問い合わせ'), variant: 'secondary' } }),
    ]),
    buildFree: registry => freeSection(registry, { backgroundColor: 'theme:primary', color: '#ffffff', textAlign: 'center' }, [
      block(registry, 'heading', { props: { text: t('お気軽にお問い合わせください'), level: 2 }, styles: { color: '#ffffff' }, at: at(10, 48, 80) }),
      block(registry, 'button', { props: { text: t('お問い合わせ'), variant: 'secondary' }, at: at(35, 120, 30) }),
    ]),
  },
  {
    key: 'articles',
    label: t('新着記事'),
    description: t('見出しの下に、公開中の記事を新しい順に並べます。'),
    icon: 'bi-newspaper',
    build: registry => section(registry, {}, [
      heading(registry, t('新着記事'), { textAlign: 'center', marginBottom: '24px' }),
      block(registry, 'article-list'),
    ]),
    buildFree: registry => freeSection(registry, {}, [
      block(registry, 'heading', { props: { text: t('新着記事'), level: 2 }, styles: { textAlign: 'center' }, at: at(10, 64, 80) }),
      block(registry, 'article-list', { at: at(5, 130, 90) }),
    ]),
  },
  {
    key: 'gallery',
    label: t('ギャラリー'),
    description: t('見出しの下に、ギャラリーの画像をタイルで並べます。'),
    icon: 'bi-images',
    build: registry => section(registry, {}, [
      heading(registry, t('ギャラリー'), { textAlign: 'center', marginBottom: '24px' }),
      block(registry, 'gallery'),
    ]),
    buildFree: registry => freeSection(registry, {}, [
      block(registry, 'heading', { props: { text: t('ギャラリー'), level: 2 }, styles: { textAlign: 'center' }, at: at(10, 64, 80) }),
      block(registry, 'gallery', { at: at(5, 130, 90) }),
    ]),
  },
]

/**
 * ひな形からセクションを作る(ID はすべて新しく振る)。中のブロックが定義にない・セクションを置けないエディタなら null。
 */
function tryBuild(registry: Registry, preset: SectionPreset, version: number): BuilderNode | null {
  if (!registry.rootChildren.includes('section')) {
    return null
  }

  try {
    return version >= FREE_LAYOUT_VERSION ? preset.buildFree(registry) : preset.build(registry)
  }
  catch {
    return null
  }
}

/**
 * このエディタで使えるひな形(中のブロックがすべて定義にあるものだけ)。セクションを置けないエディタでは空。
 */
export function availableSectionPresets(registry: Registry, version: number): SectionPreset[] {
  return SECTION_PRESETS.filter(preset => tryBuild(registry, preset, version) !== null)
}

/**
 * key のひな形からセクションを作る。見つからない・このエディタで使えなければ null。
 */
export function buildSection(registry: Registry, key: string, version: number): BuilderNode | null {
  const preset = SECTION_PRESETS.find(candidate => candidate.key === key)

  return preset ? tryBuild(registry, preset, version) : null
}
