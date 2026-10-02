import { describe, expect, it } from 'vitest'
import type { BuilderNode } from './types'
import { formatDateTime, isHiddenOn, isValidPeriod, periodState, setVisibility } from './visibility'

function node(visibility?: BuilderNode['visibility']): BuilderNode {
  return { id: 'heading_01K0000000000000000000000A', type: 'heading', props: {}, styles: {}, ...(visibility ? { visibility } : {}) }
}

describe('表示条件', () => {
  it('タイムゾーンでの日時を期間と同じ形にする', () => {
    expect(formatDateTime('Asia/Tokyo', new Date('2026-10-01T15:05:00Z'))).toBe('2026-10-02T00:05')
  })

  it('表示しない端末を判定する', () => {
    expect(isHiddenOn(node({ hideOn: ['mobile'] }), 'mobile')).toBe(true)
    expect(isHiddenOn(node({ hideOn: ['mobile'] }), 'desktop')).toBe(false)
    expect(isHiddenOn(node(), 'mobile')).toBe(false)
  })

  it('期間の中かを判定する(開始は含み、終了は含まない)', () => {
    const scheduled = node({ startAt: '2026-10-02T12:00', endAt: '2026-10-03T00:00' })

    expect(periodState(node(), '2026-10-02T12:00')).toBe('always')
    expect(periodState(scheduled, '2026-10-02T11:59')).toBe('scheduled')
    expect(periodState(scheduled, '2026-10-02T12:00')).toBe('active')
    expect(periodState(scheduled, '2026-10-03T00:00')).toBe('ended')
    expect(periodState(node({ endAt: '2026-10-03T00:00' }), '2026-01-01T00:00')).toBe('active')
  })

  it('空の項目を消し、何も残らなければ visibility ごと消す', () => {
    const target = node()

    expect(setVisibility(target, { hideOn: ['mobile', 'desktop'] })).toBe(true)
    expect(target.visibility).toEqual({ hideOn: ['desktop', 'mobile'] })
    expect(setVisibility(target, { startAt: '2026-10-02T12:00' })).toBe(true)
    expect(target.visibility).toEqual({ hideOn: ['desktop', 'mobile'], startAt: '2026-10-02T12:00' })
    expect(setVisibility(target, { hideOn: [], startAt: null })).toBe(true)
    expect(target.visibility).toBeUndefined()
    expect(setVisibility(target, { endAt: null })).toBe(false)
  })

  it('すべての端末で表示しない・開始が終了より後になる書き換えはしない', () => {
    const target = node({ hideOn: ['desktop', 'tablet'], endAt: '2026-10-03T00:00' })

    expect(setVisibility(target, { hideOn: ['desktop', 'tablet', 'mobile'] })).toBe(false)
    expect(setVisibility(target, { startAt: '2026-10-03T00:00' })).toBe(false)
    expect(target.visibility).toEqual({ hideOn: ['desktop', 'tablet'], endAt: '2026-10-03T00:00' })
    expect(isValidPeriod('2026-10-02T00:00', null)).toBe(true)
  })
})
