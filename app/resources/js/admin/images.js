/**
 * 画像アップロード欄のドロップゾーンと、切り抜きモーダル(Cropper.js)。
 */
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { Modal } from 'bootstrap/dist/js/bootstrap.bundle.min.js';

/**
 * 画像アップロード用のドロップゾーン(data-role="image-dropzone"、Blade の <x-admin.image-dropzone>)を初期化する。
 * クリック・Enter/Space でファイル選択を開き、ドロップしたファイルはファイル入力へ移して change を発生させる。
 * 選択した画像は枠内にプレビューし、選択解除ボタン(image-dropzone-remove)で選択を取り消す。
 * 切り抜きUI(data-role="image-cropper")の中のドロップゾーンは、選択後の表示を initImageCroppers() に任せる。
 * リピーターで後から追加される行にも対応するため、各イベントは document で受け取る。
 */
export function initImageDropzones() {
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
export function initImageCroppers() {
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
