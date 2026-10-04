import { t } from '../i18n'
import { canPlace, cloneWithNewIds, containsNode, createNode, findLocation, findNode, findPastePosition, insertNode, moveNode, parseClipboard, removeNode, serializeClipboard } from '../nodes'
import type { EditorContext } from './context'

/**
 * 木の編集: 選択・追加・移動・削除・複製・コピー/貼り付け・ドラッグ&ドロップ・元に戻す/やり直す。
 */
export function editingActions(context: EditorContext) {
  const { state, history, mutate, equalizeIfAddedToRow, definition, selectedNode } = context

  function select(id: string | null): void {
    state.selectedId = id
  }

  /**
   * 選択中のブロックの親を選ぶ(ページの直下なら選択を外す)。
   */
  function selectParent(): void {
    if (state.selectedId) {
      state.selectedId = findLocation(state.content, state.selectedId)?.parent?.id ?? null
    }
  }

  /**
   * 新しいブロックを親(null はページの直下)の index の位置に置き、選択する。props は既定値に重ねる(パレットの独自コンポーネントの部品の id など)。
   */
  function add(type: string, parentId: string | null, index: number, props: Record<string, unknown> = {}): boolean {
    const node = createNode(state.registry, type)
    Object.assign(node.props, JSON.parse(JSON.stringify(props)))

    const added = mutate(null, () => {
      if (!insertNode(state.registry, state.content, parentId, index, node)) {
        return false
      }
      equalizeIfAddedToRow(node.id, parentId)

      return true
    })

    if (added) {
      state.selectedId = node.id
    }

    return added
  }

  /**
   * パレットのクリックで新しいブロックを置く。選択中のブロックの中(末尾)に置けなければその後ろ、どちらもだめならページの末尾。
   */
  function addNearSelection(type: string, props: Record<string, unknown> = {}): boolean {
    const selected = selectedNode()

    if (selected?.children && canPlace(state.registry, selected.type, type)) {
      return add(type, selected.id, selected.children.length, props)
    }

    const location = selected ? findLocation(state.content, selected.id) : null

    if (location && canPlace(state.registry, location.parent?.type ?? null, type)) {
      return add(type, location.parent?.id ?? null, location.index + 1, props)
    }

    if (canPlace(state.registry, null, type)) {
      return add(type, null, state.content.children.length, props)
    }

    state.message = { type: 'warning', text: t('「:block」は、選んでいるブロックの中や後ろには置けません。', { block: definition(type).label }) }

    return false
  }

  function move(id: string, parentId: string | null, index: number): boolean {
    const fromParentId = findLocation(state.content, id)?.parent?.id ?? null

    return mutate(null, () => {
      if (!moveNode(state.registry, state.content, id, parentId, index)) {
        return false
      }
      // 別の行へ移したカラムは、移した先の行で幅をそろえる(同じ行の中の並び替えでは変えない)
      if (fromParentId !== parentId) {
        equalizeIfAddedToRow(id, parentId)
      }

      return true
    })
  }

  /**
   * 選択中のブロックを兄弟の中で 1 つ前・後ろへ動かす。
   */
  function moveSelectedBy(offset: -1 | 1): void {
    const location = state.selectedId ? findLocation(state.content, state.selectedId) : null

    if (!location) {
      return
    }

    const index = location.index + offset

    if (index < 0 || index >= location.siblings.length) {
      return
    }

    move(location.siblings[location.index].id, location.parent?.id ?? null, offset > 0 ? index + 1 : index)
  }

  function remove(id: string): void {
    if (mutate(null, () => removeNode(state.content, id) !== null) && state.selectedId && !findNode(state.content, state.selectedId)) {
      state.selectedId = null
    }
  }

  /**
   * ブロックを(子ごと)複製してすぐ後ろに置き、複製を選択する。複製と子孫には新しい ID を振る。
   * 行の中のカラムを複製したら、行のカラムの幅をそろえる。
   */
  function duplicate(id: string): void {
    const location = findLocation(state.content, id)

    if (!location) {
      return
    }

    const copy = cloneWithNewIds(location.siblings[location.index])

    mutate(null, () => {
      location.siblings.splice(location.index + 1, 0, copy)
      equalizeIfAddedToRow(copy.id, location.parent?.id ?? null)

      return true
    })
    state.selectedId = copy.id
  }

  /**
   * 選択中のブロックを(子ごと)クリップボードに入れる文字列にする。選択していなければ null。
   * cut なら、あわせてブロックを削除する(元に戻せる)。
   */
  function copySelected(cut = false): string | null {
    const node = selectedNode()

    if (!node) {
      return null
    }

    const text = serializeClipboard(node, state.content.version)
    const label = definition(node.type)?.label ?? node.type

    if (cut) {
      remove(node.id)
    }
    state.message = { type: 'success', text: cut ? t('「:block」を切り取りました。', { block: label }) : t('「:block」をコピーしました。', { block: label }) }

    return text
  }

  /**
   * クリップボードのブロックを、選択中のブロックの近く(findPastePosition)に貼り付けて選択する。
   * ページビルダーのブロックでない・置ける場所がないときは、理由を出して false を返す。
   */
  function paste(text: string): boolean {
    const node = parseClipboard(state.registry, text, state.content.version)

    if (!node) {
      state.message = { type: 'warning', text: t('貼り付けられるブロックがありません。ページビルダーのブロックをコピーしてください。') }

      return false
    }

    const position = findPastePosition(state.registry, state.content, state.selectedId, node.type)

    if (!position) {
      state.message = { type: 'warning', text: t('「:block」は、選んでいるブロックの中や後ろには置けません。', { block: definition(node.type).label }) }

      return false
    }

    mutate(null, () => {
      if (!insertNode(state.registry, state.content, position.parentId, position.index, node)) {
        return false
      }
      equalizeIfAddedToRow(node.id, position.parentId)

      return true
    })
    state.selectedId = node.id

    return true
  }

  function undo(): void {
    const previous = history.undo(JSON.stringify(state.content))

    if (previous !== null) {
      context.restore(previous)
    }
  }

  function redo(): void {
    const next = history.redo(JSON.stringify(state.content))

    if (next !== null) {
      context.restore(next)
    }
  }

  /**
   * ドラッグ中のものを、親(null はページの直下)の中に置けるか。置いてあるブロックは自分の中へは置けない。
   */
  function canDropInto(parentId: string | null): boolean {
    const dragging = state.dragging

    if (!dragging) {
      return false
    }

    const parentType = parentId === null ? null : findNode(state.content, parentId)?.type ?? null

    if (parentId !== null && parentType === null) {
      return false
    }

    if (dragging.kind === 'move' && parentId !== null) {
      const node = findNode(state.content, dragging.id)
      if (!node || containsNode(node, parentId)) {
        return false
      }
    }

    return canPlace(state.registry, parentType, dragging.type)
  }

  function endDrag(): void {
    state.dragging = null
    state.dropTarget = null
  }

  function drop(): void {
    const { dragging, dropTarget } = state

    if (dragging && dropTarget) {
      if (dragging.kind === 'new') {
        add(dragging.type, dropTarget.parentId, dropTarget.index, dragging.props)
      }
      else if (move(dragging.id, dropTarget.parentId, dropTarget.index)) {
        state.selectedId = dragging.id
      }
    }

    endDrag()
  }

  return { select, selectParent, add, addNearSelection, move, moveSelectedBy, remove, duplicate, copySelected, paste, undo, redo, canDropInto, drop, endDrag }
}
