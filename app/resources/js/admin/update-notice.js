/**
 * 管理画面の上部の、閉じられるお知らせ(更新できる Biscuit のバージョン・Biscuit からの重要なお知らせ)。
 * 要素の data-dismissible(例: update:1.2.3、announcement:2026-10-10-security)ごとに「閉じる」を覚えておき、
 * 閉じたお知らせはこのブラウザで出さない(新しいバージョン・新しいお知らせはまた出す)。
 */
const STORAGE_PREFIX = 'biscuit.dismissed:';

export function initUpdateNotice() {
    for (const notice of document.querySelectorAll('[data-dismissible]')) {
        const key = STORAGE_PREFIX + notice.dataset.dismissible;

        try {
            if (localStorage.getItem(key) === '1') {
                notice.remove();
                continue;
            }
        } catch {
            // ブラウザの保存が使えないときは、毎回出す
        }

        notice.hidden = false;
        notice.querySelector('[data-dismiss-notice]')?.addEventListener('click', () => {
            try {
                localStorage.setItem(key, '1');
            } catch {
                // 覚えられなくても、この画面では隠す
            }
            notice.remove();
        });
    }
}
