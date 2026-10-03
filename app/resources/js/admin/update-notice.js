/**
 * 管理画面の上部の、更新できる Biscuit のバージョンのお知らせ(スーパー管理者だけ)。
 * 「閉じる」で隠したバージョンをこのブラウザに覚えておき、同じバージョンのお知らせは出さない(新しいバージョンが出たらまた出す)。
 */
const STORAGE_KEY = 'biscuit.dismissedUpdateVersion';

export function initUpdateNotice() {
    const notice = document.querySelector('[data-update-notice]');

    if (!notice) {
        return;
    }

    const version = notice.dataset.version;

    try {
        if (localStorage.getItem(STORAGE_KEY) === version) {
            notice.remove();

            return;
        }
    } catch {
        // ブラウザの保存が使えないときは、毎回出す
    }

    notice.hidden = false;
    notice.querySelector('[data-update-notice-close]')?.addEventListener('click', () => {
        try {
            localStorage.setItem(STORAGE_KEY, version);
        } catch {
            // 覚えられなくても、この画面では隠す
        }
        notice.remove();
    });
}
