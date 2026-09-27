/**
 * 記事本文のリッチテキストエディタ(Quill)。
 */
import Quill from 'quill';
import { t, requestJson } from './utils.js';

/**
 * 記事本文用のリッチテキストエディタ(Quill)を初期化する。
 * 画像はツールバーからアップロードし、返却されたURLを本文に埋め込む。
 */
export function initContentEditor() {
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
