import { describe, expect, it } from 'vitest'
import { videoEmbedUrl } from './video'

describe('videoEmbedUrl', () => {
  it('YouTube の URL から、Cookie を使わない埋め込み用の URL を作る', () => {
    const embed = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'

    expect(videoEmbedUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ')).toBe(embed)
    expect(videoEmbedUrl('https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=10')).toBe(embed)
    expect(videoEmbedUrl('https://youtu.be/dQw4w9WgXcQ?si=abc')).toBe(embed)
    expect(videoEmbedUrl('https://m.youtube.com/shorts/dQw4w9WgXcQ')).toBe(embed)
    expect(videoEmbedUrl('https://www.youtube.com/embed/dQw4w9WgXcQ')).toBe(embed)
  })

  it('Vimeo の URL から埋め込み用の URL を作る', () => {
    expect(videoEmbedUrl('https://vimeo.com/76979871')).toBe('https://player.vimeo.com/video/76979871')
    expect(videoEmbedUrl('https://player.vimeo.com/video/76979871?h=abc')).toBe('https://player.vimeo.com/video/76979871')
  })

  it('ほかのサイト・http・スクリプトは受け付けない', () => {
    expect(videoEmbedUrl('http://www.youtube.com/watch?v=dQw4w9WgXcQ')).toBeNull()
    expect(videoEmbedUrl('https://www.youtube.com.evil.example/watch?v=dQw4w9WgXcQ')).toBeNull()
    expect(videoEmbedUrl('https://evil.example/youtu.be/dQw4w9WgXcQ')).toBeNull()
    expect(videoEmbedUrl('https://www.youtube.com/watch?v=short')).toBeNull()
    expect(videoEmbedUrl('javascript:alert(1)')).toBeNull()
    expect(videoEmbedUrl('https://vimeo.com/abc')).toBeNull()
    expect(videoEmbedUrl(null)).toBeNull()
  })
})
