import type { BuilderNode, BuilderVisibility, Device } from './types'

// ブロックの表示条件(ノードの visibility。biscuit の Support\Builder\Visibility と同じ形)の判定と書き換え(visibility.test.ts で確かめる)。
// 期間の日時はサイトのタイムゾーンの YYYY-MM-DDTHH:mm で持つため、今の日時も同じ形の文字列にして比べる

// 表示しない端末に選べる値
export const VISIBILITY_DEVICES: Device[] = ['desktop', 'tablet', 'mobile']

// 期間の中かどうか(always: 期間の指定なし、scheduled: 始まる前、active: 期間の中、ended: 終わったあと)
export type PeriodState = 'always' | 'scheduled' | 'active' | 'ended'

/**
 * タイムゾーンでの日時を、期間と同じ YYYY-MM-DDTHH:mm の形にする。
 */
export function formatDateTime(timezone: string, date: Date = new Date()): string {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-US', { timeZone: timezone, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
      .formatToParts(date)
      .map(part => [part.type, part.value]),
  )

  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`
}

/**
 * ブロックを、その端末で表示しないか。
 */
export function isHiddenOn(node: BuilderNode, device: Device): boolean {
  return node.visibility?.hideOn?.includes(device) ?? false
}

/**
 * ブロックが今(now。YYYY-MM-DDTHH:mm)表示する期間のどこにいるか。開始は含み、終了は含まない。
 */
export function periodState(node: BuilderNode, now: string): PeriodState {
  const { startAt, endAt } = node.visibility ?? {}

  if (!startAt && !endAt) {
    return 'always'
  }

  if (startAt && now < startAt) {
    return 'scheduled'
  }

  return endAt && now >= endAt ? 'ended' : 'active'
}

/**
 * 期間の開始・終了の組み合わせが正しいか(どちらかが空なら正しい)。
 */
export function isValidPeriod(startAt: string | null | undefined, endAt: string | null | undefined): boolean {
  return !startAt || !endAt || startAt < endAt
}

/**
 * 表示条件の一部を書き換える。空の項目(表示しない端末なし・日時なし)は消し、何も残らなければ visibility ごと消す。
 * すべての端末で表示しない・開始が終了より後になる書き換えはしない。変えたら true。
 */
export function setVisibility(node: BuilderNode, patch: Partial<BuilderVisibility>): boolean {
  const next: BuilderVisibility = { ...node.visibility, ...patch }
  const hideOn = VISIBILITY_DEVICES.filter(device => next.hideOn?.includes(device))

  if (hideOn.length === VISIBILITY_DEVICES.length || !isValidPeriod(next.startAt, next.endAt)) {
    return false
  }

  const normalized: BuilderVisibility = {
    ...(hideOn.length > 0 ? { hideOn } : {}),
    ...(next.startAt ? { startAt: next.startAt } : {}),
    ...(next.endAt ? { endAt: next.endAt } : {}),
  }

  if (JSON.stringify(normalized) === JSON.stringify(node.visibility ?? {})) {
    return false
  }

  if (Object.keys(normalized).length === 0) {
    delete node.visibility
  }
  else {
    node.visibility = normalized
  }

  return true
}
