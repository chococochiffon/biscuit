import type { BuilderNode, BuilderStyles, Device } from './types'

// Canvas でブロックに効かせるスタイル。公開側(chococo の utils/builder.ts)はメディアクエリで端末ごとの上書きを効かせるが、
// Canvas は画面の幅ではなく選んだ端末で見せるため、端末の上書きを重ねた値をインラインの style にする

// ブロックの中の要素に効かせるスタイル(chococo と同じ。画像は img、ボタンは .btn)
const INNER_STYLES: Record<string, string[]> = {
  image: ['width', 'maxWidth', 'borderRadius'],
  button: ['color', 'backgroundColor', 'borderRadius', 'fontSize'],
}

// CSS のプロパティ名が camelCase をそのまま変えたものと違うスタイル(chococo と同じ)
const PROPERTY_NAMES: Record<string, Record<string, string[]>> = {
  divider: {
    borderColor: ['border-top-color'],
    borderWidth: ['border-top-width'],
    borderStyle: ['border-top-style'],
  },
  button: {
    backgroundColor: ['background-color', 'border-color'],
  },
}

/**
 * 端末の上書きを重ねたスタイル(タブレットはタブレット、スマートフォンはタブレットとスマートフォンの上書きを重ねる)。
 */
export function effectiveStyles(node: BuilderNode, device: Device): BuilderStyles {
  return {
    ...node.styles,
    ...(device !== 'desktop' ? node.responsive?.tablet : {}),
    ...(device === 'mobile' ? node.responsive?.mobile : {}),
  }
}

function kebab(name: string): string {
  return name.replace(/[A-Z]/g, letter => `-${letter.toLowerCase()}`)
}

/**
 * ブロックの要素(root)か、中の要素(inner。画像の img・ボタンの .btn)に効かせるインラインの style。
 */
export function blockStyle(node: BuilderNode, device: Device, part: 'root' | 'inner' = 'root'): Record<string, string> {
  const style: Record<string, string> = {}
  const inner = INNER_STYLES[node.type] ?? []

  for (const [name, value] of Object.entries(effectiveStyles(node, device))) {
    if (inner.includes(name) !== (part === 'inner')) {
      continue
    }
    for (const property of PROPERTY_NAMES[node.type]?.[name] ?? [kebab(name)]) {
      style[property] = value
    }
  }

  if (node.type === 'divider' && part === 'root' && 'border-top-color' in style) {
    style.opacity = '1'
  }

  return style
}

/**
 * カラムの幅(12 分割)。タブレットは spanTablet(なければデスクトップ)、スマートフォンは spanMobile(なければ 12)。
 */
export function columnSpan(node: BuilderNode, device: Device): number {
  const int = (value: unknown, fallback: number) => (typeof value === 'number' && value >= 1 && value <= 12 ? value : fallback)
  const span = int(node.props.span, 12)

  if (device === 'desktop') {
    return span
  }

  return device === 'tablet' ? int(node.props.spanTablet, span) : int(node.props.spanMobile, 12)
}
