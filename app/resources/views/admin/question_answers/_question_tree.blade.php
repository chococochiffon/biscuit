@php
    /**
     * @var array $question  質問(id・question_text・answers)
     */
@endphp

<div class="fw-semibold">Q. {{ $question['question_text'] }}</div>

<ul class="list-unstyled ps-3 border-start mt-2 mb-0">
    @foreach ($question['answers'] as $answer)
        <li class="mb-2">
            <div>A. {{ $answer['answer_text'] ?? '' }}</div>

            @if ($answer['question'])
                <div class="mt-2">
                    @include('admin.question_answers._question_tree', ['question' => $answer['question']])
                </div>
            @endif
        </li>
    @endforeach
</ul>
