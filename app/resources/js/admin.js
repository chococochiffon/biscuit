import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import Quill from 'quill';

document.addEventListener('DOMContentLoaded', () => {
    initTagSelector();
    initTagManagerModal();
    initContentEditor();
    initCallContentRows();
    initSinglePageDetailRows();
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

    let nextIndex = Number(container.dataset.nextIndex || '0');

    function bindRow(row) {
        row.querySelector('[data-role="remove-row"]').addEventListener('click', () => row.remove());
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
