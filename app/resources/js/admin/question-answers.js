/**
 * Q&A の作成・編集フォームと、フローチャートの表示。
 */

/**
 * Q&A の作成・編集フォームを初期化する。
 * 形式(type)のラジオで簡易版/分岐ありの入力欄(fieldset[data-type-section])を切り替え、
 * 選んでいない側は disabled にして送信しない。分岐ありはフローチャート(上→下のツリー)で、
 * 質問ノード(question-block)の下に回答ノード(answer-row)を追加・削除し、回答ノードの下に分岐先の質問ノードを追加・削除する。
 * 入力名は各ノードの data-name を接頭辞にして組み立てる。
 * 分岐ありの欄に data-max-answers があれば、回答がその件数に達した質問ノードの「回答を追加」を無効にする。
 */
export function initQuestionAnswerForm() {
    const form = document.querySelector('[data-role="question-answer-form"]');

    if (!form) {
        return;
    }

    const typeInputs = form.querySelectorAll('input[name="type"]');
    const sections = form.querySelectorAll('[data-type-section]');

    function applyType() {
        const checked = form.querySelector('input[name="type"]:checked');

        sections.forEach((section) => {
            const active = checked !== null && section.dataset.typeSection === checked.value;
            section.hidden = !active;
            section.disabled = !active;
        });
    }

    typeInputs.forEach((input) => input.addEventListener('change', applyType));
    applyType();

    const maxAnswers = Number(form.querySelector('[data-max-answers]')?.dataset.maxAnswers || '0');

    function updateAddAnswerButtons() {
        if (maxAnswers <= 0) {
            return;
        }

        form.querySelectorAll('[data-role="question-block"]').forEach((questionBlock) => {
            const rows = questionBlock.querySelector(':scope > [data-role="answer-rows"]');
            const button = rows.querySelector(':scope > [data-role="answer-add-slot"] [data-role="add-answer"]');

            button.disabled = rows.querySelectorAll(':scope > [data-role="answer-row"]').length >= maxAnswers;
        });
    }

    const answerTemplate = form.querySelector('[data-role="answer-template"]');
    const questionTemplate = form.querySelector('[data-role="question-template"]');

    // バリデーションエラーで戻ったときに既存の行と入力名が重ならないよう、時刻を含めた番号にする
    let counter = 0;
    const nextIndex = () => `n${Date.now()}_${counter++}`;

    function render(template, name) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__NAME__', name).trim();

        return wrapper.firstElementChild;
    }

    function addAnswer(questionBlock) {
        const rows = questionBlock.querySelector(':scope > [data-role="answer-rows"]');
        const addSlot = rows.querySelector(':scope > [data-role="answer-add-slot"]');

        rows.insertBefore(render(answerTemplate, `${questionBlock.dataset.name}[answers][${nextIndex()}]`), addSlot);
    }

    form.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-role]');

        if (!button) {
            return;
        }

        switch (button.dataset.role) {
            case 'add-answer':
                addAnswer(button.closest('[data-role="question-block"]'));
                break;
            case 'remove-answer':
                button.closest('[data-role="answer-row"]').remove();
                break;
            case 'add-branch': {
                const row = button.closest('[data-role="answer-row"]');
                const questionBlock = render(questionTemplate, `${row.dataset.name}[question]`);

                row.querySelector(':scope > [data-role="branch-container"]').appendChild(questionBlock);
                addAnswer(questionBlock);
                button.classList.add('d-none');
                break;
            }
            case 'remove-branch': {
                const row = button.closest('[data-role="answer-row"]');

                button.closest('[data-role="question-block"]').remove();
                row.querySelector(':scope > .qa-node [data-role="add-branch"]').classList.remove('d-none');
                break;
            }
        }

        updateAddAnswerButtons();
    });

    updateAddAnswerButtons();
}

/**
 * Q&A のフローチャート(.qa-flow)が横にはみ出す場合、最初の質問が見えるよう横スクロールを中央に合わせる。
 */
export function initQuestionAnswerFlows() {
    document.querySelectorAll('.qa-flow').forEach((flow) => {
        flow.scrollLeft = (flow.scrollWidth - flow.clientWidth) / 2;
    });
}
