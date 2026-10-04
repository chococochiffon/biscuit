import { reactive } from 'vue'
import type { Breadcrumb, BuilderContent, BuilderTheme, ComponentSummary, Device, Dragging, DropTarget, PageInfo, Registry } from '../types'

export interface EditorMessage {
  type: 'success' | 'danger' | 'warning'
  text: string
}

/**
 * エディタ全体の状態(部品はこれを読み、変えるときは store の操作を呼ぶ)。
 */
export function createEditorState() {
  return reactive({
    loaded: false,
    loadError: false,
    page: null as PageInfo | null,
    registry: { rootChildren: [], blocks: {}, styles: {} } as Registry,
    imageBaseUrl: '',
    // 編集しているページのパンくず(パンくずのブロックの見本)
    breadcrumbs: [] as Breadcrumb[],
    // ギャラリーの分類(ギャラリーのブロックの選択肢)
    galleryCategories: [] as { id: number, name: string }[],
    // サイトのタイムゾーン(表示条件の期間の判定に使う)
    timezone: 'Asia/Tokyo',
    // テーマ(色のスタイルの theme:名前・ボタン・フォントの見本)
    theme: { colors: {}, fonts: { heading: null, body: null }, css: null } as BuilderTheme,
    // Custom CSS・ブロックの追加のクラス名を書けるか(スーパー管理者だけ)
    canEditCss: false,
    // Custom CSS の画面を開いているか
    cssOpen: false,
    // グローバルコンポーネント(グローバルコンポーネントのブロックの選択肢と見本。使うときに読み込む)
    components: null as ComponentSummary[] | null,
    content: { version: 1, children: [] } as BuilderContent,
    device: 'desktop' as Device,
    selectedId: null as string | null,
    hoveredId: null as string | null,
    dragging: null as Dragging | null,
    dropTarget: null as DropTarget | null,
    published: false,
    publishedAt: null as string | null,
    hasUnpublishedChanges: false,
    updatedAt: null as string | null,
    // 最後に保存してから内容を変えたか
    dirty: false,
    // 内容を変えるたびに増やす(保存中に変えたかの判定に使う)
    revision: 0,
    busy: false,
    // 保存・公開で返ったエラー(ノードの ID → 文言。ノードを特定できないものは content)
    errors: {} as Record<string, string[]>,
    message: null as EditorMessage | null,
    canUndo: false,
    canRedo: false,
    // Undo/Redo で内容を差し替えるたびに増やす(入力欄を作り直して、差し替えた値を出すのに使う)
    restoreCount: 0,
    // 最後に保存した日時(自動保存の表示用)
    lastSavedAt: null as Date | null,
    // ほかの管理者が先に保存した(読み込み直すまで自動保存を止める)
    conflict: false,
    // テンプレートの画面を開いているか
    templatesOpen: false,
    // 版の履歴の画面を開いているか
    versionsOpen: false,
    // 書き出し・読み込みの画面を開いているか
    transferOpen: false,
  })
}

export type EditorState = ReturnType<typeof createEditorState>
