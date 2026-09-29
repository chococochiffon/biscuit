/**
 * レイアウト管理の部品(ヘッダー・サイドバー・フッターに置く部品)の行入力。
 */
import Quill from 'quill';
import { bindCallContentFields } from './call-contents.js';
import { getRowAfterElement } from './rows.js';

const ROW_SELECTOR = '[data-role="layout-block-row"]';

/**
 * レイアウト管理画面の部品の行入力を初期化する。
 * 領域ごとの「+ 部品を追加」で行を追加し、「−」で削除する。左側のハンドルのドラッグで、領域の中の並び替えと別の領域への移動ができ、
 * 各行の隠しinputへ領域(region)と領域の中の並び順(sort_order)を設定する。
 * 部品の種類に応じて、見出し・呼び出しコンテンツ・自由テキストの入力欄を切り替える(使わない入力欄は無効にして送信しない)。
 * 自由テキストの本文はリッチテキストエディタ(Quill)で編集し、フォーム送信時に隠しinputへ反映する。
 */
export function initLayoutBlocks() {
    const root = document.getElementById('layout-blocks');

    if (!root) {
        return;
    }

    const constraints = JSON.parse(root.dataset.callTypeConstraints || '{}');
    const blockTypes = JSON.parse(root.dataset.blockTypes || '{}');
    const template = document.getElementById('layout-block-row-template');
    const containers = [...root.querySelectorAll('[data-role="layout-block-rows"]')];
    const editors = new Map();
    let nextIndex = Number(root.dataset.nextIndex || '0');
    let draggingRow = null;

    // 各行の領域と並び順を、画面上の位置に合わせる
    function updateRowPositions() {
        containers.forEach((container) => {
            container.querySelectorAll(ROW_SELECTOR).forEach((row, index) => {
                row.querySelector('[data-role="sort-order"]').value = String(index);
                row.querySelector('[data-role="region-input"]').value = container.dataset.region;
            });
        });
    }

    function toggleFields(section, enabled) {
        section.hidden = !enabled;
        section.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !enabled;
        });
    }

    function initEditor(row) {
        if (editors.has(row)) {
            return;
        }

        const hiddenInput = row.querySelector('[data-role="free-text-input"]');
        const quill = new Quill(row.querySelector('[data-role="free-text-editor"]'), {
            theme: 'snow',
            modules: {
                toolbar: [
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

    function applyBlockType(row) {
        const blockType = Number(row.querySelector('[data-role="block-type-select"]').value);
        const isFreeText = blockType === blockTypes.freeText;

        row.querySelectorAll('[data-role="heading-fields"]').forEach((section) => {
            toggleFields(section, (blockTypes.withHeading ?? []).includes(blockType));
        });
        toggleFields(row.querySelector('[data-role="call-content-fields"]'), blockType === blockTypes.callContent);
        toggleFields(row.querySelector('[data-role="free-text-fields"]'), isFreeText);

        if (isFreeText) {
            initEditor(row);
        }
    }

    function bindRow(row) {
        row.querySelector('[data-role="drag-handle"]').addEventListener('mousedown', () => {
            row.draggable = true;
        });

        row.addEventListener('dragstart', (event) => {
            // エディタ内の文字のドラッグなど、ハンドル以外から始まったドラッグは並び替えにしない
            if (!row.draggable || event.target !== row) {
                return;
            }

            draggingRow = row;
            row.classList.add('dragging');
        });

        row.addEventListener('dragend', () => {
            row.draggable = false;
            row.classList.remove('dragging');
            draggingRow = null;
            updateRowPositions();
        });

        row.querySelector('[data-role="remove-row"]').addEventListener('click', () => {
            editors.delete(row);
            row.remove();
            updateRowPositions();
        });

        row.querySelector('[data-role="block-type-select"]').addEventListener('change', () => applyBlockType(row));

        bindCallContentFields(row, constraints);
        applyBlockType(row);
    }

    containers.forEach((container) => {
        container.querySelectorAll(ROW_SELECTOR).forEach(bindRow);

        container.addEventListener('dragover', (event) => {
            if (!draggingRow) {
                return;
            }

            event.preventDefault();

            const afterElement = getRowAfterElement(container, ROW_SELECTOR, event.clientY);

            if (afterElement == null) {
                container.appendChild(draggingRow);
            } else {
                container.insertBefore(draggingRow, afterElement);
            }
        });
    });

    root.querySelectorAll('[data-role="layout-block-add"]').forEach((button) => {
        button.addEventListener('click', () => {
            const container = containers.find((element) => element.dataset.region === button.dataset.region);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex)).trim();
            const row = wrapper.firstElementChild;

            container.appendChild(row);
            bindRow(row);
            nextIndex += 1;
            updateRowPositions();
            row.querySelector('[data-role="block-type-select"]').focus();
        });
    });

    root.closest('form').addEventListener('submit', () => {
        editors.forEach(({ quill, hiddenInput }) => {
            hiddenInput.value = quill.getText().trim() === '' ? '' : quill.root.innerHTML;
        });
        updateRowPositions();
    });

    updateRowPositions();
}
