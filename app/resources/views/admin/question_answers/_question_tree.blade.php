@php
    /**
     * @var array $question  質問(id・question_text・answers)
     */
@endphp

<li>
    <div class="qa-node qa-node--question">
        <div class="qa-node-label small">Q</div>
        <div class="qa-node-text">{{ $question['question_text'] }}</div>
    </div>

    <ul>
        @foreach ($question['answers'] as $answer)
            <li>
                <div class="qa-node qa-node--answer">
                    <div class="qa-node-label small">A</div>
                    <div class="qa-node-text">{{ $answer['answer_text'] ?? '' }}</div>
                </div>

                @if ($answer['question'])
                    <ul>
                        @include('admin.question_answers._question_tree', ['question' => $answer['question']])
                    </ul>
                @endif
            </li>
        @endforeach
    </ul>
</li>
