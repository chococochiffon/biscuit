@php
    /**
     * @var string $name  入力名の接頭辞(例: question[answers][0][question])
     * @var string $key  エラー参照用のドット区切りキー(例: question.answers.0.question)
     * @var array|null $question  質問(id・question_text・answers)
     * @var int $depth  分岐の深さ(最初の質問が 1)
     */
@endphp

<div class="card mb-2" data-role="question-block" data-name="{{ $name }}">
    <div class="card-body p-3">
        @if (! empty($question['id']))
            <input type="hidden" name="{{ $name }}[id]" value="{{ $question['id'] }}">
        @endif

        <div class="d-flex align-items-center justify-content-between mb-1">
            <label class="form-label mb-0">{{ $depth === 1 ? __('最初の質問') : __('分岐先の質問') }}</label>
            @if ($depth > 1)
                <button type="button" class="btn btn-outline-danger btn-sm" data-role="remove-branch">{{ __('分岐を削除') }}</button>
            @endif
        </div>
        <textarea
            name="{{ $name }}[question_text]"
            rows="2"
            class="form-control @error($key.'.question_text') is-invalid @enderror"
        >{{ $question['question_text'] ?? '' }}</textarea>
        @error($key.'.question_text')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror

        <div class="mt-3 ps-3 border-start" data-role="answer-rows">
            @foreach ($question['answers'] ?? [] as $index => $answer)
                @include('admin.question_answers._answer_row', [
                    'name' => "{$name}[answers][{$index}]",
                    'key' => "{$key}.answers.{$index}",
                    'answer' => $answer,
                    'depth' => $depth,
                ])
            @endforeach
        </div>
        @error($key.'.answers')
            <div class="text-danger small ms-3 mb-1">{{ $message }}</div>
        @enderror

        <button type="button" class="btn btn-outline-secondary btn-sm ms-3" data-role="add-answer">
            {{ __('+ 回答を追加') }}
        </button>
    </div>
</div>
