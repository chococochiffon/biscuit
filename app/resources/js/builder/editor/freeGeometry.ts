import { hasDeviceLayout, round2 } from '../layout'
import type { BuilderStore } from '../store'
import type { BuilderNode, Device, LayoutBox } from '../types'

// 自由配置の面の大きさを Canvas の要素から測る処理(ドラッグで動かす・大きさを変えるときに使う)

export interface SurfaceFrame {
  left: number
  top: number
  width: number
  // 面の要素の中に絶対位置で置くもの(ガイド線など)の、中身の枠までのずれ(内側の余白)
  offsetLeft: number
  offsetTop: number
}

/**
 * 面の中身の枠(内側の余白を除いた、位置の基準。x・w の % はこの幅に対する割合)。
 */
export function surfaceFrame(surface: HTMLElement): SurfaceFrame {
  const rect = surface.getBoundingClientRect()
  const style = getComputedStyle(surface)
  const paddingLeft = Number.parseFloat(style.paddingLeft) || 0
  const paddingRight = Number.parseFloat(style.paddingRight) || 0
  const paddingTop = Number.parseFloat(style.paddingTop) || 0
  const borderLeft = Number.parseFloat(style.borderLeftWidth) || 0
  const borderRight = Number.parseFloat(style.borderRightWidth) || 0
  const borderTop = Number.parseFloat(style.borderTopWidth) || 0

  return {
    left: rect.left + borderLeft + paddingLeft,
    top: rect.top + borderTop + paddingTop,
    width: Math.max(1, rect.width - borderLeft - borderRight - paddingLeft - paddingRight),
    offsetLeft: paddingLeft,
    offsetTop: paddingTop,
  }
}

/**
 * 面の直下のブロックの要素(CanvasNode)。
 */
export function surfaceItems(surface: HTMLElement): HTMLElement[] {
  return Array.from(surface.children).filter((element): element is HTMLElement => element instanceof HTMLElement && element.dataset.nodeId !== undefined)
}

/**
 * 要素の今の見た目を、面の中の位置にする(高さは keepHeight のときだけ)。
 */
export function measuredBox(element: HTMLElement, frame: SurfaceFrame, keepHeight: boolean): LayoutBox {
  const rect = element.getBoundingClientRect()
  const box: LayoutBox = {
    x: round2(((rect.left - frame.left) / frame.width) * 100),
    y: Math.round(rect.top - frame.top),
    w: round2((rect.width / frame.width) * 100),
  }

  if (keepHeight) {
    box.h = Math.round(rect.height)
  }

  return box
}

/**
 * タブレット・スマートフォンで面のブロックを初めて動かすとき、面の子すべての位置をその端末だけの位置として作る
 * (タブレットはデスクトップの位置の写し、スマートフォンは縦 1 列に並んだ今の見た目を測った位置)。ドラッグの liveEdit の中で呼ぶ。
 */
export function ensureDeviceLayout(store: BuilderStore, parentId: string | null, siblings: BuilderNode[], device: Device, surface: HTMLElement): void {
  if (device === 'desktop' || hasDeviceLayout(siblings, device)) {
    return
  }

  const boxes: Record<string, LayoutBox> = {}

  if (device === 'tablet') {
    for (const sibling of siblings) {
      if (sibling.layout) {
        boxes[sibling.id] = { ...sibling.layout.desktop }
      }
    }
  }
  else {
    const frame = surfaceFrame(surface)

    for (const element of surfaceItems(surface)) {
      const sibling = siblings.find(node => node.id === element.dataset.nodeId)

      if (sibling?.layout) {
        boxes[sibling.id] = measuredBox(element, frame, sibling.layout.desktop.h !== undefined)
      }
    }
  }

  store.materializeDevice(parentId, device, boxes)
}
