@php
    /**
     * @var string $name  入力名の接頭辞(例: question[answers][0][question])
     * @var string $key  エラー参照用のドット区切りキー(例: question.answers.0.question)
     * @var array|null $question  質問(id・question_text・answers)
     * @var int $depth  分岐の深さ(最初の質問が 1)
     */
@endphp

<li data-role="question-block" data-name="{{ $name }}">
    <div class="qa-node qa-node--question">
        @if (! empty($question['id']))
            <input type="hidden" name="{{ $name }}[id]" value="{{ $question['id'] }}">
        @endif

        <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="qa-node-label">{{ $depth === 1 ? __('最初の質問') : __('分岐先の質問') }}</span>
            @if ($depth > 1)
                <button type="button" class="btn btn-outline-danger btn-sm py-0" data-role="remove-branch" title="{{ __('分岐を削除') }}">
                    <i class="bi bi-x-lg"></i>
                </button>
            @endif
        </div>
        <textarea
            name="{{ $name }}[question_text]"
            rows="3"
            class="form-control form-control-sm @error($key.'.question_text') is-invalid @enderror"
            placeholder="{{ __('質問文') }}"
        >{{ $question['question_text'] ?? '' }}</textarea>
        @error($key.'.question_text')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        @error($key.'.answers')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <ul data-role="answer-rows">
        @foreach ($question['answers'] ?? [] as $index => $answer)
            @include('admin.question_answers._answer_row', [
                'name' => "{$name}[answers][{$index}]",
                'key' => "{$key}.answers.{$index}",
                'answer' => $answer,
                'depth' => $depth,
            ])
        @endforeach

        <li data-role="answer-add-slot">
            <div class="qa-node qa-node--add">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-role="add-answer">
                    {{ __('+ 回答を追加') }}
                </button>
            </div>
        </li>
    </ul>
</li>
