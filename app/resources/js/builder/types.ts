// ページビルダーのエディタで使う型。内容(ノードの木)の形は biscuit の Support\Builder と chococo の types/builder.ts に合わせる

export type Device = 'desktop' | 'tablet' | 'mobile'

// デスクトップのスタイルを上書きできる端末
export type ResponsiveDevice = Exclude<Device, 'desktop'>

export type BuilderStyles = Record<string, string>

export interface BuilderNode {
  // 種類_ULID(例: heading_01K8...)
  id: string
  type: string
  props: Record<string, unknown>
  styles: BuilderStyles
  responsive?: Partial<Record<ResponsiveDevice, BuilderStyles>>
  // 中にブロックを置ける種類だけが持つ
  children?: BuilderNode[]
}

export interface BuilderContent {
  version: number
  children: BuilderNode[]
}

export type PropType = 'string' | 'richtext' | 'int' | 'enum' | 'url' | 'image' | 'bool'

export interface PropDefinition {
  label: string
  type: PropType
  default: unknown
  max?: number
  min?: number
  options?: string[]
  nullable?: boolean
  // 選択肢を登録済みのデータから作る項目(gallery-categories: ギャラリーの分類)
  source?: string
}

export interface BlockDefinition {
  label: string
  // layout・basic・cms(記事一覧など CMS のデータを表示するブロック)
  category: 'layout' | 'basic' | 'cms'
  // Bootstrap Icons の名前
  icon: string
  children: string[]
  props: Record<string, PropDefinition>
  styles: string[]
  // null はページの直下
  allowedParents: (string | null)[]
}

// biscuit の BlockRegistry::toArray()
export interface Registry {
  rootChildren: string[]
  blocks: Record<string, BlockDefinition>
  styles: Record<string, string | string[]>
}

export interface PageInfo {
  type: 'top' | 'single_page'
  id: number | null
  title: string
  path: string
  // 公開側にページビルダーの内容を出す設定になっているか(固定ページの use_builder・サイト設定の top_use_builder)
  use_builder: boolean
}

// PageBuilderJsonController がエディタに返すビルダーの状態
export interface BuilderStatePayload {
  page: PageInfo
  content: BuilderContent
  published: boolean
  published_at: string | null
  has_unpublished_changes: boolean
  updated_at: string | null
}

// パンくずの項目(path が null の項目はリンクしない。末尾は表示しているページ)
export interface Breadcrumb {
  label: string
  path: string | null
}

export interface ShowPayload extends BuilderStatePayload {
  registry: Registry
  image_base_url: string
  // 編集しているページのパンくず(トップは空)
  breadcrumbs: Breadcrumb[]
  // ギャラリーの分類(ギャラリーのブロックの選択肢)
  gallery_categories: { id: number, name: string }[]
}

// ギャラリーのブロックの見本の画像(biscuit の GalleryImageResource のうち、エディタで使うもの)
export interface GalleryImageSummary {
  id: number
  name: string
  image_url: string
}

// 画面(resources/views/admin/builder/edit.blade.php)が渡す設定
export interface EditorConfig {
  backUrl: string
  endpoints: {
    show: string
    update: string
    publish: string
    discard: string
    previewUrl: string
    images: string
    // テンプレートの一覧・保存(削除は末尾に /{id} を付ける)
    templates: string
    // 記事一覧・ナビゲーションのブロックの Canvas の見本
    articleList: string
    navigation: string
    gallery: string
  }
}

// ナビゲーションのブロックの項目(biscuit の BlockDataResolver::navigationItems())
export interface NavigationItem {
  label: string
  path: string
  prefix: boolean
}

// 記事一覧のブロックの見本の記事(biscuit の ArticleResource のうち、エディタで使うもの)
export interface ArticleSummary {
  id: number
  title: string
  path: string
  content: string | null
  thumbnail_url: string | null
  published_at: string | null
}

// ページビルダーのテンプレート(PageBuilderTemplateJsonController)
export interface BuilderTemplate {
  id: number
  name: string
  description: string | null
  node_count: number
  content: BuilderContent
}

// ドラッグ中のもの(パレットの新しいブロック、または置いてあるブロック)
export type Dragging =
  | { kind: 'new', type: string }
  | { kind: 'move', id: string, type: string }

// ドロップ先(parentId が null ならページの直下)。from は入れる位置を示している場所(Canvas とコンポーネントツリー)
export interface DropTarget {
  parentId: string | null
  index: number
  from?: 'canvas' | 'tree'
}
