/**
 * 繰り返し入力(行の追加・削除・ドラッグでの並び替え)の共通処理。
 */

/**
 * 行の追加・削除・ドラッグでの並び替えだけを行う汎用の繰り返し入力(SNSリンク・スキルなど)を初期化する。
 * data-role="repeater" の中に、行のコンテナ(repeater-rows、data-next-index に次の行番号)・
 * 追加ボタン(repeater-add)・行のテンプレート(repeater-template、行番号は __INDEX__)を置く。
 * 各行(repeater-row)にはドラッグハンドル(drag-handle)・並び順の隠しinput(sort-order)・削除ボタン(remove-row)を置く。
 * repeater に data-max-rows があれば、行数がその件数に達したときに追加ボタンを無効にする。
 */
export function initRepeaterRows() {
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
 * 追加ボタンでテンプレート(行番号は placeholder。既定は __INDEX__。コンテナの data-next-index から採番する)の行をコンテナの末尾に追加し、
 * 各行の削除ボタン(removeSelector)で行を削除する。maxRows(0 なら無制限)に達したら追加ボタンを無効にする。
 * 行ごとの独自の初期化は onBindRow、削除時の後始末は onRemoveRow で行う。
 * 返り値の updateSortOrders で、各行の並び順の隠しinputを画面上の順番に設定し直せる。
 */
export function initEditableRows(container, {
    rowSelector,
    addButton,
    template,
    removeSelector = '[data-role="remove-row"]',
    placeholder = '__INDEX__',
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
        wrapper.innerHTML = template.innerHTML.replaceAll(placeholder, String(nextIndex)).trim();
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
export function initSortableRows(container, rowSelector) {
    let draggingRow = null;

    function updateSortOrders() {
        container.querySelectorAll(rowSelector).forEach((row, index) => {
            const input = row.querySelector('[data-role="sort-order"]');

            if (input) {
                input.value = String(index);
            }
        });
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

        const afterElement = getRowAfterElement(container, rowSelector, event.clientY);

        if (afterElement == null) {
            container.appendChild(draggingRow);
        } else {
            container.insertBefore(draggingRow, afterElement);
        }
    });

    return { bindRow, updateSortOrders };
}

/**
 * 一覧の表(tableId)の行(rowSelector)をハンドルのドラッグで並び替えられるようにする(固定ページ一覧・ギャラリー画像一覧)。
 * 行に置いた隠しinput(order[])の DOM の順が変わり、「並び替えを保存」ボタンで並び替え用フォームに送信される。
 * 表は並び替えできるとき(並び順が「表示順」かつ検索条件なし)だけ id を持つため、id がなければ何もしない。
 */
export function initReorderTable(tableId, rowSelector) {
    const table = document.getElementById(tableId);

    if (!table) {
        return;
    }

    const tbody = table.querySelector('tbody');
    const { bindRow } = initSortableRows(tbody, rowSelector);

    tbody.querySelectorAll(rowSelector).forEach(bindRow);
}

/**
 * ドラッグ中の行を落とす位置として、コンテナ内でマウスの位置(y)より下にある最初の行を返す(末尾なら null)。
 * ドラッグ中の行(.dragging)は候補から除く。
 */
export function getRowAfterElement(container, rowSelector, y) {
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
