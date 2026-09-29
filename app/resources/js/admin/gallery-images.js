/**
 * ギャラリー画像の分類管理モーダル。
 */
import { initManagerModal } from './manager-modal.js';
import { t } from './utils.js';

/**
 * 画面の分類の選択肢(data-role="gallery-category-select")を、分類の一覧(並び順)で作り直す。
 * 分類以外の固定の選択肢(先頭の「未分類」/「すべて」、末尾の「未分類」など、値が数字でないもの)はそのまま残す。
 * 選んでいた分類が削除された場合は、先頭の選択肢に戻す。
 */
function refreshCategorySelects(categories) {
    document.querySelectorAll('[data-role="gallery-category-select"]').forEach((select) => {
        const selected = select.value;

        [...select.options].filter((option) => /^\d+$/.test(option.value)).forEach((option) => option.remove());

        const anchor = select.options[1] ?? null;
        categories.forEach((category) => {
            select.insertBefore(new Option(category.name, String(category.id)), anchor);
        });

        select.value = [...select.options].some((option) => option.value === selected) ? selected : select.options[0].value;
    });
}

/**
 * 分類管理モーダル(gallery-category-manager-modal)を初期化する。
 * 登録・名前の変更・削除は管理モーダルの共通処理(initManagerModal)で行い、
 * 一覧の行をドラッグして離すと、その並び順をすぐに保存する。
 * 変更は画面の分類の選択肢にすぐ反映し、data-reload-on-change があれば変更後に閉じたとき画面を再読み込みする。
 */
export function initGalleryCategoryManagerModal() {
    const modal = document.getElementById('gallery-category-manager-modal');

    if (!modal) {
        return;
    }

    const input = document.getElementById('gallery-category-manager-input');
    const list = document.getElementById('gallery-category-manager-list');
    let changed = false;

    function markChanged() {
        changed = true;
    }

    initManagerModal(modal, {
        listContainer: list,
        submitButton: document.getElementById('gallery-category-manager-submit'),
        errorBox: document.getElementById('gallery-category-manager-error'),
        focusTarget: input,
        submitOnEnter: [input],
        messages: {
            deleteFailed: t('分類の削除に失敗しました。'),
            saveFailed: t('分類の保存に失敗しました。'),
        },
        itemLabel: (category) => category.name,
        readForm() {
            const name = input.value.trim();

            return name === '' ? null : { name };
        },
        fillForm(category) {
            input.value = category.name;
        },
        clearForm() {
            input.value = '';
        },
        renderItem(category, { edit, remove }) {
            const row = document.createElement('li');
            row.className = 'list-group-item d-flex align-items-center gap-2';
            const name = document.createElement('span');
            name.className = 'flex-grow-1';
            name.setAttribute('role', 'button');
            name.textContent = category.name;
            name.addEventListener('click', edit);
            row.appendChild(name);

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'btn-close';
            deleteButton.style.fontSize = '0.6rem';
            deleteButton.setAttribute('aria-label', t('分類を削除'));
            deleteButton.addEventListener('click', remove);
            row.appendChild(deleteButton);

            return row;
        },
        onLoaded: refreshCategorySelects,
        onSaved: markChanged,
        onDeleted: markChanged,
        sortable: {
            failedMessage: t('分類の並び替えの保存に失敗しました。'),
            // 分類の選択肢もその順に並べ直す
            onReordered(categories) {
                refreshCategorySelects(categories);
                markChanged();
            },
        },
    });

    modal.addEventListener('hidden.bs.modal', () => {
        if (changed && modal.dataset.reloadOnChange) {
            window.location.reload();
        }
    });
}
