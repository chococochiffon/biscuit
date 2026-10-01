import type { BuilderContent, BuilderNode, Registry } from './types'

// ノードの木の操作(画面から切り離した関数。nodes.test.ts で確かめる)。
// 木を書き換える関数は、渡した内容をその場で書き換える(エディタの状態は store.ts だけが呼ぶ)

// ULID に使う文字(Crockford の Base32)
const ULID_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'

/**
 * ULID(時刻 10 文字 + 乱数 16 文字)を作る。
 */
export function ulid(now: number = Date.now()): string {
  let time = ''
  let remaining = now

  for (let i = 0; i < 10; i++) {
    time = ULID_ALPHABET[remaining % 32] + time
    remaining = Math.floor(remaining / 32)
  }

  const random = crypto.getRandomValues(new Uint8Array(16))

  return time + Array.from(random, byte => ULID_ALPHABET[byte % 32]).join('')
}

/**
 * 新しいノードの ID(種類_ULID)。
 */
export function newId(type: string): string {
  return `${type}_${ulid()}`
}

/**
 * 定義の既定値で新しいノードを作る。
 */
export function createNode(registry: Registry, type: string): BuilderNode {
  const definition = registry.blocks[type]
  const props: Record<string, unknown> = {}

  for (const [name, prop] of Object.entries(definition.props)) {
    props[name] = structuredClone(prop.default)
  }

  const node: BuilderNode = { id: newId(type), type, props, styles: {} }

  if (definition.children.length > 0) {
    node.children = []
  }

  return node
}

/**
 * ノードとその子孫の ID をすべて振り直した写し(複製・貼り付け用)。
 */
export function cloneWithNewIds(node: BuilderNode): BuilderNode {
  const copy = structuredClone(node)
  const renew = (target: BuilderNode) => {
    target.id = newId(target.type)
    target.children?.forEach(renew)
  }
  renew(copy)

  return copy
}

/**
 * 親(null はページの直下)の中に、その種類のブロックを置けるか。
 */
export function canPlace(registry: Registry, parentType: string | null, childType: string): boolean {
  const children = parentType === null ? registry.rootChildren : registry.blocks[parentType]?.children ?? []

  return children.includes(childType)
}

export interface NodeLocation {
  // 親(null はページの直下)
  parent: BuilderNode | null
  siblings: BuilderNode[]
  index: number
}

/**
 * ノードの場所(親・兄弟の配列・その中の位置)。見つからなければ null。
 */
export function findLocation(content: BuilderContent, id: string): NodeLocation | null {
  const search = (parent: BuilderNode | null, siblings: BuilderNode[]): NodeLocation | null => {
    for (const [index, node] of siblings.entries()) {
      if (node.id === id) {
        return { parent, siblings, index }
      }
      const found = node.children ? search(node, node.children) : null
      if (found) {
        return found
      }
    }

    return null
  }

  return search(null, content.children)
}

/**
 * ID のノード。見つからなければ null。
 */
export function findNode(content: BuilderContent, id: string): BuilderNode | null {
  const location = findLocation(content, id)

  return location ? location.siblings[location.index] : null
}

/**
 * ページの直下から、ID のノードの親までの祖先(ページに近い順)。
 */
export function ancestorsOf(content: BuilderContent, id: string): BuilderNode[] {
  const ancestors: BuilderNode[] = []
  let location = findLocation(content, id)

  while (location?.parent) {
    ancestors.unshift(location.parent)
    location = findLocation(content, location.parent.id)
  }

  return ancestors
}

/**
 * target が node 自身か、その子孫か。
 */
export function containsNode(node: BuilderNode, targetId: string): boolean {
  return node.id === targetId || (node.children ?? []).some(child => containsNode(child, targetId))
}

/**
 * 親(null はページの直下)の子の配列。親が見つからない・子を持てない種類なら null。
 */
function childrenOf(content: BuilderContent, parentId: string | null): BuilderNode[] | null {
  if (parentId === null) {
    return content.children
  }

  return findNode(content, parentId)?.children ?? null
}

function typeOf(content: BuilderContent, parentId: string | null): string | null {
  return parentId === null ? null : findNode(content, parentId)?.type ?? null
}

/**
 * 親の index の位置にノードを入れる。置けない場所なら入れずに false を返す。
 */
export function insertNode(registry: Registry, content: BuilderContent, parentId: string | null, index: number, node: BuilderNode): boolean {
  const children = childrenOf(content, parentId)

  if (!children || !canPlace(registry, typeOf(content, parentId), node.type)) {
    return false
  }

  children.splice(Math.max(0, Math.min(index, children.length)), 0, node)

  return true
}

/**
 * ノードを親の index の位置へ移す(index は移す前の並びで数えた位置)。
 * 置けない場所・自分の中へは移さずに false を返す。
 */
export function moveNode(registry: Registry, content: BuilderContent, id: string, parentId: string | null, index: number): boolean {
  const from = findLocation(content, id)
  const children = childrenOf(content, parentId)

  if (!from || !children) {
    return false
  }

  const node = from.siblings[from.index]

  if ((parentId !== null && containsNode(node, parentId)) || !canPlace(registry, typeOf(content, parentId), node.type)) {
    return false
  }

  // 同じ親の中で後ろへ移すときは、抜いた分だけ位置が前にずれる
  const target = from.siblings === children && from.index < index ? index - 1 : index

  from.siblings.splice(from.index, 1)
  children.splice(Math.max(0, Math.min(target, children.length)), 0, node)

  return true
}

/**
 * ノードを(子ごと)取り除き、取り除いたノードを返す。見つからなければ null。
 */
export function removeNode(content: BuilderContent, id: string): BuilderNode | null {
  const location = findLocation(content, id)

  return location ? location.siblings.splice(location.index, 1)[0] : null
}

/**
 * 内容のノードの数。
 */
export function countNodes(nodes: BuilderNode[]): number {
  return nodes.reduce((count, node) => count + 1 + countNodes(node.children ?? []), 0)
}
