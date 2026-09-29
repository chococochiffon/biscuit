/**
 * 固定ページの詳細の行入力。
 */
import Quill from 'quill';
import { initEditableRows } from './rows.js';

/**
 * 固定ページの詳細(single_page_details)の行入力UI(固定ページの登録・編集フォーム)を初期化する。
 * 「+」で行を追加、「×」で行を削除し、左側のハンドルをドラッグして並び替えできる。
 * 各行の本文はリッチテキストエディタ(Quill)で編集し、フォーム送信時に隠しtextareaへ反映する。
 * コンテナに data-max-rows があれば、行数がその件数に達したときに追加ボタンを無効にする。
 */
export function initSinglePageDetailRows() {
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
