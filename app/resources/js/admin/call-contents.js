/**
 * サイト設定フォームの呼び出しコンテンツ行(選択肢の絞り込み)とデータ種別紐付け管理モーダル。
 */
import { initManagerModal } from './manager-modal.js';
import { initEditableRows } from './rows.js';
import { t } from './utils.js';

/**
 * 呼び出しコンテンツ(call_contents)の行入力UI(サイト設定の登録・編集フォーム)を初期化する。
 * 「+」で行を追加、「−」で行を削除する。
 */
export function initCallContentRows() {
    const container = document.getElementById('call-content-rows');

    if (!container) {
        return;
    }

    const constraints = JSON.parse(container.dataset.callTypeConstraints || '{}');

    initEditableRows(container, {
        rowSelector: '[data-role="call-content-row"]',
        addButton: document.getElementById('call-content-add'),
        template: document.getElementById('call-content-row-template'),
        onBindRow: (row) => bindCallContentFields(row, constraints),
    });
}

/**
 * 呼び出しコンテンツの入力欄(表示箇所・呼び出し方・データ種別・表示件数)を持つ行に、選択肢の絞り込みを登録する。
 * 表示箇所(data-role="place-select")は選択欄のほか、固定の値を持つ隠しinput(レイアウトの部品)でもよい。
 */
export function bindCallContentFields(row, constraints) {
    const placeSelect = row.querySelector('[data-role="place-select"]');
    const callTypeSelect = row.querySelector('[data-role="call-type-select"]');
    const callTypeError = row.querySelector('[data-role="call-type-error"]');
    const relationSelect = row.querySelector('[data-role="content-model-relation-select"]');
    const relationError = row.querySelector('[data-role="content-model-relation-error"]');

    placeSelect.addEventListener('change', () => applyPlaceConstraints(row, constraints));
    callTypeSelect.addEventListener('change', () => {
        setFieldError(callTypeSelect, callTypeError, null);
        applyCallTypeConstraints(row, constraints);
    });
    relationSelect.addEventListener('change', () => setFieldError(relationSelect, relationError, null));
    applyPlaceConstraints(row, constraints);
}

/**
 * 表示箇所(place)の選択に応じて、同じ行の呼び出し方(call_type)の選択肢を絞り込み、
 * 続けてデータ種別・表示件数の制御(applyCallTypeConstraints)を行う。
 * 表示箇所が未選択の場合はすべての呼び出し方を候補にする。
 * 既存の選択値が選択できなくなった場合は、値をクリアした上でその行にエラーを表示する。
 */
function applyPlaceConstraints(row, constraints) {
    const placeSelect = row.querySelector('[data-role="place-select"]');
    const callTypeSelect = row.querySelector('[data-role="call-type-select"]');
    const callTypeError = row.querySelector('[data-role="call-type-error"]');

    const placeRule = constraints.places?.[placeSelect.value];
    const previousCallTypeValue = callTypeSelect.value;

    Array.from(callTypeSelect.options).forEach((option) => {
        if (!option.value) {
            return;
        }

        const allowed = !placeRule || placeRule.callTypes.includes(Number(option.value));
        option.hidden = !allowed;
        option.disabled = !allowed;
    });

    if (previousCallTypeValue && callTypeSelect.selectedOptions[0]?.hidden) {
        callTypeSelect.value = '';
        setFieldError(callTypeSelect, callTypeError, t('選択した表示箇所ではこの呼び出し方は選択できなくなりました。呼び出し方を選び直してください。'));
    } else {
        setFieldError(callTypeSelect, callTypeError, null);
    }

    applyCallTypeConstraints(row, constraints);
}

/**
 * 表示箇所(place)・呼び出し方(call_type)の選択に応じて、同じ行のデータ種別(モデル名)の選択肢を絞り込み、
 * 表示件数を固定(読み取り専用・値1)にするかどうかを切り替える。
 * 表示箇所が未選択の場合は、呼び出し方で選択可能なモデル名(全表示箇所の和集合)を候補にする。
 * 既存の選択値が選択できなくなった場合は、値をクリアした上でその行にエラーを表示する。
 */
function applyCallTypeConstraints(row, constraints) {
    const placeSelect = row.querySelector('[data-role="place-select"]');
    const callTypeSelect = row.querySelector('[data-role="call-type-select"]');
    const relationSelect = row.querySelector('[data-role="content-model-relation-select"]');
    const relationError = row.querySelector('[data-role="content-model-relation-error"]');
    const viewCountInput = row.querySelector('[data-role="view-count-input"]');

    const callType = callTypeSelect.value;

    // 呼び出し方が未選択(表示箇所の変更でクリアされた場合を含む)なら、データ種別の絞り込みを解除する
    if (!callType) {
        Array.from(relationSelect.options).forEach((option) => {
            option.hidden = false;
            option.disabled = !option.value;
        });
        viewCountInput.readOnly = false;

        return;
    }

    const placeRule = constraints.places?.[placeSelect.value];
    const allowedModelNames = placeRule
        ? (placeRule.modelNamesByCallType[callType] ?? [])
        : (constraints.modelNamesByCallType?.[callType] ?? []);
    const previousRelationValue = relationSelect.value;

    Array.from(relationSelect.options).forEach((option) => {
        if (!option.value) {
            return;
        }

        const allowed = allowedModelNames.includes(option.dataset.modelName);
        option.hidden = !allowed;
        option.disabled = !allowed;
    });

    if (previousRelationValue && relationSelect.selectedOptions[0]?.hidden) {
        relationSelect.value = '';
        setFieldError(relationSelect, relationError, t('選択した表示箇所・呼び出し方ではこのデータ種別は選択できなくなりました。データ種別を選び直してください。'));
    } else {
        setFieldError(relationSelect, relationError, null);
    }

    if (constraints.fixedViewCount?.[callType]) {
        viewCountInput.value = '1';
        viewCountInput.readOnly = true;
    } else {
        viewCountInput.readOnly = false;
        if (!viewCountInput.value) {
            viewCountInput.value = '1';
        }
    }
}

/**
 * セレクトボックスにバリデーションエラー表示(赤枠+メッセージ)を設定/解除する。
 * messageがnullの場合はエラー表示を解除する。
 */
function setFieldError(select, errorElement, message) {
    select.classList.toggle('is-invalid', Boolean(message));
    errorElement.textContent = message ?? '';
    errorElement.hidden = !message;
}

/**
 * ページ内のすべての「データ種別」セレクト(呼び出しコンテンツの行テンプレート含む)に対してコールバックを実行する。
 */
function forEachContentModelRelationSelect(callback) {
    document.querySelectorAll('[data-role="content-model-relation-select"]').forEach(callback);

    const template = document.getElementById('call-content-row-template');
    template?.content.querySelectorAll('[data-role="content-model-relation-select"]').forEach(callback);
}

/**
 * データ種別紐付けの作成/更新結果を、ページ内のすべての「データ種別」セレクトの選択肢へ反映する。
 */
function upsertContentModelRelationOption(relation) {
    forEachContentModelRelationSelect((select) => {
        let option = select.querySelector(`option[value="${relation.id}"]`);

        if (!option) {
            option = document.createElement('option');
            option.value = String(relation.id);
            select.appendChild(option);
        }

        option.dataset.contentType = String(relation.content_type);
        // 呼び出し方の組み合わせは、カスタムページなら種類のベースの型(CustomArticle など)で判定する
        option.dataset.modelName = relation.matrix_model_name ?? relation.model_name;
        option.textContent = `${relation.content_type_label} / ${relation.model_name}`;
    });

    reapplyAllCallContentConstraints();
}

/**
 * 削除されたデータ種別紐付けを、ページ内のすべての「データ種別」セレクトの選択肢から取り除く。
 * 選択中だった行はいったん未選択に戻す。
 */
function removeContentModelRelationOption(id) {
    forEachContentModelRelationSelect((select) => {
        const option = select.querySelector(`option[value="${id}"]`);

        if (!option) {
            return;
        }

        if (select.value === String(id)) {
            select.value = '';
        }

        option.remove();
    });
}

/**
 * 既存の呼び出しコンテンツ行すべてに、表示箇所(place)からの選択肢の絞り込みを再適用する。
 * データ種別紐付けをその場で追加/更新した直後に、絞り込み状態を最新化するために呼び出す。
 */
function reapplyAllCallContentConstraints() {
    const container = document.getElementById('call-content-rows');

    if (!container) {
        return;
    }

    const constraints = JSON.parse(container.dataset.callTypeConstraints || '{}');
    container.querySelectorAll('[data-role="call-content-row"]').forEach((row) => applyPlaceConstraints(row, constraints));
}

/**
 * データ種別紐付け(ContentModelRelation)の管理モーダル(サイト設定フォーム)を初期化する。
 * 一覧取得・登録・更新・削除をAjaxで行い、呼び出しコンテンツ行の「データ種別」セレクトへ即時反映する。
 */
export function initContentModelRelationManagerModal() {
    const modal = document.getElementById('content-model-relation-manager-modal');

    if (!modal) {
        return;
    }

    const contentTypeSelect = document.getElementById('content-model-relation-manager-content-type');
    const modelNameInput = document.getElementById('content-model-relation-manager-model-name');
    const tableNameSelect = document.getElementById('content-model-relation-manager-table-name');

    // 許可リスト外の動的テーブル(user_make_*)を編集する場合は、選択肢に足してから選ぶ
    function ensureTableNameOption(tableName) {
        if (!tableName || tableNameSelect.querySelector(`option[value="${tableName}"]`)) {
            return;
        }

        const option = document.createElement('option');
        option.value = tableName;
        option.textContent = tableName;
        tableNameSelect.appendChild(option);
    }

    initManagerModal(modal, {
        listContainer: document.getElementById('content-model-relation-manager-list'),
        submitButton: document.getElementById('content-model-relation-manager-submit'),
        errorBox: document.getElementById('content-model-relation-manager-error'),
        focusTarget: modelNameInput,
        messages: {
            deleteFailed: t('データ種別の紐付けの削除に失敗しました。'),
            deleteRejected: t('このデータ種別の紐付けは使用されているため削除できません。'),
            saveFailed: t('データ種別の紐付けの保存に失敗しました。'),
        },
        itemLabel: (relation) => `${relation.content_type_label} / ${relation.model_name}`,
        readForm() {
            const modelName = modelNameInput.value.trim();
            const tableName = tableNameSelect.value;

            if (modelName === '' || tableName === '') {
                return null;
            }

            return { content_type: contentTypeSelect.value, model_name: modelName, table_name: tableName };
        },
        fillForm(relation) {
            contentTypeSelect.value = String(relation.content_type);
            modelNameInput.value = relation.model_name;
            ensureTableNameOption(relation.table_name);
            tableNameSelect.value = relation.table_name;
        },
        clearForm() {
            contentTypeSelect.selectedIndex = 0;
            modelNameInput.value = '';
            tableNameSelect.value = '';
        },
        renderItem(relation, { edit, remove }) {
            const item = document.createElement('div');
            item.className = 'list-group-item d-flex justify-content-between align-items-center';

            const label = document.createElement('span');
            label.textContent = `${relation.content_type_label} / ${relation.model_name} `;

            const tableNameBadge = document.createElement('span');
            tableNameBadge.className = 'text-muted small';
            tableNameBadge.textContent = `(${relation.table_name})`;
            label.appendChild(tableNameBadge);
            item.appendChild(label);

            const actions = document.createElement('div');
            actions.className = 'd-flex gap-2';

            const editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'btn btn-sm btn-outline-secondary';
            editButton.innerHTML = '<i class="bi bi-pencil"></i>';
            editButton.setAttribute('aria-label', t('編集'));
            editButton.addEventListener('click', edit);
            actions.appendChild(editButton);

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'btn btn-sm btn-outline-danger';
            deleteButton.innerHTML = '<i class="bi bi-trash"></i>';
            deleteButton.setAttribute('aria-label', t('削除'));
            deleteButton.addEventListener('click', remove);
            actions.appendChild(deleteButton);

            item.appendChild(actions);

            return item;
        },
        onSaved: (saved) => upsertContentModelRelationOption(saved),
        onDeleted: (relation) => removeContentModelRelationOption(relation.id),
    });
}
