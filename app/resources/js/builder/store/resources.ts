import { t } from '../i18n'
import type { ArticleSummary, GalleryImageSummary, NavigationItem } from '../types'
import type { EditorContext } from './context'

/**
 * 取得の条件(key)ごとに結果を覚える。失敗したら空を返して覚えず、次は取得し直す。
 */
function createCache<T>() {
  const entries = new Map<string, Promise<T[]>>()

  return (key: string, fetch: () => Promise<T[]>): Promise<T[]> => {
    if (!entries.has(key)) {
      entries.set(key, fetch().catch(() => {
        entries.delete(key)

        return []
      }))
    }

    return entries.get(key)!
  }
}

/**
 * Canvas の見本に使うデータ(記事一覧・ナビゲーション・ギャラリー・グローバルコンポーネント)と画像。
 */
export function resourceActions(context: EditorContext) {
  const { api, state, handleError } = context
  const articleLists = createCache<ArticleSummary>()
  const navigations = createCache<NavigationItem>()
  const galleries = createCache<GalleryImageSummary>()

  /**
   * 記事一覧のブロックの見本(条件どおりの公開中の記事)。取得できなければ空。
   */
  function articleListPreview(props: Record<string, unknown>): Promise<ArticleSummary[]> {
    const query = {
      limit: String(props.limit ?? 6),
      order: String(props.order ?? 'newest'),
      parentPath: typeof props.parentPath === 'string' ? props.parentPath : '',
      tag: typeof props.tag === 'string' ? props.tag : '',
    }

    return articleLists(JSON.stringify(query), () => api.articleList(query).then(response => response.articles))
  }

  /**
   * ナビゲーションのブロックの見本(項目の出どころどおりの項目)。取得できなければ空。
   */
  function navigationPreview(source: unknown): Promise<NavigationItem[]> {
    const key = source === 'pages' ? 'pages' : 'site'

    return navigations(key, () => api.navigation(key).then(response => response.items))
  }

  /**
   * ギャラリーのブロックの見本(条件どおりの公開中の画像)。取得できなければ空。
   */
  function galleryPreview(props: Record<string, unknown>): Promise<GalleryImageSummary[]> {
    const query = {
      category: typeof props.category === 'number' ? String(props.category) : '',
      limit: String(props.limit ?? 8),
    }

    return galleries(JSON.stringify(query), () => api.gallery(query).then(response => response.images))
  }

  /**
   * グローバルコンポーネントの一覧を読み込む(一度だけ。失敗したら空)。
   */
  async function loadComponents(): Promise<void> {
    if (state.components !== null) {
      return
    }

    state.components = []
    state.components = await api.components().catch(() => [])
  }

  async function uploadImage(file: File): Promise<string | null> {
    try {
      return (await api.uploadImage(file)).path
    }
    catch (error) {
      handleError(error, t('画像のアップロードに失敗しました。'))

      return null
    }
  }

  function imageUrl(path: unknown): string | null {
    return typeof path === 'string' && path !== '' ? `${state.imageBaseUrl.replace(/\/$/, '')}/${path}` : null
  }

  return { articleListPreview, navigationPreview, galleryPreview, loadComponents, uploadImage, imageUrl }
}
