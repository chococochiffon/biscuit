@php
    /**
     * @var string $name  入力名の接頭辞(例: question[answers][0])
     * @var string $key  エラー参照用のドット区切りキー(例: question.answers.0)
     * @var array|null $answer  回答(id・answer_text・question)
     * @var int $depth  回答が属する質問の分岐の深さ
     */
    $hasBranch = ! empty($answer['question']);
@endphp

<li data-role="answer-row" data-name="{{ $name }}">
    <div class="qa-node qa-node--answer">
        @if (! empty($answer['id']))
            <input type="hidden" name="{{ $name }}[id]" value="{{ $answer['id'] }}">
        @endif

        <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="qa-node-label">{{ __('回答') }}</span>
            <button type="button" class="btn btn-outline-danger btn-sm py-0" data-role="remove-answer" title="{{ __('回答を削除') }}">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <textarea
            name="{{ $name }}[answer_text]"
            rows="3"
            class="form-control form-control-sm @error($key.'.answer_text') is-invalid @enderror"
            placeholder="{{ __('回答文') }}"
        >{{ $answer['answer_text'] ?? '' }}</textarea>
        @error($key.'.answer_text')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror

        <button type="button" class="btn btn-link btn-sm p-0 mt-1 @if ($hasBranch) d-none @endif" data-role="add-branch">
            {{ __('+ 分岐する質問を追加') }}
        </button>
    </div>

    <ul data-role="branch-container">
        @if ($hasBranch)
            @include('admin.question_answers._question_block', [
                'name' => "{$name}[question]",
                'key' => "{$key}.question",
                'question' => $answer['question'],
                'depth' => $depth + 1,
            ])
        @endif
    </ul>
</li>
