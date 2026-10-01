import { t } from '../i18n'

// スタイルの入力欄の名前・まとまり・選択肢の表示名(スタイルのプロパティ名は biscuit の StyleRegistry と同じ)

export interface StyleGroup {
  label: string
  styles: string[]
}

export function styleGroups(): StyleGroup[] {
  return [
    { label: t('余白'), styles: ['marginTop', 'marginBottom', 'paddingTop', 'paddingBottom', 'paddingLeft', 'paddingRight'] },
    { label: t('大きさ'), styles: ['width', 'maxWidth', 'minHeight'] },
    { label: t('文字'), styles: ['fontSize', 'fontWeight', 'lineHeight', 'textAlign', 'color'] },
    { label: t('背景'), styles: ['backgroundColor'] },
    { label: t('枠線'), styles: ['borderWidth', 'borderStyle', 'borderColor', 'borderRadius'] },
  ]
}

export function styleLabel(name: string): string {
  const labels: Record<string, string> = {
    marginTop: t('上の余白(外側)'),
    marginBottom: t('下の余白(外側)'),
    paddingTop: t('上の余白(内側)'),
    paddingBottom: t('下の余白(内側)'),
    paddingLeft: t('左の余白(内側)'),
    paddingRight: t('右の余白(内側)'),
    width: t('幅'),
    maxWidth: t('最大幅'),
    minHeight: t('最小の高さ'),
    fontSize: t('文字の大きさ'),
    fontWeight: t('文字の太さ'),
    lineHeight: t('行の高さ'),
    textAlign: t('文字揃え'),
    color: t('文字の色'),
    backgroundColor: t('背景色'),
    borderWidth: t('線の太さ'),
    borderStyle: t('線の種類'),
    borderColor: t('線の色'),
    borderRadius: t('角の丸み'),
  }

  return labels[name] ?? name
}

export function styleOptionLabel(name: string, value: string): string {
  const labels: Record<string, Record<string, string>> = {
    textAlign: { left: t('左揃え'), center: t('中央揃え'), right: t('右揃え'), justify: t('両端揃え') },
    fontWeight: { 300: t('細い'), 400: t('標準'), 500: t('やや太い'), 600: t('少し太い'), 700: t('太い'), 800: t('とても太い') },
    borderStyle: { none: t('なし'), solid: t('実線'), dashed: t('破線'), dotted: t('点線'), double: t('二重線') },
  }

  return labels[name]?.[value] ?? value
}
