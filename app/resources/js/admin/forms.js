/**
 * フォーム共通の入力補助(日時ピッカー、公開側 URL のプレビュー)。
 */
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import { Japanese } from 'flatpickr/dist/l10n/ja.js';

/**
 * 日時入力欄(公開開始・公開終了など)にDateTimePicker(flatpickr)を、
 * 日付入力欄(一覧の検索条件など)にDatePicker(flatpickr)を適用する。
 * 送信値はバリデーション(date_format:Y-m-d H:i / Y-m-d)に合わせ、画面上の表示のみ Y/m/d H:i / Y/m/d とする。
 */
export function initDateTimePickers() {
    const pickers = [
        { selector: '[data-role="datetime-picker"]', options: { enableTime: true, time_24hr: true, dateFormat: 'Y-m-d H:i', altFormat: 'Y/m/d H:i' } },
        { selector: '[data-role="date-picker"]', options: { dateFormat: 'Y-m-d', altFormat: 'Y/m/d' } },
    ];

    pickers.forEach(({ selector, options }) => {
        document.querySelectorAll(selector).forEach((input) => {
            flatpickr(input, {
                ...options,
                altInput: true,
                locale: Japanese,
                allowInput: true,
                // ラベル(for属性)・aria-labelが画面上の表示用入力欄を指すよう、元の入力欄(hidden)から移す
                onReady: (selectedDates, dateStr, instance) => {
                    if (!instance.altInput) {
                        return;
                    }

                    if (input.id) {
                        instance.altInput.id = input.id;
                        input.removeAttribute('id');
                    }

                    if (input.hasAttribute('aria-label')) {
                        instance.altInput.setAttribute('aria-label', input.getAttribute('aria-label'));
                    }
                },
            });
        });
    });
}

/**
 * 記事・固定ページの登録・編集画面で、親パスとスラッグの入力に合わせて公開側URLのプレビューを更新する。
 * 組み立て方はサーバー側の HasPath::buildPath() と同じ(前後のスラッシュを除いて「/親パス/スラッグ」)。
 * スラッグが未入力の場合はプレビューの data-fallback(記事番号など)を使い、それもなければ空にする。
 */
export function initPathPreview() {
    const parentPathInput = document.querySelector('[data-role="path-parent"]');
    const slugInput = document.querySelector('[data-role="path-slug"]');
    const preview = document.querySelector('[data-role="path-preview"]');

    if (!parentPathInput || !slugInput || !preview) {
        return;
    }

    const trimSlashes = (value) => value.trim().replace(/^\/+|\/+$/g, '');

    const update = () => {
        const slug = trimSlashes(slugInput.value) || preview.dataset.fallback || '';
        const parentPath = trimSlashes(parentPathInput.value);

        preview.textContent = slug === '' ? '' : `/${[parentPath, slug].filter((part) => part !== '').join('/')}`;
    };

    parentPathInput.addEventListener('input', update);
    slugInput.addEventListener('input', update);
}
