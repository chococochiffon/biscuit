@extends('layouts.admin')

@section('title', __('Q&Aの編集'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 48rem;">{{ __('Q&Aの編集') }}</h1>

    <form
        method="POST"
        action="{{ route('admin.question-answers.update', $questionAnswer) }}"
        class="mx-auto"
        style="max-width: 48rem;"
        data-role="question-answer-form"
    >
        @csrf
        @method('PUT')

        @include('admin.question_answers._form')

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('更新する') }}
            </button>
            <a href="{{ route('admin.question-answers.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
