@extends('layouts.admin')

@section('title', __('Q&A詳細'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between mx-auto" style="max-width: 48rem;">
        <h1 class="h5 mb-0">{{ __('Q&A詳細') }}</h1>
        <a href="{{ route('admin.question-answers.edit', $questionAnswer) }}" class="link-primary">{{ __('編集する') }}</a>
    </div>

    <div class="card mx-auto" style="max-width: 48rem;">
        <dl class="row mb-0 p-3">
            <dt class="col-3 text-muted fw-normal">{{ __('形式') }}</dt>
            <dd class="col-9">{{ $questionAnswer->top_view ? __('簡易版(トップ表示)') : __('分岐あり') }}</dd>

            @if ($questionAnswer->top_view)
                <dt class="col-3 text-muted fw-normal">{{ __('質問(簡易版)') }}</dt>
                <dd class="col-9" style="white-space: pre-wrap;">{{ $questionAnswer->short_question_text }}</dd>

                <dt class="col-3 text-muted fw-normal">{{ __('回答(簡易版)') }}</dt>
                <dd class="col-9 mb-0" style="white-space: pre-wrap;">{{ $questionAnswer->short_answer_text }}</dd>
            @else
                <dt class="col-3 text-muted fw-normal">{{ __('質問・回答') }}</dt>
                <dd class="col-9 mb-0">
                    @if ($questionTree)
                        @include('admin.question_answers._question_tree', ['question' => $questionTree])
                    @else
                        {{ __('未登録') }}
                    @endif
                </dd>
            @endif
        </dl>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.question-answers.index') }}" class="text-secondary">{{ __('一覧へ戻る') }}</a>
    </div>
@endsection
