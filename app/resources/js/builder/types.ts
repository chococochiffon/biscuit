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
  // 表示条件(書かなければ常に表示)
  visibility?: BuilderVisibility
  // 中にブロックを置ける種類だけが持つ
  children?: BuilderNode[]
}

// ブロックの表示条件(biscuit の Support\Builder\Visibility)。日時はサイトのタイムゾーンの YYYY-MM-DDTHH:mm
export interface BuilderVisibility {
  // 表示しない端末(すべては選べない)
  hideOn?: Device[]
  // 表示する期間(開始は含み、終了は含まない。期間の外は公開側に出さない)
  startAt?: string | null
  endAt?: string | null
}

export interface BuilderContent {
  version: number
  children: BuilderNode[]
}

export type PropType = 'string' | 'richtext' | 'int' | 'enum' | 'url' | 'image' | 'bool' | 'video'

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
  // component はグローバルコンポーネント(ページではないため path は null)
  type: 'top' | 'single_page' | 'component'
  id: number | null
  title: string
  path: string | null
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
  // サイトのタイムゾーン(表示条件の期間の判定に使う)
  timezone: string
}

// グローバルコンポーネント(PageBuilderComponentJsonController::index())。content は公開中の内容(未公開なら null)
export interface ComponentSummary {
  id: number
  name: string
  published: boolean
  content: BuilderContent | null
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
    // グローバルコンポーネントのエディタにはプレビューがない(null)
    previewUrl: string | null
    images: string
    // テンプレートの一覧・保存(削除は末尾に /{id} を付ける)
    templates: string
    // 記事一覧・ナビゲーションのブロックの Canvas の見本
    articleList: string
    navigation: string
    gallery: string
    // グローバルコンポーネントの一覧(ブロックの選択肢と見本)
    components: string
    // 版の履歴の一覧(版の内容は末尾に /{id} を付ける)
    versions: string
    // 書き出し・読み込み
    export: string
    import: string
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

// 版の履歴の 1 件(EditsBuilderContent::versionList())。current は公開中の内容の版
export interface BuilderVersionSummary {
  id: number
  published_at: string
  // 公開した管理者(分からなければ null)
  administrator: string | null
  node_count: number
  current: boolean
}

// 版の内容(EditsBuilderContent::versionContent())
export interface BuilderVersion {
  id: number
  published_at: string
  content: BuilderContent
}

// 書き出したファイルを読み込んだ結果(PageBuilderTransferJsonController::import())
export interface BuilderImportResult {
  content: BuilderContent
  // 知らせること(展開したグローバルコンポーネント・外した画像など)
  warnings: string[]
  // 登録し直した画像の数
  images: number
}
