import { clampBox, hasDeviceLayout, isFreeSurface, round2 } from '../layout'
import { findLocation, findNode, moveNode } from '../nodes'
import type { BuilderLayout, BuilderNode, Device, LayoutBox, ResponsiveDevice } from '../types'
import type { EditorContext } from './context'

/**
 * 自由配置(v2)の操作: ブロックの位置と大きさ(layout)を変える・面を移る・端末だけの位置を作る/自動に戻す。
 * ドラッグで動かしている間は liveEdit の中で呼び(履歴を積まない)、離したら endLiveEdit で 1 回の操作として積む。
 */
export function freeLayoutActions(context: EditorContext) {
  const { state, mutate, mutateNode, hasFreeRoot } = context

  function allowsHeight(type: string): boolean {
    return state.registry.blocks[type]?.layoutHeight === true
  }

  /**
   * 親(null は一番外側)の直下が自由配置の面か。
   */
  function isSurface(parentId: string | null): boolean {
    const parentType = parentId === null ? null : findNode(state.content, parentId)?.type ?? null

    return (parentId === null || parentType !== null) && isFreeSurface(parentType, state.content.version, hasFreeRoot())
  }

  /**
   * ブロックが自由配置の面の直下にあるか(位置と大きさで置くブロックか)。
   */
  function isFreeChild(id: string): boolean {
    const location = findLocation(state.content, id)

    return location !== null && isSurface(location.parent?.id ?? null)
  }

  /**
   * 面の子(一番外側は内容の children)。
   */
  function surfaceChildren(parentId: string | null): BuilderNode[] {
    return parentId === null ? state.content.children : findNode(state.content, parentId)?.children ?? []
  }

  /**
   * 動かしている間に、その端末の位置を変える。ふだんははみ出さないように収めるが、ドラッグで動かしている間は収めない
   * (clamp: false。面の外へ動かして別の面へ移せるように。離したら操作を積むときに収める。layout.ts の syncLayouts())。
   */
  function setLiveBox(node: BuilderNode, device: Device, box: LayoutBox, clamp = true): void {
    const layout = (node.layout ?? { desktop: box }) as BuilderLayout
    layout[device] = clamp ? clampBox(box, allowsHeight(node.type)) : { ...box, x: round2(box.x), y: Math.round(box.y) }
    node.layout = layout
  }

  /**
   * その端末だけの位置をまだ持たない面で初めて動かすとき、面の子すべての今の見た目(Canvas で測った位置)をその端末の位置にする。
   */
  function materializeDevice(parentId: string | null, device: ResponsiveDevice, boxes: Record<string, LayoutBox>): void {
    for (const child of surfaceChildren(parentId)) {
      const box = boxes[child.id]

      if (box && child.layout) {
        child.layout[device] = clampBox(box, allowsHeight(child.type))
      }
    }
  }

  /**
   * 動かし終えたブロックを、別の面の一番前(子の末尾)へ移し、その面での位置にする。移せなければ false。
   * 前の面での端末だけの位置は使えないため外す。タブレット・スマートフォンで動かしたときは、移った先の面がその端末だけの位置を
   * 持っていればその位置に置き、持っていなければ(縦 1 列・デスクトップを引き継ぐ面)端末の位置は付けない
   * (付けると、面のほかのブロックが端末の既定の位置に並び直してしまうため)。デスクトップの位置は離した位置から始める。
   */
  function reparentLive(id: string, parentId: string | null, device: Device, box: LayoutBox): boolean {
    const node = findNode(state.content, id)

    if (!node || !isSurface(parentId)) {
      return false
    }

    const withDeviceLayout = device !== 'desktop' && hasDeviceLayout(surfaceChildren(parentId), device)

    if (!moveNode(state.registry, state.content, id, parentId, surfaceChildren(parentId).length)) {
      return false
    }

    const clamped = clampBox(box, allowsHeight(node.type))
    node.layout = withDeviceLayout ? { desktop: { ...clamped }, [device]: clamped } : { desktop: clamped }

    return true
  }

  /**
   * 位置と大きさを数値で変える(プロパティの欄・矢印キー)。同じ端末の位置を続けて変える操作は 1 回にまとめる。
   */
  function updateLayout(id: string, device: Device, patch: Partial<LayoutBox>): boolean {
    return mutateNode(id, `layout.${device}`, (node) => {
      const current = device === 'desktop' ? node.layout?.desktop : node.layout?.[device]

      if (!current) {
        return false
      }

      const next = clampBox({ ...current, ...patch }, allowsHeight(node.type))

      if (patch.h === undefined && 'h' in patch) {
        delete next.h
      }

      if (JSON.stringify(next) === JSON.stringify(current)) {
        return false
      }

      setLiveBox(node, device, next)

      return true
    })
  }

  /**
   * 面の子の、その端末だけの位置をすべて外す(タブレットはデスクトップの位置、スマートフォンは縦 1 列に戻る)。
   */
  function resetDeviceLayout(parentId: string | null, device: ResponsiveDevice): boolean {
    return mutate(null, () => {
      let changed = false

      for (const child of surfaceChildren(parentId)) {
        if (child.layout?.[device]) {
          delete child.layout[device]
          changed = true
        }
      }

      return changed
    })
  }

  return { isSurface, isFreeChild, surfaceChildren, setLiveBox, materializeDevice, reparentLive, updateLayout, resetDeviceLayout }
}
