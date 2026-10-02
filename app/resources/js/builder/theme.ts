import { t } from './i18n'
import type { BuilderTheme } from './types'

// ページビルダーのテーマ(biscuit の Support\Builder\ThemeRegistry)。色のスタイルの値「theme:名前」は、
// CSS の変数(--builder-theme-名前)にして効かせる(Canvas はこの変数を Canvas の要素に、chococo はビルダーの要素に置く)。
// テーマの色の変数はボタンのブロックのメイン・サブの色にも使う(builder.css)

/**
 * テーマの色の名前と表示名(並びは管理画面のテーマと同じ。文言は呼んだときの言語で作る)。
 */
export function themeColors(): { name: string, label: string }[] {
  return [
    { name: 'primary', label: t('メイン') },
    { name: 'secondary', label: t('サブ') },
    { name: 'accent', label: t('アクセント') },
    { name: 'text', label: t('文字') },
    { name: 'light', label: t('淡い色') },
  ]
}

// 色のスタイルに入れるテーマの色の値
export const THEME_COLOR_PATTERN = /^theme:(?:primary|secondary|accent|text|light)$/

/**
 * スタイルの値を CSS の値にする(テーマの色は CSS の変数に。ほかはそのまま)。
 */
export function cssValue(value: string): string {
  return THEME_COLOR_PATTERN.test(value) ? `var(--builder-theme-${value.slice('theme:'.length)})` : value
}

/**
 * テーマの色の値(theme:primary)の表示名。テーマの色でなければ null。
 */
export function themeColorLabel(value: string | null | undefined): string | null {
  return themeColors().find(color => `theme:${color.name}` === value)?.label ?? null
}

/**
 * テーマを CSS の変数にする(Canvas・ビルダーの要素の style に置く)。フォントは選んだものだけ。
 */
export function themeVariables(theme: BuilderTheme): Record<string, string> {
  const variables: Record<string, string> = {}

  for (const [name, value] of Object.entries(theme.colors)) {
    variables[`--builder-theme-${name}`] = value
  }

  if (theme.fonts.heading) {
    variables['--builder-theme-heading-font'] = theme.fonts.heading.family
  }

  if (theme.fonts.body) {
    variables['--builder-theme-body-font'] = theme.fonts.body.family
  }

  return variables
}

/**
 * テーマのフォント(Google Fonts)の CSS を読み込む(同じものは 1 回だけ)。
 */
export function loadThemeFonts(theme: BuilderTheme): void {
  for (const font of [theme.fonts.heading, theme.fonts.body]) {
    if (!font || !font.href.startsWith('https://fonts.googleapis.com/') || document.querySelector(`link[data-builder-font="${font.key}"]`)) {
      continue
    }

    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = font.href
    link.dataset.builderFont = font.key
    document.head.append(link)
  }
}
