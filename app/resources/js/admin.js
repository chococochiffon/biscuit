/**
 * 管理画面の JS の入口。機能ごとのモジュール(resources/js/admin/)を読み込み、画面の読み込み後に初期化する。
 * 各 init 関数は、対象の要素がない画面では何もしない。
 */
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import { initTagSelector, initTagManagerModal } from './admin/tags.js';
import { initContentEditor } from './admin/content-editor.js';
import { initCallContentRows, initContentModelRelationManagerModal } from './admin/call-contents.js';
import { initRepeaterRows, initReorderTable } from './admin/rows.js';
import { initSinglePageDetailRows } from './admin/single-pages.js';
import { initPathPreview, initDateTimePickers } from './admin/forms.js';
import { initImageDropzones, initImageCroppers } from './admin/images.js';
import { initArticleApprovalControls, initArticlePathOptionManagerModal } from './admin/articles.js';
import { initQuestionAnswerForm, initQuestionAnswerFlows } from './admin/question-answers.js';
import { initGalleryCategoryManagerModal } from './admin/gallery-images.js';
import { initLayoutBlocks } from './admin/layouts.js';

document.addEventListener('DOMContentLoaded', () => {
    initTagSelector();
    initTagManagerModal();
    initContentEditor();
    initCallContentRows();
    initRepeaterRows();
    initContentModelRelationManagerModal();
    initSinglePageDetailRows();
    initReorderTable('single-page-reorder-rows', '[data-role="single-page-row"]');
    initPathPreview();
    initImageDropzones();
    initImageCroppers();
    initDateTimePickers();
    initArticleApprovalControls();
    initArticlePathOptionManagerModal();
    initQuestionAnswerForm();
    initQuestionAnswerFlows();
    initReorderTable('gallery-image-reorder-rows', '[data-role="gallery-image-row"]');
    initGalleryCategoryManagerModal();
    initLayoutBlocks();
});
