/**
 * 記事一覧の公開設定(個別・一括)の変更。
 */
import { t, csrfToken } from './utils.js';

/**
 * 隠しフォームを動的に生成してsubmitする。既存のフォームへネストさせずに
 * 一覧画面のチェックボックス一括操作やインライン更新をLaravelの通常のPOST/PATCHで送信するために使う。
 *
 * @param {string} action 送信先URL
 * @param {string} method HTTPメソッド(GET/POST以外は_methodで上書きする)
 * @param {Record<string, string|string[]>} fields 送信するフィールド(name → 値、配列の場合は同名で複数付与)
 */
function submitHiddenForm(action, method, fields) {

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = action;
    form.style.display = 'none';

    const appendHidden = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    };

    appendHidden('_token', csrfToken());

    if (method.toUpperCase() !== 'POST') {
        appendHidden('_method', method);
    }

    Object.entries(fields).forEach(([name, value]) => {
        (Array.isArray(value) ? value : [value]).forEach((v) => appendHidden(name, v));
    });

    document.body.appendChild(form);
    form.submit();
}

/**
 * 記事一覧の公開設定(approval)操作を初期化する。
 * 行ごとのセレクトを変更すると即座にその記事だけを更新し、
 * チェックボックスで選択した複数記事はまとめて一括更新できる。
 */
export function initArticleApprovalControls() {
    document.querySelectorAll('[data-role="approval-inline-select"]').forEach((select) => {
        select.addEventListener('change', () => {
            submitHiddenForm(select.dataset.updateUrl, 'PATCH', { approval: select.value });
        });
    });

    const bulkButton = document.getElementById('bulk-approval-submit');

    if (!bulkButton) {
        return;
    }

    const bulkSelect = document.getElementById('bulk-approval-select');
    const selectAllCheckbox = document.querySelector('[data-role="select-all-articles"]');
    const rowCheckboxes = document.querySelectorAll('[data-role="article-checkbox"]');

    selectAllCheckbox?.addEventListener('change', () => {
        rowCheckboxes.forEach((checkbox) => {
            checkbox.checked = selectAllCheckbox.checked;
        });
    });

    bulkButton.addEventListener('click', () => {
        const articleIds = Array.from(rowCheckboxes)
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => checkbox.value);

        if (articleIds.length === 0) {
            window.alert(t('記事を選択してください。'));

            return;
        }

        submitHiddenForm(bulkButton.dataset.actionUrl, 'PATCH', {
            approval: bulkSelect.value,
            'article_ids[]': articleIds,
        });
    });
}
