@php
    /**
     * @var string $name  入力名の接頭辞(例: question[answers][0])
     * @var string $key  エラー参照用のドット区切りキー(例: question.answers.0)
     * @var array|null $answer  回答(id・answer_text・question)
     * @var int $depth  回答が属する質問の分岐の深さ
     */
    $hasBranch = ! empty($answer['question']);
@endphp

<div class="mb-3" data-role="answer-row" data-name="{{ $name }}">
    @if (! empty($answer['id']))
        <input type="hidden" name="{{ $name }}[id]" value="{{ $answer['id'] }}">
    @endif

    <div class="d-flex gap-2 align-items-start">
        <div class="flex-grow-1">
            <label class="form-label small mb-1">{{ __('回答文') }}</label>
            <textarea
                name="{{ $name }}[answer_text]"
                rows="2"
                class="form-control form-control-sm @error($key.'.answer_text') is-invalid @enderror"
            >{{ $answer['answer_text'] ?? '' }}</textarea>
            @error($key.'.answer_text')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">{{ __('分岐する質問がない場合は、この回答文を表示して終わります。') }}</div>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm mt-4" data-role="remove-answer" title="{{ __('回答を削除') }}">−</button>
    </div>

    <div class="mt-2" data-role="branch-container">
        @if ($hasBranch)
            @include('admin.question_answers._question_block', [
                'name' => "{$name}[question]",
                'key' => "{$key}.question",
                'question' => $answer['question'],
                'depth' => $depth + 1,
            ])
        @endif
    </div>

    <button type="button" class="btn btn-link btn-sm p-0 @if ($hasBranch) d-none @endif" data-role="add-branch">
        {{ __('+ 分岐する質問を追加') }}
    </button>
</div>
