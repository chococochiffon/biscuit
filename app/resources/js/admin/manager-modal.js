/**
 * 一覧の表示と登録・編集・削除を Ajax で行う管理モーダルの共通処理。
 */
import { t, requestJson } from './utils.js';
import { initSortableRows } from './rows.js';

/**
 * 一覧の表示と登録・編集・削除を Ajax で行う管理モーダルの共通処理(タグ・データ種別紐付け・投稿先・ギャラリーの分類の管理)。
 * listContainer の data-index-url を一覧取得・登録の URL、`${indexUrl}/${id}` を更新・削除の URL として使う。
 * モーダルを開くと一覧を読み込み、一覧の項目の「編集」でフォームに値を入れて更新モードにし、閉じると登録モードに戻す。
 * 画面ごとに違う部分は options で渡す:
 * - readForm(): フォームから送信する値を返す(未入力などで送信しない場合は null)
 * - fillForm(item) / clearForm(): 編集時にフォームへ値を入れる / フォームを空にする
 * - renderItem(item, { edit, remove }): 一覧の 1 件の要素を返す(edit・remove は編集開始・削除の関数)
 * - itemLabel(item): 削除確認に表示する名前
 * - messages: 削除・保存に失敗したときのメッセージ(deleteRejected は削除が 422 で拒否され、理由が返らなかったとき)
 * - onSaved(saved, previous, payload) / onDeleted(item): 保存・削除のあとの処理(previous は更新前の項目。登録時は null)
 * - onLoaded(items): 一覧を読み込んだあとの処理(読み込んだ項目の配列を受け取る)
 * - sortable: 一覧の行をドラッグで並び替え、離したときにその並び順をすぐ保存する場合に渡す({ failedMessage, onReordered })。
 *   各行の先頭にドラッグハンドルを付け、listContainer の data-reorder-url へ order(id の配列)を PATCH で送る。
 *   保存に成功したら onReordered(並び替え後の項目の配列) を呼ぶ
 */
export function initManagerModal(modal, {
    listContainer,
    submitButton,
    errorBox,
    focusTarget,
    submitOnEnter = [],
    messages,
    itemLabel,
    readForm,
    fillForm,
    clearForm,
    renderItem,
    onSaved = () => {},
    onDeleted = () => {},
    onLoaded = () => {},
    sortable = null,
}) {
    const indexUrl = listContainer.dataset.indexUrl;
    const sortableRowSelector = '[data-role="manager-sortable-row"]';
    const { bindRow: bindSortableRow } = sortable ? initSortableRows(listContainer, sortableRowSelector) : {};
    let editing = null;
    let loadedItems = [];

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
    }

    function clearError() {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
    }

    function resetForm() {
        editing = null;
        clearForm();
        submitButton.textContent = t('登録');
    }

    function edit(item) {
        editing = item;
        fillForm(item);
        submitButton.textContent = t('更新');
        clearError();
        focusTarget.focus();
    }

    async function load() {
        const response = await requestJson(indexUrl);

        if (!response.ok) {
            return;
        }

        const items = await response.json();

        listContainer.innerHTML = '';
        items.forEach((item) => {
            const element = renderItem(item, { edit: () => edit(item), remove: () => remove(item) });

            if (sortable) {
                makeSortable(element, item);
            }

            listContainer.appendChild(element);
        });
        loadedItems = items;
        onLoaded(items);
    }

    function makeSortable(row, item) {
        row.dataset.role = 'manager-sortable-row';
        row.dataset.id = String(item.id);

        const handle = document.createElement('span');
        handle.className = 'drag-handle';
        handle.dataset.role = 'drag-handle';
        handle.title = t('ドラッグして並び替え');
        handle.innerHTML = '<i class="bi bi-grip-vertical"></i>';
        row.prepend(handle);

        bindSortableRow(row);
        row.addEventListener('dragend', saveOrder);
    }

    /**
     * 一覧の行の並び順を保存する(並びが変わっていなければ何もしない)。
     */
    async function saveOrder() {
        const order = [...listContainer.querySelectorAll(sortableRowSelector)].map((row) => Number(row.dataset.id));

        if (order.every((id, index) => loadedItems[index]?.id === id)) {
            return;
        }

        clearError();

        const response = await requestJson(listContainer.dataset.reorderUrl, { method: 'PATCH', body: { order } });

        if (!response.ok) {
            showError(sortable.failedMessage);

            return;
        }

        loadedItems = order.map((id) => loadedItems.find((item) => item.id === id));
        sortable.onReordered?.(loadedItems);
    }

    async function remove(item) {
        if (!window.confirm(t('「:name」を削除してよろしいですか?', { name: itemLabel(item) }))) {
            return;
        }

        const response = await requestJson(`${indexUrl}/${item.id}`, { method: 'DELETE' });

        if (!response.ok) {
            const rejected = response.status === 422 ? (await response.json()).message ?? messages.deleteRejected : null;
            showError(rejected ?? messages.deleteFailed);

            return;
        }

        if (editing?.id === item.id) {
            resetForm();
        }

        onDeleted(item);
        await load();
    }

    async function submit() {
        const payload = readForm();

        if (payload === null) {
            return;
        }

        clearError();

        const previous = editing;
        const response = await requestJson(previous ? `${indexUrl}/${previous.id}` : indexUrl, {
            method: previous ? 'PUT' : 'POST',
            body: payload,
        });

        if (!response.ok) {
            showError(response.status === 422
                ? Object.values((await response.json()).errors ?? {}).flat().join(' ')
                : messages.saveFailed);

            return;
        }

        onSaved(await response.json(), previous, payload);
        resetForm();
        await load();
    }

    submitButton.addEventListener('click', submit);

    submitOnEnter.forEach((input) => {
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                submit();
            }
        });
    });

    modal.addEventListener('show.bs.modal', () => {
        clearError();
        load();
    });

    modal.addEventListener('hidden.bs.modal', resetForm);

    return { showError, clearError };
}
