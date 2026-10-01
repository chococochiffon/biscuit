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

// スタイルの値の形(biscuit の StyleRegistry と同じ。エディタは BlockRegistry::toArray() の styles で種類を受け取る)
const STYLE_PATTERNS: Record<string, RegExp> = {
  length: /^(?:0|auto|\d{1,4}(?:\.\d{1,2})?(?:px|rem|em|%|vh|vw))$/,
  color: /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/,
  number: /^\d(?:\.\d{1,2})?$/,
}

/**
 * スタイルの値が、そのスタイルの種類(length・color・number、または選べる値の一覧)で許した形か。
 */
export function isValidStyleValue(kind: string | string[] | undefined, value: string): boolean {
  if (kind === undefined) {
    return false
  }

  return Array.isArray(kind) ? kind.includes(value) : STYLE_PATTERNS[kind]?.test(value) ?? false
}

/**
 * 端末のスタイルを変える(デスクトップは styles、タブレット・スマートフォンは responsive の上書き)。
 * null なら指定を外し、空になった端末の上書きは取り除く。変わったら true。
 */
export function setNodeStyle(node: BuilderNode, device: Device, name: string, value: string | null): boolean {
  if (device === 'desktop') {
    if ((node.styles[name] ?? null) === value) {
      return false
    }
    if (value === null) {
      delete node.styles[name]
    }
    else {
      node.styles[name] = value
    }

    return true
  }

  const overrides = { ...node.responsive?.[device] }

  if ((overrides[name] ?? null) === value) {
    return false
  }
  if (value === null) {
    delete overrides[name]
  }
  else {
    overrides[name] = value
  }

  const responsive = { ...node.responsive, [device]: overrides }

  if (Object.keys(overrides).length === 0) {
    delete responsive[device]
  }

  if (Object.keys(responsive).length === 0) {
    delete node.responsive
  }
  else {
    node.responsive = responsive
  }

  return true
}

/**
 * 端末で使われるスタイルの値と、それがどこで決まっているか(own: その端末で指定・inherited: 上の端末から引き継ぎ・none: 指定なし)。
 */
export function styleValueFor(node: BuilderNode, device: Device, name: string): { value: string | null, source: 'own' | 'inherited' | 'none' } {
  const own = device === 'desktop' ? node.styles[name] : node.responsive?.[device]?.[name]

  if (own !== undefined) {
    return { value: own, source: 'own' }
  }

  const inherited = effectiveStyles(node, device)[name]

  return inherited !== undefined ? { value: inherited, source: 'inherited' } : { value: null, source: 'none' }
}
