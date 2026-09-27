import { Modal } from 'bootstrap/dist/js/bootstrap.bundle.min.js';
import Quill from 'quill';
import flatpickr from 'flatpickr';
import { Japanese } from 'flatpickr/dist/l10n/ja.js';
import 'flatpickr/dist/flatpickr.min.css';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

document.addEventListener('DOMContentLoaded', () => {
    initTagSelector();
    initTagManagerModal();
    initContentEditor();
    initCallContentRows();
    initRepeaterRows();
    initContentModelRelationManagerModal();
    initSinglePageDetailRows();
    initSinglePageReorder();
    initPathPreview();
    initImageDropzones();
    initImageCroppers();
    initDateTimePickers();
    initArticleApprovalControls();
    initQuestionAnswerForm();
    initQuestionAnswerFlows();
});

/**
 * 画面上の文言を、レイアウトから渡された翻訳(window.adminTranslations。キーは日本語の原文)で返す。
 * 翻訳がなければ原文のまま返す。:name などの置き換え記号は replacements の値で埋める。
 * 使う文言は App\Support\AdminJsTranslations::KEYS にも追加する。
 */
function t(key, replacements = {}) {
    const text = window.adminTranslations?.[key] ?? key;

    return Object.entries(replacements).reduce((result, [name, value]) => result.replaceAll(`:${name}`, String(value)), text);
}

/**
 * レイアウトの meta タグから CSRF トークンを取得する。
 */
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * JSON を返す管理画面の API へ、CSRF トークン付きでリクエストする。
 * body がオブジェクトなら JSON に、FormData ならそのまま送る。
 */
function requestJson(url, { method = 'GET', body } = {}) {
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() };
    let payload = body;

    if (body !== undefined && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    return fetch(url, { method, headers, body: payload });
}

/**
 * タグ検索・選択UI(記事の作成・編集フォーム)を初期化する。
 * 選択済みタグはバッジで表示し、×ボタンで選択解除できる。
 */
function initTagSelector() {
    const container = document.getElementById('tag-selector');

    if (!container) {
        return;
    }

    const input = document.getElementById('tag-input');
    const suggestionsBox = document.getElementById('tag-suggestions');
    const selectedBox = document.getElementById('selected-tags');
    const hiddenInputsContainer = document.getElementById('tag-hidden-inputs');
    const searchUrl = container.dataset.searchUrl;

    let selected = [];

    try {
        selected = JSON.parse(container.dataset.initialTags || '[]');
    } catch {
        selected = [];
    }

    function render() {
        selectedBox.innerHTML = '';
        hiddenInputsContainer.innerHTML = '';

        selected.forEach((name) => {
            const badge = document.createElement('span');
            badge.className = 'badge text-bg-primary d-inline-flex align-items-center gap-2';
            badge.textContent = name;

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'btn-close btn-close-white';
            removeButton.style.fontSize = '0.6rem';
            removeButton.setAttribute('aria-label', t('タグを削除'));
            removeButton.addEventListener('click', () => {
                selected = selected.filter((selectedName) => selectedName !== name);
                render();
            });

            badge.appendChild(removeButton);
            selectedBox.appendChild(badge);

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'tags[]';
            hidden.value = name;
            hiddenInputsContainer.appendChild(hidden);
        });
    }

    function addTag(name) {
        const trimmed = name.trim();

        if (trimmed === '' || selected.includes(trimmed)) {
            return;
        }

        selected.push(trimmed);
        input.value = '';
        suggestionsBox.innerHTML = '';
        render();
    }

    function renderSuggestions(tags) {
        suggestionsBox.innerHTML = '';

        tags
            .filter((tag) => !selected.includes(tag.tag_name))
            .forEach((tag) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.textContent = tag.tag_name;
                item.addEventListener('click', () => addTag(tag.tag_name));
                suggestionsBox.appendChild(item);
            });
    }

    let debounceTimer = null;

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const keyword = input.value.trim();

        if (keyword === '') {
            suggestionsBox.innerHTML = '';

            return;
        }

        debounceTimer = setTimeout(async () => {
            try {
                const response = await requestJson(`${searchUrl}?q=${encodeURIComponent(keyword)}`);

                if (!response.ok) {
                    return;
                }

                renderSuggestions(await response.json());
            } catch {
                suggestionsBox.innerHTML = '';
            }
        }, 250);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            addTag(input.value);
        }
    });

    document.addEventListener('click', (event) => {
        if (!container.contains(event.target)) {
            suggestionsBox.innerHTML = '';
        }
    });

    document.addEventListener('tag:deleted', (event) => {
        const deletedName = event.detail?.name;

        if (!deletedName || !selected.includes(deletedName)) {
            return;
        }

        selected = selected.filter((name) => name !== deletedName);
        render();
    });

    document.addEventListener('tag:renamed', (event) => {
        const { previousName, name } = event.detail ?? {};

        if (!previousName || !name) {
            return;
        }

        const index = selected.indexOf(previousName);

        if (index === -1) {
            return;
        }

        if (selected.includes(name)) {
            selected.splice(index, 1);
        } else {
            selected[index] = name;
        }

        render();
    });

    render();
}

/**
 * タグ管理モーダル(記事の作成・編集フォーム)を初期化する。
 * タグの新規登録・編集・削除をモーダル内で完結させ、記事フォームのタグ入力欄へ名前の変更・削除を通知する。
 */
function initTagManagerModal() {
    const modal = document.getElementById('tag-manager-modal');

    if (!modal) {
        return;
    }

    const input = document.getElementById('tag-manager-input');

    initManagerModal(modal, {
        listContainer: document.getElementById('tag-manager-list'),
        submitButton: document.getElementById('tag-manager-submit'),
        errorBox: document.getElementById('tag-manager-error'),
        focusTarget: input,
        submitOnEnter: [input],
        messages: {
            deleteFailed: t('タグの削除に失敗しました。'),
            saveFailed: t('タグの保存に失敗しました。'),
        },
        itemLabel: (tag) => tag.tag_name,
        readForm() {
            const name = input.value.trim();

            return name === '' ? null : { tag_name: name };
        },
        fillForm(tag) {
            input.value = tag.tag_name;
        },
        clearForm() {
            input.value = '';
        },
        renderItem(tag, { edit, remove }) {
            const chip = document.createElement('span');
            chip.className = 'badge text-bg-light border text-dark d-inline-flex align-items-center gap-2 p-2';
            chip.setAttribute('role', 'button');
            chip.addEventListener('click', edit);

            const name = document.createElement('span');
            name.textContent = tag.tag_name;
            chip.appendChild(name);

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'btn-close';
            deleteButton.style.fontSize = '0.6rem';
            deleteButton.setAttribute('aria-label', t('タグを削除'));
            deleteButton.addEventListener('click', (event) => {
                event.stopPropagation();
                remove();
            });
            chip.appendChild(deleteButton);

            return chip;
        },
        onSaved(saved, previous, payload) {
            if (previous && previous.tag_name !== payload.tag_name) {
                document.dispatchEvent(new CustomEvent('tag:renamed', { detail: { previousName: previous.tag_name, name: payload.tag_name } }));
            }
        },
        onDeleted(tag) {
            document.dispatchEvent(new CustomEvent('tag:deleted', { detail: { name: tag.tag_name } }));
        },
    });
}

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
 */
function initManagerModal(modal, {
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

        listContainer.innerHTML = '';
        (await response.json()).forEach((item) => {
            listContainer.appendChild(renderItem(item, { edit: () => edit(item), remove: () => remove(item) }));
        });
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
}

/**
 * 記事本文用のリッチテキストエディタ(Quill)を初期化する。
 * 画像はツールバーからアップロードし、返却されたURLを本文に埋め込む。
 */
function initContentEditor() {
    const editorElement = document.getElementById('content-editor');

    if (!editorElement) {
        return;
    }

    const hiddenInput = document.getElementById('content-input');
    const form = hiddenInput.closest('form');
    const uploadUrl = editorElement.dataset.uploadUrl;

    const quill = new Quill(editorElement, {
        theme: 'snow',
        modules: {
            toolbar: {
                container: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link', 'image'],
                    ['clean'],
                ],
                handlers: {
                    image: handleImageUpload,
                },
            },
        },
    });

    if (hiddenInput.value) {
        quill.clipboard.dangerouslyPasteHTML(hiddenInput.value);
    }

    function handleImageUpload() {
        const fileInput = document.createElement('input');
        fileInput.setAttribute('type', 'file');
        fileInput.setAttribute('accept', 'image/*');
        fileInput.click();

        fileInput.onchange = async () => {
            const file = fileInput.files[0];

            if (!file) {
                return;
            }

            const formData = new FormData();
            formData.append('image', file);

            const response = await requestJson(uploadUrl, { method: 'POST', body: formData });

            if (!response.ok) {
                window.alert(t('画像のアップロードに失敗しました。'));

                return;
            }

            const data = await response.json();
            const range = quill.getSelection(true);
            quill.insertEmbed(range.index, 'image', data.url);
        };
    }

    form.addEventListener('submit', () => {
        hiddenInput.value = quill.root.innerHTML;
    });
}

/**
 * 呼び出しコンテンツ(call_contents)の行入力UI(サイト設定の登録・編集フォーム)を初期化する。
 * 「+」で行を追加、「−」で行を削除する。
 */
function initCallContentRows() {
    const container = document.getElementById('call-content-rows');

    if (!container) {
        return;
    }

    const constraints = JSON.parse(container.dataset.callTypeConstraints || '{}');

    initEditableRows(container, {
        rowSelector: '[data-role="call-content-row"]',
        addButton: document.getElementById('call-content-add'),
        template: document.getElementById('call-content-row-template'),
        onBindRow(row) {
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
        },
    });
}

/**
 * 行の追加・削除・ドラッグでの並び替えだけを行う汎用の繰り返し入力(SNSリンク・スキルなど)を初期化する。
 * data-role="repeater" の中に、行のコンテナ(repeater-rows、data-next-index に次の行番号)・
 * 追加ボタン(repeater-add)・行のテンプレート(repeater-template、行番号は __INDEX__)を置く。
 * 各行(repeater-row)にはドラッグハンドル(drag-handle)・並び順の隠しinput(sort-order)・削除ボタン(remove-row)を置く。
 * repeater に data-max-rows があれば、行数がその件数に達したときに追加ボタンを無効にする。
 */
function initRepeaterRows() {
    document.querySelectorAll('[data-role="repeater"]').forEach((repeater) => {
        initEditableRows(repeater.querySelector('[data-role="repeater-rows"]'), {
            rowSelector: '[data-role="repeater-row"]',
            addButton: repeater.querySelector('[data-role="repeater-add"]'),
            template: repeater.querySelector('[data-role="repeater-template"]'),
            maxRows: Number(repeater.dataset.maxRows || '0'),
        });
    });
}

/**
 * 行の追加・削除・ドラッグでの並び替えができる繰り返し入力の共通処理。
 * 追加ボタンでテンプレート(行番号は __INDEX__。コンテナの data-next-index から採番する)の行をコンテナの末尾に追加し、
 * 各行の削除ボタン(removeSelector)で行を削除する。maxRows(0 なら無制限)に達したら追加ボタンを無効にする。
 * 行ごとの独自の初期化は onBindRow、削除時の後始末は onRemoveRow で行う。
 * 返り値の updateSortOrders で、各行の並び順の隠しinputを画面上の順番に設定し直せる。
 */
function initEditableRows(container, {
    rowSelector,
    addButton,
    template,
    removeSelector = '[data-role="remove-row"]',
    maxRows = 0,
    onBindRow = () => {},
    onRemoveRow = () => {},
}) {
    let nextIndex = Number(container.dataset.nextIndex || '0');
    const { bindRow: bindSortableRow, updateSortOrders } = initSortableRows(container, rowSelector);

    function updateAddButton() {
        if (maxRows > 0) {
            addButton.disabled = container.querySelectorAll(rowSelector).length >= maxRows;
        }
    }

    function bindRow(row) {
        bindSortableRow(row);
        onBindRow(row);

        row.querySelector(removeSelector).addEventListener('click', () => {
            onRemoveRow(row);
            row.remove();
            updateSortOrders();
            updateAddButton();
        });
    }

    container.querySelectorAll(rowSelector).forEach(bindRow);

    addButton.addEventListener('click', () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex)).trim();
        const row = wrapper.firstElementChild;

        container.appendChild(row);
        bindRow(row);
        nextIndex += 1;
        updateSortOrders();
        updateAddButton();
    });

    updateSortOrders();
    updateAddButton();

    return { updateSortOrders };
}

/**
 * コンテナ内の行をハンドル(data-role="drag-handle")のドラッグで並び替えられるようにする。
 * 並び替えるたびに、各行の隠しinput(data-role="sort-order")へ画面上の順番(0始まり)を設定する
 * (隠しinputを持たない行は、DOM の順番そのものを送信に使う想定で何もしない)。
 * 返り値の bindRow で行ごとにドラッグ操作を登録し、行の追加・削除後は updateSortOrders を呼ぶ。
 */
function initSortableRows(container, rowSelector) {
    let draggingRow = null;

    function updateSortOrders() {
        container.querySelectorAll(rowSelector).forEach((row, index) => {
            const input = row.querySelector('[data-role="sort-order"]');

            if (input) {
                input.value = String(index);
            }
        });
    }

    function getRowAfterElement(y) {
        const rows = [...container.querySelectorAll(`${rowSelector}:not(.dragging)`)];

        return rows.reduce(
            (closest, row) => {
                const box = row.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;

                if (offset < 0 && offset > closest.offset) {
                    return { offset, element: row };
                }

                return closest;
            },
            { offset: Number.NEGATIVE_INFINITY, element: null }
        ).element;
    }

    function bindRow(row) {
        row.querySelector('[data-role="drag-handle"]').addEventListener('mousedown', () => {
            row.draggable = true;
        });

        row.addEventListener('dragstart', () => {
            draggingRow = row;
            row.classList.add('dragging');
        });

        row.addEventListener('dragend', () => {
            row.draggable = false;
            row.classList.remove('dragging');
            draggingRow = null;
            updateSortOrders();
        });
    }

    container.addEventListener('dragover', (event) => {
        if (!draggingRow) {
            return;
        }

        event.preventDefault();

        const afterElement = getRowAfterElement(event.clientY);

        if (afterElement == null) {
            container.appendChild(draggingRow);
        } else {
            container.insertBefore(draggingRow, afterElement);
        }
    });

    return { bindRow, updateSortOrders };
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
        option.dataset.modelName = relation.model_name;
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
function initContentModelRelationManagerModal() {
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

/**
 * 固定ページの詳細(single_page_details)の行入力UI(固定ページの登録・編集フォーム)を初期化する。
 * 「+」で行を追加、「×」で行を削除し、左側のハンドルをドラッグして並び替えできる。
 * 各行の本文はリッチテキストエディタ(Quill)で編集し、フォーム送信時に隠しtextareaへ反映する。
 * コンテナに data-max-rows があれば、行数がその件数に達したときに追加ボタンを無効にする。
 */
function initSinglePageDetailRows() {
    const container = document.getElementById('single-page-detail-rows');

    if (!container) {
        return;
    }

    const editors = new Map();

    function initEditor(row) {
        const editorElement = row.querySelector('[data-role="content-editor"]');
        const hiddenInput = row.querySelector('[data-role="content-input"]');

        const quill = new Quill(editorElement, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link'],
                    ['clean'],
                ],
            },
        });

        if (hiddenInput.value) {
            quill.clipboard.dangerouslyPasteHTML(hiddenInput.value);
        }

        editors.set(row, { quill, hiddenInput });
    }

    const { updateSortOrders } = initEditableRows(container, {
        rowSelector: '[data-role="detail-row"]',
        addButton: document.getElementById('single-page-detail-add'),
        template: document.getElementById('single-page-detail-row-template'),
        removeSelector: '[data-role="remove-detail"]',
        maxRows: Number(container.dataset.maxRows || '0'),
        onBindRow: initEditor,
        onRemoveRow: (row) => editors.delete(row),
    });

    container.closest('form').addEventListener('submit', () => {
        editors.forEach(({ quill, hiddenInput }) => {
            hiddenInput.value = quill.root.innerHTML;
        });
        updateSortOrders();
    });
}

/**
 * 固定ページ一覧(single_pages)の並び替えUIを初期化する。
 * ハンドルをドラッグして行を並び替えると、隠しinput(order[])のDOM順が変わり、
 * 「並び替えを保存」ボタンで並び替え用フォーム(single-page-reorder-form)に送信される。
 */
function initSinglePageReorder() {
    const table = document.getElementById('single-page-reorder-rows');

    if (!table) {
        return;
    }

    const tbody = table.querySelector('tbody');
    const { bindRow } = initSortableRows(tbody, '[data-role="single-page-row"]');

    tbody.querySelectorAll('[data-role="single-page-row"]').forEach(bindRow);
}

/**
 * 画像アップロード用のドロップゾーン(data-role="image-dropzone"、Blade の <x-admin.image-dropzone>)を初期化する。
 * クリック・Enter/Space でファイル選択を開き、ドロップしたファイルはファイル入力へ移して change を発生させる。
 * 選択した画像は枠内にプレビューし、選択解除ボタン(image-dropzone-remove)で選択を取り消す。
 * 切り抜きUI(data-role="image-cropper")の中のドロップゾーンは、選択後の表示を initImageCroppers() に任せる。
 * リピーターで後から追加される行にも対応するため、各イベントは document で受け取る。
 */
function initImageDropzones() {
    const dropzoneOf = (event) => event.target.closest?.('[data-role="image-dropzone"]');
    const inputOf = (dropzone) => dropzone.querySelector('[data-role="image-dropzone-input"]');

    document.addEventListener('click', (event) => {
        const dropzone = dropzoneOf(event);

        if (!dropzone) {
            return;
        }

        if (event.target.closest('[data-role="image-dropzone-remove"]')) {
            inputOf(dropzone).value = '';
            setDropzonePreview(dropzone, null);

            return;
        }

        // ファイル入力自体のクリック(下の input.click() によるもの)で、ファイル選択を二重に開かないようにする
        if (!event.target.closest('[data-role="image-dropzone-input"]')) {
            inputOf(dropzone).click();
        }
    });

    document.addEventListener('keydown', (event) => {
        const dropzone = dropzoneOf(event);

        if (dropzone && event.target === dropzone && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            inputOf(dropzone).click();
        }
    });

    document.addEventListener('dragover', (event) => {
        const dropzone = dropzoneOf(event);

        if (dropzone) {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        }
    });

    document.addEventListener('dragleave', (event) => {
        const dropzone = dropzoneOf(event);

        if (dropzone && !dropzone.contains(event.relatedTarget)) {
            dropzone.classList.remove('is-dragover');
        }
    });

    document.addEventListener('drop', (event) => {
        const dropzone = dropzoneOf(event);

        if (!dropzone) {
            return;
        }

        event.preventDefault();
        dropzone.classList.remove('is-dragover');

        if (event.dataTransfer.files.length) {
            const input = inputOf(dropzone);

            input.files = event.dataTransfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    document.addEventListener('change', (event) => {
        const input = event.target.closest('[data-role="image-dropzone-input"]');
        const file = input?.files?.[0];

        if (!input || input.closest('[data-role="image-cropper"]') || !file || !file.type.startsWith('image/')) {
            return;
        }

        const reader = new FileReader();
        reader.onload = () => setDropzonePreview(input.closest('[data-role="image-dropzone"]'), reader.result);
        reader.readAsDataURL(file);
    });
}

/**
 * ドロップゾーンの表示を、画像のプレビュー(src。null なら「クリックまたはドラッグ&ドロップ」の案内)に切り替える。
 */
function setDropzonePreview(dropzone, src) {
    dropzone.querySelector('[data-role="image-dropzone-image"]').src = src || '';
    dropzone.querySelector('[data-role="image-dropzone-preview"]').style.display = src ? '' : 'none';
    dropzone.querySelector('[data-role="image-dropzone-placeholder"]').style.display = src ? 'none' : '';
}

/**
 * 切り抜き範囲を指定して画像をアップロードするUI(トップスライダー画像・ユーザーのアイコン画像のフォーム)を初期化する。
 * data-role="image-cropper" の中のドロップゾーン(<x-admin.image-dropzone>。クリック・ドラッグ&ドロップの操作は
 * initImageDropzones() が受け持つ)で画像を選ぶと、切り抜き用のモーダル
 * (admin.partials._image_cropper_modal)を開いて Cropper.js で範囲と画像の拡大・縮小を調整させる。
 * 「決定」で枠の範囲(元画像のピクセル基準)を隠しinput(image-cropper-x/y/width/height)へ設定し、
 * 切り抜いた結果をドロップゾーン内にプレビューする。決定後は「切り抜きを編集」(image-cropper-edit)で開き直せる。
 * 最初の選択でモーダルを閉じた場合は、ファイルの選択を取り消す。
 * 枠の比率は保存サイズ(data-output-width / data-output-height)から決める。
 * リピーターで後から追加される行にも対応するため、change・click イベントは document で受け取る。
 */
function initImageCroppers() {
    const modalElement = document.getElementById('image-cropper-modal');

    if (!modalElement) {
        return;
    }

    const modal = Modal.getOrCreateInstance(modalElement);
    const modalImage = modalElement.querySelector('[data-role="image-cropper-modal-image"]');
    const help = modalElement.querySelector('[data-role="image-cropper-modal-help"]');
    const fieldKeys = ['x', 'y', 'width', 'height'];

    // 入力欄ごとに、選んだ画像(data URL)と決定済みの切り抜き範囲(未決定なら null)を覚えておく
    const selections = new WeakMap();
    let cropper = null;
    let currentWrapper = null;
    // 開いているモーダルで編集中の選択(開いている間に同じ欄で別の画像を選び直すと、別の選択に置き換わる)
    let currentSelection = null;
    let applied = false;
    // 閉じている途中(背景のフェード中)に次の画像を選んだ場合は、閉じ終わってから開く
    let hiding = false;
    let pendingWrapper = null;

    function outputSize(wrapper) {
        return { width: Number(wrapper.dataset.outputWidth), height: Number(wrapper.dataset.outputHeight) };
    }

    function field(wrapper, key) {
        return wrapper.querySelector(`[data-role="image-cropper-${key}"]`);
    }

    function dropzone(wrapper) {
        return wrapper.querySelector('[data-role="image-dropzone"]');
    }

    // ファイルの選択を取り消し、保存済みの画像(あれば)の表示に戻す
    function clearSelection(wrapper) {
        selections.delete(wrapper);
        wrapper.querySelector('[data-role="image-dropzone-input"]').value = '';
        fieldKeys.forEach((key) => {
            field(wrapper, key).value = '';
        });
        setDropzonePreview(dropzone(wrapper), wrapper.querySelector('[data-role="image-dropzone-image"]').dataset.originalSrc || null);
        wrapper.querySelector('[data-role="image-cropper-edit"]').style.display = 'none';
    }

    function open(wrapper) {
        if (hiding) {
            pendingWrapper = wrapper;

            return;
        }

        const { width, height } = outputSize(wrapper);

        currentWrapper = wrapper;
        currentSelection = selections.get(wrapper);
        applied = false;
        modalImage.src = selections.get(wrapper).src;
        help.textContent = help.dataset.template.replace(':size', `${width}×${height}`);
        modal.show();
    }

    modalElement.addEventListener('shown.bs.modal', () => {
        const { width, height } = outputSize(currentWrapper);

        cropper = new Cropper(modalImage, {
            aspectRatio: width / height,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            data: selections.get(currentWrapper).data ?? undefined,
        });
    });

    modalElement.addEventListener('hide.bs.modal', () => {
        hiding = true;
    });

    modalElement.addEventListener('hidden.bs.modal', () => {
        cropper?.destroy();
        cropper = null;

        // 一度も決定していない画像のままモーダルを閉じた場合は、選択自体を取り消す
        // (閉じている間に選び直された新しい画像の選択は残す)
        if (!applied && !currentSelection.data && selections.get(currentWrapper) === currentSelection) {
            clearSelection(currentWrapper);
        }

        currentWrapper = null;
        currentSelection = null;
        hiding = false;

        if (pendingWrapper) {
            const wrapper = pendingWrapper;

            pendingWrapper = null;
            open(wrapper);
        }
    });

    modalElement.querySelector('[data-role="image-cropper-modal-apply"]').addEventListener('click', () => {
        const wrapper = currentWrapper;
        const { width, height } = outputSize(wrapper);
        const data = cropper.getData(true);

        // 整数に丸めると比率が 1px ずれることがあるため、高さを幅と保存サイズの比率から求め直す
        data.height = Math.round(data.width * height / width);

        selections.get(wrapper).data = data;
        fieldKeys.forEach((key) => {
            field(wrapper, key).value = String(data[key]);
        });

        setDropzonePreview(dropzone(wrapper), cropper.getCroppedCanvas({ maxWidth: 960, maxHeight: 960 }).toDataURL());
        wrapper.querySelector('[data-role="image-cropper-edit"]').style.display = '';

        applied = true;
        modal.hide();
    });

    modalElement.addEventListener('click', (event) => {
        const button = event.target.closest('[data-role="image-cropper-modal-zoom"], [data-role="image-cropper-modal-reset"]');

        if (!button || !cropper) {
            return;
        }

        if (button.dataset.role === 'image-cropper-modal-zoom') {
            cropper.zoom(Number(button.dataset.zoomRatio));
        } else {
            cropper.reset();
        }
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-role="image-cropper-edit"]');
        const wrapper = button?.closest('[data-role="image-cropper"]');

        if (wrapper && selections.has(wrapper)) {
            open(wrapper);
        }
    });

    document.addEventListener('change', (event) => {
        const input = event.target.closest('[data-role="image-cropper"] [data-role="image-dropzone-input"]');

        if (!input) {
            return;
        }

        const wrapper = input.closest('[data-role="image-cropper"]');
        const file = input.files?.[0];

        if (!file || !file.type.startsWith('image/')) {
            clearSelection(wrapper);

            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            selections.set(wrapper, { src: reader.result, data: null });
            fieldKeys.forEach((key) => {
                field(wrapper, key).value = '';
            });
            open(wrapper);
        };
        reader.readAsDataURL(file);
    });
}

/**
 * 日時入力欄(公開開始・公開終了など)にDateTimePicker(flatpickr)を、
 * 日付入力欄(一覧の検索条件など)にDatePicker(flatpickr)を適用する。
 * 送信値はバリデーション(date_format:Y-m-d H:i / Y-m-d)に合わせ、画面上の表示のみ Y/m/d H:i / Y/m/d とする。
 */
function initDateTimePickers() {
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
function initPathPreview() {
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
function initArticleApprovalControls() {
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
 * Q&A の作成・編集フォームを初期化する。
 * 形式(type)のラジオで簡易版/分岐ありの入力欄(fieldset[data-type-section])を切り替え、
 * 選んでいない側は disabled にして送信しない。分岐ありはフローチャート(上→下のツリー)で、
 * 質問ノード(question-block)の下に回答ノード(answer-row)を追加・削除し、回答ノードの下に分岐先の質問ノードを追加・削除する。
 * 入力名は各ノードの data-name を接頭辞にして組み立てる。
 * 分岐ありの欄に data-max-answers があれば、回答がその件数に達した質問ノードの「回答を追加」を無効にする。
 */
function initQuestionAnswerForm() {
    const form = document.querySelector('[data-role="question-answer-form"]');

    if (!form) {
        return;
    }

    const typeInputs = form.querySelectorAll('input[name="type"]');
    const sections = form.querySelectorAll('[data-type-section]');

    function applyType() {
        const checked = form.querySelector('input[name="type"]:checked');

        sections.forEach((section) => {
            const active = checked !== null && section.dataset.typeSection === checked.value;
            section.hidden = !active;
            section.disabled = !active;
        });
    }

    typeInputs.forEach((input) => input.addEventListener('change', applyType));
    applyType();

    const maxAnswers = Number(form.querySelector('[data-max-answers]')?.dataset.maxAnswers || '0');

    function updateAddAnswerButtons() {
        if (maxAnswers <= 0) {
            return;
        }

        form.querySelectorAll('[data-role="question-block"]').forEach((questionBlock) => {
            const rows = questionBlock.querySelector(':scope > [data-role="answer-rows"]');
            const button = rows.querySelector(':scope > [data-role="answer-add-slot"] [data-role="add-answer"]');

            button.disabled = rows.querySelectorAll(':scope > [data-role="answer-row"]').length >= maxAnswers;
        });
    }

    const answerTemplate = form.querySelector('[data-role="answer-template"]');
    const questionTemplate = form.querySelector('[data-role="question-template"]');

    // バリデーションエラーで戻ったときに既存の行と入力名が重ならないよう、時刻を含めた番号にする
    let counter = 0;
    const nextIndex = () => `n${Date.now()}_${counter++}`;

    function render(template, name) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__NAME__', name).trim();

        return wrapper.firstElementChild;
    }

    function addAnswer(questionBlock) {
        const rows = questionBlock.querySelector(':scope > [data-role="answer-rows"]');
        const addSlot = rows.querySelector(':scope > [data-role="answer-add-slot"]');

        rows.insertBefore(render(answerTemplate, `${questionBlock.dataset.name}[answers][${nextIndex()}]`), addSlot);
    }

    form.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-role]');

        if (!button) {
            return;
        }

        switch (button.dataset.role) {
            case 'add-answer':
                addAnswer(button.closest('[data-role="question-block"]'));
                break;
            case 'remove-answer':
                button.closest('[data-role="answer-row"]').remove();
                break;
            case 'add-branch': {
                const row = button.closest('[data-role="answer-row"]');
                const questionBlock = render(questionTemplate, `${row.dataset.name}[question]`);

                row.querySelector(':scope > [data-role="branch-container"]').appendChild(questionBlock);
                addAnswer(questionBlock);
                button.classList.add('d-none');
                break;
            }
            case 'remove-branch': {
                const row = button.closest('[data-role="answer-row"]');

                button.closest('[data-role="question-block"]').remove();
                row.querySelector(':scope > .qa-node [data-role="add-branch"]').classList.remove('d-none');
                break;
            }
        }

        updateAddAnswerButtons();
    });

    updateAddAnswerButtons();
}

/**
 * Q&A のフローチャート(.qa-flow)が横にはみ出す場合、最初の質問が見えるよう横スクロールを中央に合わせる。
 */
function initQuestionAnswerFlows() {
    document.querySelectorAll('.qa-flow').forEach((flow) => {
        flow.scrollLeft = (flow.scrollWidth - flow.clientWidth) / 2;
    });
}
