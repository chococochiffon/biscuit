<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '../i18n'
import { useBuilderStore } from '../store'
import type { BuilderNode, Device } from '../types'
import { isValidPeriod, periodState, VISIBILITY_DEVICES } from '../visibility'

// プロパティの「表示」タブ: ブロックの表示条件(表示しない端末・表示する期間)を編集する。
// 端末は公開側で画面幅に合わせて隠し(HTML には残る)、期間の外のブロックは公開側に出さない(エディタの Canvas には出す)
const props = defineProps<{
  node: BuilderNode
}>()

const store = useBuilderStore()

const DEVICE_LABELS: Record<Device, string> = {
  desktop: t('デスクトップで表示しない'),
  tablet: t('タブレットで表示しない'),
  mobile: t('スマートフォンで表示しない'),
}

const hideOn = computed(() => props.node.visibility?.hideOn ?? [])
const startAt = computed(() => props.node.visibility?.startAt ?? '')
const endAt = computed(() => props.node.visibility?.endAt ?? '')
const periodError = ref<string | null>(null)

const PERIOD_STATES = {
  always: null,
  scheduled: { text: t('表示期間の前です(公開側にはまだ出ません)。'), color: 'text-warning-emphasis' },
  active: { text: t('表示期間の中です。'), color: 'text-success' },
  ended: { text: t('表示期間を過ぎています(公開側には出ません)。'), color: 'text-secondary' },
}
const period = computed(() => PERIOD_STATES[periodState(props.node, store.now())])

/**
 * 残り 1 つの端末は、すべての端末で表示しないことにならないよう外せなくする。
 */
function isLocked(device: Device): boolean {
  return !hideOn.value.includes(device) && hideOn.value.length === VISIBILITY_DEVICES.length - 1
}

function toggleDevice(device: Device, hidden: boolean): void {
  store.updateVisibility(props.node.id, {
    hideOn: hidden ? [...hideOn.value, device] : hideOn.value.filter(item => item !== device),
  })
}

function updatePeriod(key: 'startAt' | 'endAt', event: Event): void {
  const input = event.target as HTMLInputElement
  const value = input.value || null
  const next = { startAt: startAt.value || null, endAt: endAt.value || null, [key]: value }

  if (!isValidPeriod(next.startAt, next.endAt)) {
    periodError.value = t('表示を終える日時は、表示を始める日時より後にしてください。')
    // 反映しなかった値を、入力欄から元に戻す
    input.value = key === 'startAt' ? startAt.value : endAt.value

    return
  }

  periodError.value = null
  store.updateVisibility(props.node.id, { [key]: value })
}
</script>

<template>
  <section class="mb-3">
    <h3 class="builder-panel-heading">{{ t('端末') }}</h3>
    <div v-for="device in VISIBILITY_DEVICES" :key="device" class="form-check small">
      <input
        :id="`visibility-${device}`"
        class="form-check-input"
        type="checkbox"
        :checked="hideOn.includes(device)"
        :disabled="isLocked(device)"
        @change="toggleDevice(device, ($event.target as HTMLInputElement).checked)"
      >
      <label class="form-check-label" :for="`visibility-${device}`">{{ DEVICE_LABELS[device] }}</label>
    </div>
    <p class="small text-secondary mt-1 mb-0">
      {{ t('公開側で、その端末の画面幅のときだけ隠します。すべての端末を選ぶことはできません。') }}
    </p>
  </section>

  <section class="mb-3">
    <h3 class="builder-panel-heading">{{ t('表示する期間') }}</h3>
    <div class="mb-2">
      <label for="visibility-start" class="form-label small mb-1">{{ t('表示を始める日時') }}</label>
      <input id="visibility-start" type="datetime-local" class="form-control form-control-sm" :value="startAt" @change="updatePeriod('startAt', $event)">
    </div>
    <div class="mb-2">
      <label for="visibility-end" class="form-label small mb-1">{{ t('表示を終える日時') }}</label>
      <input id="visibility-end" type="datetime-local" class="form-control form-control-sm" :value="endAt" @change="updatePeriod('endAt', $event)">
    </div>
    <div v-if="periodError" class="small text-danger mb-2">{{ periodError }}</div>
    <p v-if="period" class="small mb-1" :class="period.color">{{ period.text }}</p>
    <p class="small text-secondary mb-0">
      {{ t('空欄なら期限を設けません。期間の外は公開側に出しません(プレビューとこの画面には出します)。') }}
    </p>
  </section>
</template>
