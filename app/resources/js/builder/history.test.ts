import { describe, expect, it } from 'vitest'
import { createHistory, MERGE_MILLISECONDS } from './history'

describe('createHistory', () => {
  it('元に戻す・やり直す', () => {
    const history = createHistory()
    history.record('A')
    history.record('B')

    expect(history.undo('C')).toBe('B')
    expect(history.undo('B')).toBe('A')
    expect(history.undo('A')).toBeNull()
    expect(history.redo('A')).toBe('B')
    expect(history.redo('B')).toBe('C')
    expect(history.canRedo()).toBe(false)
  })

  it('新しい操作をするとやり直しは消える', () => {
    const history = createHistory()
    history.record('A')
    history.undo('B')
    history.record('A')

    expect(history.canRedo()).toBe(false)
  })

  it('続けて同じ項目を変える操作は 1 回にまとめ、間が空くか別の項目なら分ける', () => {
    const history = createHistory()
    history.record('見', 'prop:heading:text', 0)
    history.record('見出', 'prop:heading:text', 300)
    history.record('見出し', 'prop:heading:text', 600)
    history.record('見出し!', 'prop:heading:text', 600 + MERGE_MILLISECONDS)
    history.record('X', 'prop:heading:level', 600 + MERGE_MILLISECONDS + 10)

    expect(history.undo('now')).toBe('X')
    expect(history.undo('X')).toBe('見出し!')
    expect(history.undo('見出し!')).toBe('見')
    expect(history.canUndo()).toBe(false)
  })

  it('取り消したあとの操作は直前とまとめない', () => {
    const history = createHistory()
    history.record('A', 'prop:x', 0)
    history.undo('B')
    history.record('A', 'prop:x', 10)

    expect(history.canUndo()).toBe(true)
  })

  it('上限を超えたら古いものから捨てる', () => {
    const history = createHistory(2)
    history.record('A')
    history.record('B')
    history.record('C')

    expect(history.undo('D')).toBe('C')
    expect(history.undo('C')).toBe('B')
    expect(history.undo('B')).toBeNull()
  })
})
