import { describe, expect, it } from 'vitest'
import { classesError, customCssErrors, parseClasses, scopedCss } from './customCss'

describe('Custom CSS', () => {
  it('安全な CSS は受け付ける', () => {
    expect(customCssErrors('/* x */ .a { background: url("/storage/a.png") } .behavior-note { color: red } @media (max-width: 600px) { .a { padding: 0 } }')).toEqual([])
    expect(customCssErrors('')).toEqual([])
  })

  it('抜け出す・外部を読む・スクリプトを動かせる CSS は断る', () => {
    for (const css of [
      '</style><script>alert(1)</script>',
      '.a { background: u\\72l(https://evil.example/) }',
      '@import "https://evil.example/a.css";',
      '.a { background: url(https://evil.example/a.png) }',
      '.a { background: url(//evil.example/a.png) }',
      '.a { background: image-set("https://evil.example/a.png" 1x) }',
      '.a { width: expression(alert(1)) }',
      '} body { display: none } .a {',
      '.a { color: red;',
      '.a { color: red } /*',
    ]) {
      expect(customCssErrors(css), css).not.toEqual([])
    }
  })

  it('要素の中にネストし、使えない CSS は出さない', () => {
    expect(scopedCss('.builder-canvas', '.a { color: red }')).toBe('.builder-canvas{.a { color: red }}')
    expect(scopedCss('.builder-canvas', '} body {')).toBe('')
    expect(scopedCss('.builder-canvas', null)).toBe('')
  })

  it('追加のクラス名を空白で区切って確かめる', () => {
    expect(parseClasses('  card  card-title card ')).toEqual(['card', 'card-title'])
    expect(classesError(['card', '_x'])).toBeNull()
    expect(classesError(['1st'])).not.toBeNull()
    expect(classesError(['a', 'b', 'c', 'd', 'e', 'f'])).not.toBeNull()
  })
})
