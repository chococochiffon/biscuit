/**
 * 記事フォームのタグ入力とタグ管理モーダル。
 */
import { initManagerModal } from './manager-modal.js';
import { t, requestJson } from './utils.js';

/**
 * タグ検索・選択UI(記事の作成・編集フォーム)を初期化する。
 * 選択済みタグはバッジで表示し、×ボタンで選択解除できる。
 */
export function initTagSelector() {
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
export function initTagManagerModal() {
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
