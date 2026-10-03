import { t } from './i18n'

// ページビルダーの Custom CSS とブロックの追加のクラス名の確かめ(biscuit の Support\Builder\CustomCss と同じ規則。customCss.test.ts で確かめる)。
// エディタは Canvas の要素(.builder-canvas)の中に、公開側(chococo)は .page-builder の中にネストして、ビルダーの外へ効かないようにする

export const CUSTOM_CSS_MAX_LENGTH = 20000
export const MAX_CLASSES = 5
const CLASS_PATTERN = /^[A-Za-z_][A-Za-z0-9_-]{0,49}$/

// 使えない書き方(コメント・文字列の外で探す) → 画面に出す名前
const FORBIDDEN: [RegExp, string][] = [
  [/@import/i, '@import'],
  [/@charset/i, '@charset'],
  [/@namespace/i, '@namespace'],
  [/expression\s*\(/i, 'expression()'],
  [/javascript\s*:/i, 'javascript:'],
  [/(?<![\w-])behavior\s*:/i, 'behavior'],
  [/-moz-binding/i, '-moz-binding'],
  [/image-set\s*\(/i, 'image-set()'],
  [/(?<![\w-])src\s*\(/i, 'src()'],
]

/**
 * コメントと文字列の中身を除いた CSS(閉じていなければ null)。
 */
function withoutCommentsAndStrings(css: string): string | null {
  let result = ''

  for (let i = 0; i < css.length; i++) {
    const char = css[i]

    if (char === '/' && css[i + 1] === '*') {
      const end = css.indexOf('*/', i + 2)
      if (end === -1) {
        return null
      }
      i = end + 1
      continue
    }

    if (char === '"' || char === '\'') {
      const end = css.indexOf(char, i + 1)
      if (end === -1) {
        return null
      }
      result += char + char
      i = end
      continue
    }

    result += char
  }

  return result
}

function hasOnlyLocalUrls(css: string): boolean {
  const matches = [...css.matchAll(/url\s*\(\s*(["']?)([^"')]*)\1\s*\)/gi)]

  return matches.every(match => /^\/(?!\/)\S*$/.test(match[2])) && (css.match(/url\s*\(/gi) ?? []).length === matches.length
}

function hasBalancedBraces(code: string): boolean {
  let depth = 0

  for (const char of code) {
    depth += char === '{' ? 1 : char === '}' ? -1 : 0
    if (depth < 0) {
      return false
    }
  }

  return depth === 0
}

/**
 * Custom CSS の誤りの一覧(空なら使える)。
 */
export function customCssErrors(css: string | null | undefined): string[] {
  if (!css) {
    return []
  }

  if (css.length > CUSTOM_CSS_MAX_LENGTH) {
    return [t('CSS は :max 文字までで入力してください。', { max: CUSTOM_CSS_MAX_LENGTH })]
  }

  if (css.includes('<') || css.includes('\\')) {
    return [t('CSS に「<」と「\\」は使えません。')]
  }

  const code = withoutCommentsAndStrings(css)

  if (code === null) {
    return [t('CSS のコメントか文字列が閉じていません。')]
  }

  const errors = FORBIDDEN.filter(([pattern]) => pattern.test(code)).map(([, name]) => t('CSS に「:value」は使えません。', { value: name }))

  if (!hasOnlyLocalUrls(css)) {
    errors.push(t('CSS の url() には、/storage/… のような / で始まるサイト内のパスだけを書けます。'))
  }

  if (!hasBalancedBraces(code)) {
    errors.push(t('CSS の { と } の対応が正しくありません。'))
  }

  return errors
}

/**
 * 空白で区切った追加のクラス名を一覧にする(重なりは 1 つに)。
 */
export function parseClasses(text: string): string[] {
  return [...new Set(text.split(/\s+/).filter(name => name !== ''))]
}

/**
 * 追加のクラス名の誤り(null なら正しい)。
 */
export function classesError(classes: string[]): string | null {
  if (classes.length > MAX_CLASSES) {
    return t('追加のクラス名は、重ならないように :max 個まで指定してください。', { max: MAX_CLASSES })
  }

  return classes.every(name => CLASS_PATTERN.test(name)) ? null : t('追加のクラス名には、英字か _ で始まる英数字・-・_ だけを使えます。')
}

/**
 * Custom CSS を要素(scope。例: .builder-canvas)の中にネストする(使えない CSS は出さない)。
 */
export function scopedCss(scope: string, css: string | null | undefined): string {
  return css && customCssErrors(css).length === 0 ? `${scope}{${css}}` : ''
}
