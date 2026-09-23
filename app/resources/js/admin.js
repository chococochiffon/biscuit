import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import Quill from 'quill';

document.addEventListener('DOMContentLoaded', () => {
    initTagSelector();
    initTagManagerModal();
    initContentEditor();
    initCallContentRows();
    initContentModelRelationManagerModal();
    initSinglePageDetailRows();
    initSinglePageReorder();
    initImageDropzones();
});

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
            removeButton.setAttribute('aria-label', 'タグを削除');
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
                const response = await fetch(`${searchUrl}?q=${encodeURIComponent(keyword)}`, {
                    headers: { Accept: 'application/json' },
                });

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
 * タグの新規登録・編集・削除をモーダル内で完結させる。
 */
function initTagManagerModal() {
    const modal = document.getElementById('tag-manager-modal');

    if (!modal) {
        return;
    }

    const listContainer = document.getElementById('tag-manager-list');
    const input = document.getElementById('tag-manager-input');
    const submitButton = document.getElementById('tag-manager-submit');
    const errorBox = document.getElementById('tag-manager-error');
    const indexUrl = listContainer.dataset.indexUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    let editingTagId = null;
    let editingTagName = null;

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
    }

    function clearError() {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
    }

    function resetForm() {
        editingTagId = null;
        editingTagName = null;
        input.value = '';
        submitButton.textContent = '登録';
    }

    function renderTags(tags) {
        listContainer.innerHTML = '';

        tags.forEach((tag) => {
            const chip = document.createElement('span');
            chip.className = 'badge text-bg-light border text-dark d-inline-flex align-items-center gap-2 p-2';
            chip.setAttribute('role', 'button');

            const name = document.createElement('span');
            name.textContent = tag.tag_name;
            chip.appendChild(name);

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'btn-close';
            deleteButton.style.fontSize = '0.6rem';
            deleteButton.setAttribute('aria-label', 'タグを削除');
            chip.appendChild(deleteButton);

            chip.addEventListener('click', () => {
                editingTagId = tag.id;
                editingTagName = tag.tag_name;
                input.value = tag.tag_name;
                submitButton.textContent = '更新';
                clearError();
                input.focus();
            });

            deleteButton.addEventListener('click', async (event) => {
                event.stopPropagation();

                if (!window.confirm(`「${tag.tag_name}」を削除してよろしいですか?`)) {
                    return;
                }

                await deleteTag(tag.id, tag.tag_name);
            });

            listContainer.appendChild(chip);
        });
    }

    async function fetchTags() {
        const response = await fetch(indexUrl, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        renderTags(await response.json());
    }

    async function deleteTag(id, name) {
        const response = await fetch(`${indexUrl}/${id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });

        if (!response.ok) {
            showError('タグの削除に失敗しました。');

            return;
        }

        if (editingTagId === id) {
            resetForm();
        }

        document.dispatchEvent(new CustomEvent('tag:deleted', { detail: { name } }));

        await fetchTags();
    }

    async function submitTag() {
        const name = input.value.trim();

        if (name === '') {
            return;
        }

        clearError();

        const isEditing = editingTagId !== null;
        const previousName = editingTagName;
        const url = isEditing ? `${indexUrl}/${editingTagId}` : indexUrl;
        const method = isEditing ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ tag_name: name }),
        });

        if (!response.ok) {
            if (response.status === 422) {
                const data = await response.json();
                showError(Object.values(data.errors ?? {}).flat().join(' '));
            } else {
                showError('タグの保存に失敗しました。');
            }

            return;
        }

        if (isEditing && previousName !== name) {
            document.dispatchEvent(new CustomEvent('tag:renamed', { detail: { previousName, name } }));
        }

        resetForm();
        await fetchTags();
    }

    submitButton.addEventListener('click', submitTag);

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            submitTag();
        }
    });

    modal.addEventListener('show.bs.modal', () => {
        clearError();
        fetchTags();
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
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

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

            const response = await fetch(uploadUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: formData,
            });

            if (!response.ok) {
                window.alert('画像のアップロードに失敗しました。');

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

    const addButton = document.getElementById('call-content-add');
    const template = document.getElementById('call-content-row-template');
    const constraints = JSON.parse(container.dataset.callTypeConstraints || '{}');

    let nextIndex = Number(container.dataset.nextIndex || '0');

    function bindRow(row) {
        row.querySelector('[data-role="remove-row"]').addEventListener('click', () => row.remove());

        const callTypeSelect = row.querySelector('[data-role="call-type-select"]');
        const relationSelect = row.querySelector('[data-role="content-model-relation-select"]');
        const relationError = row.querySelector('[data-role="content-model-relation-error"]');
        const placeSelect = row.querySelector('[data-role="place-select"]');
        const placeError = row.querySelector('[data-role="place-error"]');

        callTypeSelect.addEventListener('change', () => applyCallTypeConstraints(row, constraints));
        relationSelect.addEventListener('change', () => {
            setFieldError(relationSelect, relationError, null);
            applyPlaceConstraints(row, constraints[callTypeSelect.value]);
        });
        placeSelect.addEventListener('change', () => setFieldError(placeSelect, placeError, null));
        applyCallTypeConstraints(row, constraints);
    }

    container.querySelectorAll('[data-role="call-content-row"]').forEach(bindRow);

    addButton.addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;

        container.appendChild(row);
        bindRow(row);
        nextIndex += 1;
    });
}

/**
 * 呼び出し方(call_type)の選択に応じて、同じ行のデータ種別(モデル名)/表示箇所の選択肢を絞り込み、
 * 表示件数を固定(読み取り専用・値1)にするかどうかを切り替える。
 * 既存の選択値が新しい呼び出し方では選択できなくなった場合は、値をクリアした上でその行にエラーを表示する。
 */
function applyCallTypeConstraints(row, constraints) {
    const callTypeSelect = row.querySelector('[data-role="call-type-select"]');
    const relationSelect = row.querySelector('[data-role="content-model-relation-select"]');
    const relationError = row.querySelector('[data-role="content-model-relation-error"]');
    const viewCountInput = row.querySelector('[data-role="view-count-input"]');

    const rule = constraints[callTypeSelect.value];

    if (!rule) {
        return;
    }

    const previousRelationValue = relationSelect.value;

    Array.from(relationSelect.options).forEach((option) => {
        if (!option.value) {
            return;
        }

        const allowed = rule.modelNames.includes(option.dataset.modelName);
        option.hidden = !allowed;
        option.disabled = !allowed;
    });

    if (previousRelationValue && relationSelect.selectedOptions[0]?.hidden) {
        relationSelect.value = '';
        setFieldError(relationSelect, relationError, '選択した呼び出し方ではこのデータ種別は選択できなくなりました。データ種別を選び直してください。');
    } else {
        setFieldError(relationSelect, relationError, null);
    }

    applyPlaceConstraints(row, rule);

    if (rule.fixedViewCount) {
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
 * 表示箇所(place)の選択肢を絞り込む。データ種別(モデル名)が選択済みの場合は
 * 呼び出し方(call_type)・モデル名の組み合わせで許可された表示箇所に限定し、
 * 未選択の場合は呼び出し方で選択可能な表示箇所の和集合を候補にする。
 * 既存の選択値が選択できなくなった場合は、値をクリアした上でその行にエラーを表示する。
 */
function applyPlaceConstraints(row, rule) {
    const relationSelect = row.querySelector('[data-role="content-model-relation-select"]');
    const placeSelect = row.querySelector('[data-role="place-select"]');
    const placeError = row.querySelector('[data-role="place-error"]');

    if (!rule) {
        return;
    }

    const selectedModelName = relationSelect.selectedOptions[0]?.dataset.modelName;
    const allowedPlaces = selectedModelName ? (rule.placesByModelName[selectedModelName] ?? []) : rule.places;
    const previousPlaceValue = placeSelect.value;

    Array.from(placeSelect.options).forEach((option) => {
        if (!option.value) {
            return;
        }

        const allowed = allowedPlaces.includes(Number(option.value));
        option.hidden = !allowed;
        option.disabled = !allowed;
    });

    if (previousPlaceValue && placeSelect.selectedOptions[0]?.hidden) {
        placeSelect.value = '';
        setFieldError(placeSelect, placeError, '選択した呼び出し方・データ種別ではこの表示箇所は選択できなくなりました。表示箇所を選び直してください。');
    } else {
        setFieldError(placeSelect, placeError, null);
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
 * 既存の呼び出しコンテンツ行すべてに、呼び出し方(call_type)による選択肢の絞り込みを再適用する。
 * データ種別紐付けをその場で追加/更新した直後に、絞り込み状態を最新化するために呼び出す。
 */
function reapplyAllCallContentConstraints() {
    const container = document.getElementById('call-content-rows');

    if (!container) {
        return;
    }

    const constraints = JSON.parse(container.dataset.callTypeConstraints || '{}');
    container.querySelectorAll('[data-role="call-content-row"]').forEach((row) => applyCallTypeConstraints(row, constraints));
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

    const listContainer = document.getElementById('content-model-relation-manager-list');
    const contentTypeSelect = document.getElementById('content-model-relation-manager-content-type');
    const modelNameInput = document.getElementById('content-model-relation-manager-model-name');
    const tableNameSelect = document.getElementById('content-model-relation-manager-table-name');
    const submitButton = document.getElementById('content-model-relation-manager-submit');
    const errorBox = document.getElementById('content-model-relation-manager-error');
    const indexUrl = listContainer.dataset.indexUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    let editingId = null;

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
    }

    function clearError() {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
    }

    function resetForm() {
        editingId = null;
        contentTypeSelect.selectedIndex = 0;
        modelNameInput.value = '';
        tableNameSelect.value = '';
        submitButton.textContent = '登録';
    }

    function ensureTableNameOption(tableName) {
        if (!tableName || tableNameSelect.querySelector(`option[value="${tableName}"]`)) {
            return;
        }

        const option = document.createElement('option');
        option.value = tableName;
        option.textContent = tableName;
        tableNameSelect.appendChild(option);
    }

    function renderList(relations) {
        listContainer.innerHTML = '';

        relations.forEach((relation) => {
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
            editButton.setAttribute('aria-label', '編集');
            editButton.addEventListener('click', () => {
                editingId = relation.id;
                contentTypeSelect.value = String(relation.content_type);
                modelNameInput.value = relation.model_name;
                ensureTableNameOption(relation.table_name);
                tableNameSelect.value = relation.table_name;
                submitButton.textContent = '更新';
                clearError();
                modelNameInput.focus();
            });
            actions.appendChild(editButton);

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'btn btn-sm btn-outline-danger';
            deleteButton.innerHTML = '<i class="bi bi-trash"></i>';
            deleteButton.setAttribute('aria-label', '削除');
            deleteButton.addEventListener('click', async () => {
                if (!window.confirm(`「${relation.content_type_label} / ${relation.model_name}」を削除してよろしいですか?`)) {
                    return;
                }

                await deleteRelation(relation);
            });
            actions.appendChild(deleteButton);

            item.appendChild(actions);
            listContainer.appendChild(item);
        });
    }

    async function fetchList() {
        const response = await fetch(indexUrl, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        renderList(await response.json());
    }

    async function deleteRelation(relation) {
        const response = await fetch(`${indexUrl}/${relation.id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });

        if (!response.ok) {
            if (response.status === 422) {
                const data = await response.json();
                showError(data.message ?? 'このデータ種別の紐付けは使用されているため削除できません。');
            } else {
                showError('データ種別の紐付けの削除に失敗しました。');
            }

            return;
        }

        if (editingId === relation.id) {
            resetForm();
        }

        removeContentModelRelationOption(relation.id);
        await fetchList();
    }

    async function submitRelation() {
        const contentType = contentTypeSelect.value;
        const modelName = modelNameInput.value.trim();
        const tableName = tableNameSelect.value;

        if (modelName === '' || tableName === '') {
            return;
        }

        clearError();

        const isEditing = editingId !== null;
        const url = isEditing ? `${indexUrl}/${editingId}` : indexUrl;
        const method = isEditing ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ content_type: contentType, model_name: modelName, table_name: tableName }),
        });

        if (!response.ok) {
            if (response.status === 422) {
                const data = await response.json();
                showError(Object.values(data.errors ?? {}).flat().join(' '));
            } else {
                showError('データ種別の紐付けの保存に失敗しました。');
            }

            return;
        }

        const saved = await response.json();
        upsertContentModelRelationOption(saved);
        resetForm();
        await fetchList();
    }

    submitButton.addEventListener('click', submitRelation);

    modal.addEventListener('show.bs.modal', () => {
        clearError();
        fetchList();
    });

    modal.addEventListener('hidden.bs.modal', resetForm);
}

/**
 * 固定ページの詳細(single_page_details)の行入力UI(固定ページの登録・編集フォーム)を初期化する。
 * 「+」で行を追加、「×」で行を削除し、左側のハンドルをドラッグして並び替えできる。
 * 各行の本文はリッチテキストエディタ(Quill)で編集し、フォーム送信時に隠しtextareaへ反映する。
 */
function initSinglePageDetailRows() {
    const container = document.getElementById('single-page-detail-rows');

    if (!container) {
        return;
    }

    const addButton = document.getElementById('single-page-detail-add');
    const template = document.getElementById('single-page-detail-row-template');
    const form = container.closest('form');

    let nextIndex = Number(container.dataset.nextIndex || '0');
    const editors = new Map();
    let draggingRow = null;

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

    function updateSortOrders() {
        container.querySelectorAll('[data-role="detail-row"]').forEach((row, index) => {
            row.querySelector('[data-role="sort-order"]').value = String(index);
        });
    }

    function getRowAfterElement(y) {
        const rows = [...container.querySelectorAll('[data-role="detail-row"]:not(.dragging)')];

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

    function bindDragAndDrop(row) {
        const handle = row.querySelector('[data-role="drag-handle"]');

        handle.addEventListener('mousedown', () => {
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

    function bindRow(row) {
        initEditor(row);
        bindDragAndDrop(row);

        row.querySelector('[data-role="remove-detail"]').addEventListener('click', () => {
            editors.delete(row);
            row.remove();
            updateSortOrders();
        });
    }

    container.querySelectorAll('[data-role="detail-row"]').forEach(bindRow);

    addButton.addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;

        container.appendChild(row);
        bindRow(row);
        nextIndex += 1;
        updateSortOrders();
    });

    form.addEventListener('submit', () => {
        editors.forEach(({ quill, hiddenInput }) => {
            hiddenInput.value = quill.root.innerHTML;
        });
        updateSortOrders();
    });

    updateSortOrders();
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
    let draggingRow = null;

    function getRowAfterElement(y) {
        const rows = [...tbody.querySelectorAll('[data-role="single-page-row"]:not(.dragging)')];

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

    tbody.querySelectorAll('[data-role="single-page-row"]').forEach((row) => {
        const handle = row.querySelector('[data-role="drag-handle"]');

        handle.addEventListener('mousedown', () => {
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
        });
    });

    tbody.addEventListener('dragover', (event) => {
        if (!draggingRow) {
            return;
        }

        event.preventDefault();

        const afterElement = getRowAfterElement(event.clientY);

        if (afterElement == null) {
            tbody.appendChild(draggingRow);
        } else {
            tbody.insertBefore(draggingRow, afterElement);
        }
    });
}

/**
 * 画像アップロード用のドロップゾーンUI(サイトアイコン・サイト画像などのフォーム)を初期化する。
 * クリックでのファイル選択、ドラッグ&ドロップ、選択直後のプレビュー表示、選択解除に対応する。
 */
function initImageDropzones() {
    document.querySelectorAll('[data-role="image-dropzone"]').forEach(bindImageDropzone);
}

function bindImageDropzone(dropzone) {
    const input = dropzone.querySelector('[data-role="image-dropzone-input"]');
    const preview = dropzone.querySelector('[data-role="image-dropzone-preview"]');
    const previewImage = dropzone.querySelector('[data-role="image-dropzone-image"]');
    const placeholder = dropzone.querySelector('[data-role="image-dropzone-placeholder"]');
    const removeButton = dropzone.querySelector('[data-role="image-dropzone-remove"]');

    function showPreview(src) {
        previewImage.src = src;
        preview.style.display = '';
        placeholder.style.display = 'none';
    }

    function showPlaceholder() {
        previewImage.src = '';
        preview.style.display = 'none';
        placeholder.style.display = '';
    }

    function handleFiles(files) {
        const file = files?.[0];

        if (!file || !file.type.startsWith('image/')) {
            return;
        }

        const reader = new FileReader();
        reader.onload = () => showPreview(reader.result);
        reader.readAsDataURL(file);
    }

    dropzone.addEventListener('click', (event) => {
        if (event.target.closest('[data-role="image-dropzone-remove"]')) {
            return;
        }

        input.click();
    });

    dropzone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            input.click();
        }
    });

    input.addEventListener('change', () => handleFiles(input.files));

    dropzone.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropzone.classList.add('is-dragover');
    });

    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('is-dragover');
    });

    dropzone.addEventListener('drop', (event) => {
        event.preventDefault();
        dropzone.classList.remove('is-dragover');

        const files = event.dataTransfer.files;

        if (files.length) {
            input.files = files;
            handleFiles(files);
        }
    });

    removeButton?.addEventListener('click', (event) => {
        event.stopPropagation();
        input.value = '';
        showPlaceholder();
    });
}
