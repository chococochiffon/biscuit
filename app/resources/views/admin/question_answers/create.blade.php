@extends('layouts.admin')

@section('title', __('Q&Aの新規登録'))

@section('content')
    <h1 class="h5 mb-4">{{ __('Q&Aの新規登録') }}</h1>

    <form
        method="POST"
        action="{{ route('admin.question-answers.store') }}"
        data-role="question-answer-form"
    >
        @csrf

        @include('admin.question_answers._form')

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('登録する') }}
            </button>
            <a href="{{ route('admin.question-answers.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
