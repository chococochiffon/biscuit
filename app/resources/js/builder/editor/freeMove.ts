import { deviceBox, snapOffset } from '../layout'
import { canPlace, findNode } from '../nodes'
import type { BuilderStore } from '../store'
import type { BuilderNode, LayoutBox } from '../types'
import { ensureDeviceLayout, surfaceFrame, surfaceItems } from './freeGeometry'

// 自由配置の面のブロックを、マウスのドラッグで動かす。少し動かしてから始め(クリック・ダブルクリックと分ける)、
// 面の端・中央とほかのブロックの端・中央に吸い付いてガイド線を出す。離した場所が別の面(ボックス・別のセクション)なら、その面へ移す。
// 動かしている間は履歴を積まず、離したら 1 回の操作として積む

// 動かし始めるまでの距離(px)と、吸い付く距離(px)
const START_DISTANCE = 4
const SNAP_DISTANCE = 6

export interface FreePlacement {
  parentId: string | null
  siblings: BuilderNode[]
}

/**
 * 面の ID(一番外側は root)。
 */
export function surfaceIdOf(parentId: string | null): string {
  return parentId ?? 'root'
}

/**
 * ポインターの下の、いちばん内側の面(element の中の面は除く)。
 */
function surfaceAt(x: number, y: number, element: HTMLElement): HTMLElement | null {
  return document.elementsFromPoint(x, y).find(
    (candidate): candidate is HTMLElement => candidate instanceof HTMLElement && candidate.classList.contains('builder-free') && candidate.dataset.surfaceId !== undefined && !element.contains(candidate),
  ) ?? null
}

export function startFreeMove(store: BuilderStore, node: BuilderNode, element: HTMLElement, placement: FreePlacement, event: PointerEvent): void {
  const surface = element.parentElement
  const device = store.state.device

  if (!surface) {
    return
  }

  const startX = event.clientX
  const startY = event.clientY
  let snapshot: string | null = null
  let startBox: LayoutBox | null = null
  let startLeft = 0
  let startTop = 0
  let frame = surfaceFrame(surface)
  let others: DOMRect[] = []
  let target: HTMLElement | null = null

  function begin(): void {
    snapshot = store.beginLiveEdit()
    store.select(node.id)
    // 動かしている間は、文字を選ばない
    document.body.classList.add('builder-free-moving')
    ensureDeviceLayout(store, placement.parentId, placement.siblings, device, surface!)

    const current = findNode(store.state.content, node.id)
    startBox = current ? deviceBox(current, device) : null
    frame = surfaceFrame(surface!)
    const rect = element.getBoundingClientRect()
    startLeft = rect.left - frame.left
    startTop = rect.top - frame.top
    others = surfaceItems(surface!).filter(item => item !== element).map(item => item.getBoundingClientRect())
  }

  function onMove(moveEvent: PointerEvent): void {
    const dx = moveEvent.clientX - startX
    const dy = moveEvent.clientY - startY

    if (snapshot === null) {
      if (Math.hypot(dx, dy) < START_DISTANCE) {
        return
      }
      begin()
    }

    if (!startBox) {
      return
    }

    const rect = element.getBoundingClientRect()
    let left = startLeft + dx
    let top = startTop + dy
    const guides: typeof store.state.guides = []
    const surfaceId = surface!.dataset.surfaceId ?? 'root'

    // 横: 面の左端・中央・右端と、ほかのブロックの左端・中央・右端
    const xCandidates = [0, frame.width / 2, frame.width, ...others.flatMap(other => [other.left, other.left + other.width / 2, other.right].map(value => value - frame.left))]
    const snapX = snapOffset([left, left + rect.width / 2, left + rect.width], xCandidates, SNAP_DISTANCE)
    if (snapX) {
      left += snapX.offset
      guides.push({ surfaceId, orientation: 'vertical', position: snapX.guide + frame.offsetLeft })
    }

    // 縦: ほかのブロックの上端・中央・下端
    const yCandidates = others.flatMap(other => [other.top, other.top + other.height / 2, other.bottom].map(value => value - frame.top))
    const snapY = snapOffset([top, top + rect.height / 2, top + rect.height], yCandidates, SNAP_DISTANCE)
    if (snapY) {
      top += snapY.offset
      guides.push({ surfaceId, orientation: 'horizontal', position: snapY.guide + frame.offsetTop })
    }

    store.state.guides = guides
    const box = { ...startBox, x: (left / frame.width) * 100, y: top }
    store.liveEdit(node.id, (current) => {
      store.setLiveBox(current, device, box, false)

      return true
    })

    // 離すと入る面(今の面と違い、そのブロックを置ける面だけ)
    target = surfaceAt(moveEvent.clientX, moveEvent.clientY, element)
    const targetParentId = target?.dataset.surfaceId === 'root' ? null : target?.dataset.surfaceId ?? null
    const targetType = targetParentId === null ? null : findNode(store.state.content, targetParentId)?.type ?? null

    if (!target || target === surface || !canPlace(store.state.registry, targetType, node.type) || !store.isSurface(targetParentId)) {
      target = null
    }
    store.state.freeDropSurfaceId = target?.dataset.surfaceId ?? null
  }

  function onUp(): void {
    window.removeEventListener('pointermove', onMove)
    window.removeEventListener('pointerup', onUp)
    window.removeEventListener('pointercancel', onUp)

    if (snapshot === null) {
      return
    }

    if (target) {
      // 別の面へ移す: 今の見た目の位置を、移る先の面の中の位置にする
      const targetFrame = surfaceFrame(target)
      const rect = element.getBoundingClientRect()
      const parentId = target.dataset.surfaceId === 'root' ? null : target.dataset.surfaceId!
      const box: LayoutBox = {
        ...startBox,
        x: ((rect.left - targetFrame.left) / targetFrame.width) * 100,
        y: rect.top - targetFrame.top,
        w: Math.min(100, (rect.width / targetFrame.width) * 100),
      }
      store.reparentLive(node.id, parentId, device, box)
    }

    store.endLiveEdit(snapshot, node.id)
    document.body.classList.remove('builder-free-moving')
    store.state.guides = []
    store.state.freeDropSurfaceId = null
  }

  window.addEventListener('pointermove', onMove)
  window.addEventListener('pointerup', onUp)
  window.addEventListener('pointercancel', onUp)
}
