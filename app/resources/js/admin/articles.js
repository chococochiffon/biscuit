/**
 * 記事一覧の公開設定(個別・一括)の変更と、投稿先管理モーダル。
 */
import { t, csrfToken } from './utils.js';
import { initManagerModal } from './manager-modal.js';

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

/**
 * 投稿先管理モーダル(article-path-option-manager-modal)を初期化する。
 * 登録・変更・削除は管理モーダルの共通処理(initManagerModal)で行い、一覧の行をドラッグして離すと、その並び順をすぐに保存する。
 */
export function initArticlePathOptionManagerModal() {
    const modal = document.getElementById('article-path-option-manager-modal');

    if (!modal) {
        return;
    }

    const labelInput = document.getElementById('article-path-option-manager-label');
    const parentPathInput = document.getElementById('article-path-option-manager-parent-path');
    const list = document.getElementById('article-path-option-manager-list');

    initManagerModal(modal, {
        listContainer: list,
        submitButton: document.getElementById('article-path-option-manager-submit'),
        errorBox: document.getElementById('article-path-option-manager-error'),
        focusTarget: labelInput,
        submitOnEnter: [labelInput, parentPathInput],
        messages: {
            deleteFailed: t('投稿先の削除に失敗しました。'),
            saveFailed: t('投稿先の保存に失敗しました。'),
        },
        itemLabel: (option) => option.label,
        readForm() {
            const label = labelInput.value.trim();
            const parentPath = parentPathInput.value.trim();

            return label === '' || parentPath === '' ? null : { label, parent_path: parentPath };
        },
        fillForm(option) {
            labelInput.value = option.label;
            parentPathInput.value = option.parent_path;
        },
        clearForm() {
            labelInput.value = '';
            parentPathInput.value = '';
        },
        renderItem(option, { edit, remove }) {
            const row = document.createElement('li');
            row.className = 'list-group-item d-flex align-items-center gap-2';
            const name = document.createElement('span');
            name.className = 'flex-grow-1';
            name.setAttribute('role', 'button');
            name.textContent = option.label;
            name.addEventListener('click', edit);
            row.appendChild(name);

            const path = document.createElement('code');
            path.textContent = `/${option.parent_path}/`;
            row.appendChild(path);

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'btn-close';
            deleteButton.style.fontSize = '0.6rem';
            deleteButton.setAttribute('aria-label', t('投稿先を削除'));
            deleteButton.addEventListener('click', remove);
            row.appendChild(deleteButton);

            return row;
        },
        sortable: {
            failedMessage: t('投稿先の並び替えの保存に失敗しました。'),
        },
    });
}
