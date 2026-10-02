import type { ArticleSummary, BuilderContent, BuilderImportResult, BuilderStatePayload, BuilderTemplate, BuilderVersion, BuilderVersionSummary, ComponentSummary, EditorConfig, GalleryImageSummary, NavigationItem, ShowPayload } from './types'

// PageBuilderJsonController を呼ぶ。セッションで認証するため、CSRF トークンを付けて同じオリジンへ送る

export class ApiError extends Error {
  constructor(
    public status: number,
    public data: { message?: string, errors?: Record<string, string[]>, updated_at?: string | null },
  ) {
    super(data.message ?? `HTTP ${status}`)
  }
}

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

async function request<T>(url: string, method: string, body?: unknown): Promise<T> {
  const isForm = body instanceof FormData
  const response = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
      ...(body !== undefined && !isForm ? { 'Content-Type': 'application/json' } : {}),
    },
    body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
  })
  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new ApiError(response.status, data)
  }

  return data as T
}

export function createApi(config: EditorConfig) {
  const { endpoints } = config

  return {
    show: () => request<ShowPayload>(endpoints.show, 'GET'),
    save: (content: BuilderContent, updatedAt: string | null) =>
      request<BuilderStatePayload>(endpoints.update, 'PUT', { content, updated_at: updatedAt }),
    publish: (updatedAt: string | null) => request<BuilderStatePayload>(endpoints.publish, 'POST', { updated_at: updatedAt }),
    discard: (updatedAt: string | null) => request<BuilderStatePayload>(endpoints.discard, 'POST', { updated_at: updatedAt }),
    previewUrl: () => request<{ url: string, expires_at: string }>(endpoints.previewUrl ?? '', 'GET'),
    components: () => request<ComponentSummary[]>(endpoints.components, 'GET'),
    templates: () => request<BuilderTemplate[]>(endpoints.templates, 'GET'),
    saveTemplate: (name: string, description: string, content: BuilderContent) =>
      request<BuilderTemplate>(endpoints.templates, 'POST', { name, description: description || null, content }),
    deleteTemplate: (id: number) => request<unknown>(`${endpoints.templates}/${id}`, 'DELETE'),
    versions: () => request<BuilderVersionSummary[]>(endpoints.versions, 'GET'),
    version: (id: number) => request<BuilderVersion>(`${endpoints.versions}/${id}`, 'GET'),
    exportContent: (content: BuilderContent, title: string) =>
      request<Record<string, unknown>>(endpoints.export, 'POST', { content, title }),
    importFile: (file: File, allowsGlobal: boolean) => {
      const form = new FormData()
      form.append('file', file)
      form.append('allows_global', allowsGlobal ? '1' : '0')

      return request<BuilderImportResult>(endpoints.import, 'POST', form)
    },
    articleList: (query: Record<string, string>) =>
      request<{ articles: ArticleSummary[] }>(`${endpoints.articleList}?${new URLSearchParams(query)}`, 'GET'),
    navigation: (source: string) =>
      request<{ items: NavigationItem[] }>(`${endpoints.navigation}?${new URLSearchParams({ source })}`, 'GET'),
    gallery: (query: Record<string, string>) =>
      request<{ images: GalleryImageSummary[] }>(`${endpoints.gallery}?${new URLSearchParams(query)}`, 'GET'),
    uploadImage: (file: File) => {
      const form = new FormData()
      form.append('image', file)

      return request<{ path: string, url: string }>(endpoints.images, 'POST', form)
    },
  }
}

export type BuilderApi = ReturnType<typeof createApi>
