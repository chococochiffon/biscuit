/**
 * 一覧の表示と登録・編集・削除を Ajax で行う管理モーダルの共通処理。
 */
import { t, requestJson } from './utils.js';

/**
 * 一覧の表示と登録・編集・削除を Ajax で行う管理モーダルの共通処理(タグ管理・データ種別紐付け管理)。
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
}) {
    const indexUrl = listContainer.dataset.indexUrl;
    let editing = null;

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
            listContainer.appendChild(renderItem(item, { edit: () => edit(item), remove: () => remove(item) }));
        });
        onLoaded(items);
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
