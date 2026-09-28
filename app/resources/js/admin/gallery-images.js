/**
 * ギャラリー画像一覧の並び替え。
 */
import { initSortableRows } from './rows.js';

/**
 * ギャラリー画像一覧(gallery_images)の並び替えUIを初期化する。
 * ハンドルをドラッグして行を並び替えると、隠しinput(order[])のDOM順が変わり、
 * 「並び替えを保存」ボタンで並び替え用フォーム(gallery-image-reorder-form)に送信される。
 */
export function initGalleryImageReorder() {
    const table = document.getElementById('gallery-image-reorder-rows');

    if (!table) {
        return;
    }

    const tbody = table.querySelector('tbody');
    const { bindRow } = initSortableRows(tbody, '[data-role="gallery-image-row"]');

    tbody.querySelectorAll('[data-role="gallery-image-row"]').forEach(bindRow);
}
