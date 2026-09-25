@php
    $hasOldInput = old('type') !== null;

    $type = $hasOldInput
        ? old('type')
        : (isset($questionAnswer) && ! $questionAnswer->top_view ? 'branch' : 'simple');

    $questionData = ($hasOldInput ? old('question') : null) ?? ($questionTree ?? null) ?? [
        'id' => null,
        'question_text' => null,
        'answers' => [['id' => null, 'answer_text' => null, 'question' => null]],
    ];
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mb-3">
    <label class="form-label d-block">{{ __('形式') }}</label>
    <div class="form-check form-check-inline">
        <input id="type_simple" type="radio" name="type" value="simple" class="form-check-input" @checked($type === 'simple')>
        <label for="type_simple" class="form-check-label">{{ __('簡易版(トップ表示)') }}</label>
    </div>
    <div class="form-check form-check-inline">
        <input id="type_branch" type="radio" name="type" value="branch" class="form-check-input" @checked($type === 'branch')>
        <label for="type_branch" class="form-check-label">{{ __('分岐あり') }}</label>
    </div>
    <div class="form-text">{{ __('簡易版はトップに表示します。簡易版では質問・回答の分岐は登録しません。') }}</div>
</div>

<fieldset data-type-section="simple">
    <div class="mb-3">
        <label for="short_question_text" class="form-label">{{ __('質問(簡易版)') }}</label>
        <textarea
            id="short_question_text"
            name="short_question_text"
            rows="2"
            class="form-control @error('short_question_text') is-invalid @enderror"
        >{{ old('short_question_text', $questionAnswer->short_question_text ?? '') }}</textarea>
        @error('short_question_text')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="short_answer_text" class="form-label">{{ __('回答(簡易版)') }}</label>
        <textarea
            id="short_answer_text"
            name="short_answer_text"
            rows="3"
            class="form-control @error('short_answer_text') is-invalid @enderror"
        >{{ old('short_answer_text', $questionAnswer->short_answer_text ?? '') }}</textarea>
        @error('short_answer_text')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</fieldset>

<fieldset class="mb-3" data-type-section="branch">
    <div class="form-text mb-2">{{ __('回答ごとに「分岐する質問」を追加すると、その回答を選んだときに次の質問へ進みます。') }}</div>

    @include('admin.question_answers._question_block', [
        'name' => 'question',
        'key' => 'question',
        'question' => $questionData,
        'depth' => 1,
    ])
</fieldset>

<template data-role="answer-template">
    @include('admin.question_answers._answer_row', ['name' => '__NAME__', 'key' => '__KEY__', 'answer' => null, 'depth' => 1])
</template>

<template data-role="question-template">
    @include('admin.question_answers._question_block', ['name' => '__NAME__', 'key' => '__KEY__', 'question' => null, 'depth' => 2])
</template>
