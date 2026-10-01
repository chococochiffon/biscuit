// 動画のブロックの URL(YouTube・Vimeo)から、埋め込み用の URL を組み立てる(biscuit の Support\Builder\VideoUrl と同じ形)。
// 入力された URL をそのまま iframe に入れず、動画の ID だけを取り出して決まった形の URL にする

const YOUTUBE = /^https:\/\/(?:(?:www\.|m\.)?youtube\.com\/(?:watch\?(?:[^\s#]*&)?v=|shorts\/|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{11})(?:[?&#]\S*)?$/
const VIMEO = /^https:\/\/(?:www\.)?(?:vimeo\.com|player\.vimeo\.com\/video)\/(\d{1,12})(?:[/?#]\S*)?$/

/**
 * 埋め込み用の URL。対応していない URL なら null。
 */
export function videoEmbedUrl(url: unknown): string | null {
  if (typeof url !== 'string' || url.length > 2048) {
    return null
  }

  const youtube = url.match(YOUTUBE)
  if (youtube) {
    return `https://www.youtube-nocookie.com/embed/${youtube[1]}`
  }

  const vimeo = url.match(VIMEO)

  return vimeo ? `https://player.vimeo.com/video/${vimeo[1]}` : null
}
