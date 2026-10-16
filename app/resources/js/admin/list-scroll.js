/**
 * 一覧の画面(main[data-list-page])のスクロール位置を、編集・削除などでほかの画面へ移るときに覚え、
 * 同じ一覧(クエリ付きの URL が同じ)へ戻ってきたときに元の位置へ戻す。
 * ページ・検索条件・並び順はサーバー側(AdminListUrl)が戻し先の URL として覚えている。
 * ページ送り・並び順の見出し・検索フォームの操作は別の一覧を開くため、位置を覚えない。
 */
const STORAGE_PREFIX = 'admin.listScroll:';
const IGNORED_AREAS = '.pagination, .admin-search-card, thead';

export function initListScrollRestore() {
    const main = document.querySelector('main[data-list-page]');
    if (!main) {
        return;
    }

    const key = STORAGE_PREFIX + window.location.pathname + window.location.search;

    // 覚えた位置があれば戻し、使ったものは消す(サイドメニューなどから開き直したときに戻さないため)
    try {
        const saved = window.sessionStorage.getItem(key);
        window.sessionStorage.removeItem(key);
        const y = Number(saved);
        if (saved !== null && Number.isFinite(y)) {
            window.scrollTo(0, y);
        }
    } catch {
        // ストレージが使えないブラウザでは位置を戻さない
    }

    // 一覧の中の操作(編集・削除のボタン、公開ステータスの変更など)のあとに画面を離れたときだけ位置を覚える
    let leavingFromList = false;
    const markLeaving = (event) => {
        leavingFromList = !event.target.closest(IGNORED_AREAS);
    };
    main.addEventListener('click', markLeaving);
    main.addEventListener('change', markLeaving);
    document.addEventListener('click', (event) => {
        if (!main.contains(event.target)) {
            leavingFromList = false;
        }
    });

    window.addEventListener('pagehide', () => {
        if (!leavingFromList) {
            return;
        }
        try {
            window.sessionStorage.setItem(key, String(window.scrollY));
        } catch {
            // ストレージが使えないブラウザでは位置を覚えない
        }
    });
}
