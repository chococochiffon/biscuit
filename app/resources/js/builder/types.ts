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

export type PropType = 'string' | 'richtext' | 'int' | 'enum' | 'url' | 'image'

export interface PropDefinition {
  label: string
  type: PropType
  default: unknown
  max?: number
  min?: number
  options?: string[]
  nullable?: boolean
}

export interface BlockDefinition {
  label: string
  category: 'layout' | 'basic'
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

export interface ShowPayload extends BuilderStatePayload {
  registry: Registry
  image_base_url: string
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
  }
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
