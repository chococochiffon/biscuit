// 編集の履歴(Undo/Redo)。内容(ノードの木)の写しを JSON の文字列で持つ(画面から切り離した処理で history.test.ts で確かめる)。
// 文字の入力のように続けて同じ項目を変える操作は、key が同じで MERGE_MILLISECONDS 以内なら 1 回の操作にまとめる
// (1 文字ごとに履歴を積まない)

// 続けて同じ項目を変えたときに 1 回の操作にまとめる間隔
export const MERGE_MILLISECONDS = 1000

// 戻せる操作の数
export const HISTORY_LIMIT = 100

export function createHistory(limit: number = HISTORY_LIMIT) {
  let past: string[] = []
  let future: string[] = []
  let lastKey: string | null = null
  let lastAt = 0

  return {
    /**
     * 操作の前の内容を積む。key が直前と同じで、間隔が短ければ積まない(直前の操作にまとめる)。
     */
    record(snapshot: string, key: string | null = null, now: number = Date.now()): void {
      const merges = key !== null && key === lastKey && now - lastAt < MERGE_MILLISECONDS
      lastKey = key
      lastAt = now

      if (merges) {
        return
      }

      past.push(snapshot)
      future = []

      if (past.length > limit) {
        past.shift()
      }
    },

    /**
     * 1 つ前の内容を返し、今の内容をやり直し用に積む。戻せなければ null。
     */
    undo(current: string): string | null {
      const previous = past.pop()

      if (previous === undefined) {
        return null
      }

      future.push(current)
      lastKey = null

      return previous
    },

    /**
     * 取り消した内容をやり直す。やり直せなければ null。
     */
    redo(current: string): string | null {
      const next = future.pop()

      if (next === undefined) {
        return null
      }

      past.push(current)
      lastKey = null

      return next
    },

    canUndo: () => past.length > 0,
    canRedo: () => future.length > 0,
  }
}

export type History = ReturnType<typeof createHistory>
