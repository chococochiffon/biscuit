import type { BuilderNode, PropDefinition } from './types'

// 独自コンポーネント(使うたびに一部の項目を差し替えられる部品)の処理(custom.test.ts で確かめる)。
// 部品の中身のノードの exposed(項目名 → 表示名)が差し替えられる項目で、ページに置いた custom ブロックの props.values に
// 「ノードの ID.項目名」をキーにして差し替えた値を持つ(biscuit の BlockDataResolver::customComponentChildren() と同じ当てはめ方)

export interface ExposedField {
  // 差し替えた値のキー(ノードの ID.項目名)
  key: string
  node: BuilderNode
  prop: string
  label: string
}

/**
 * 項目を差し替えられる項目にできるか(選択肢を登録済みのデータから作る項目・差し替えた値の項目はできない)。
 */
export function isExposable(definition: PropDefinition): boolean {
  return definition.source === undefined && definition.type !== 'overrides'
}

/**
 * 部品の中身の差し替えられる項目(木の並び順)。
 */
export function exposedFields(nodes: BuilderNode[]): ExposedField[] {
  return nodes.flatMap(node => [
    ...Object.entries(node.exposed ?? {}).map(([prop, label]) => ({ key: `${node.id}.${prop}`, node, prop, label })),
    ...exposedFields(node.children ?? []),
  ])
}

/**
 * 部品の中身に差し替えた値を当てはめた写し(空の値・差し替えられる項目でないものは部品の値のまま)。
 */
export function applyOverrides(nodes: BuilderNode[], values: Record<string, unknown>): BuilderNode[] {
  return nodes.map((node) => {
    const props = { ...node.props }

    for (const prop of Object.keys(node.exposed ?? {})) {
      const value = values[`${node.id}.${prop}`]

      if (value !== undefined && value !== null && value !== '') {
        props[prop] = value
      }
    }

    return { ...node, props, ...(node.children ? { children: applyOverrides(node.children, values) } : {}) }
  })
}

/**
 * 項目を差し替えられる項目にする(表示名)・やめる(null)。空になったら exposed ごと消す。変えたら true。
 */
export function setExposed(node: BuilderNode, prop: string, label: string | null): boolean {
  if ((node.exposed?.[prop] ?? null) === label) {
    return false
  }

  const exposed = { ...node.exposed }

  if (label === null) {
    delete exposed[prop]
  }
  else {
    exposed[prop] = label
  }

  if (Object.keys(exposed).length === 0) {
    delete node.exposed
  }
  else {
    node.exposed = exposed
  }

  return true
}

/**
 * 独自コンポーネントのブロックの差し替えた値を変える(null・空文字は部品の値のままにする = キーを消す)。変えたら true。
 */
export function setOverride(node: BuilderNode, key: string, value: unknown): boolean {
  const values = { ...(node.props.values as Record<string, unknown> | undefined) }
  const next = value === '' ? null : value

  if ((values[key] ?? null) === next) {
    return false
  }

  if (next === null) {
    delete values[key]
  }
  else {
    values[key] = next
  }

  node.props.values = values

  return true
}
