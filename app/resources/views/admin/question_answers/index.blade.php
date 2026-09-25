@extends('layouts.admin')

@section('title', __('Q&A一覧'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('Q&A一覧') }}</h1>

        <a href="{{ route('admin.question-answers.create') }}" class="btn btn-primary">
            {{ __('新規登録') }}
        </a>
    </div>

    <div class="card">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('質問') }}</th>
                    <th class="text-nowrap">{{ __('形式') }}</th>
                    <th class="text-nowrap">{{ __('更新日時') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($questionAnswers as $questionAnswer)
                    <tr>
                        <td>
                            <a href="{{ route('admin.question-answers.show', $questionAnswer) }}">
                                {{ Illuminate\Support\Str::limit($questionAnswer->top_view ? $questionAnswer->short_question_text : $questionAnswer->question?->question_text, 60) }}
                            </a>
                        </td>
                        <td class="text-nowrap">
                            @if ($questionAnswer->top_view)
                                <span class="badge text-bg-primary">{{ __('簡易版(トップ表示)') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('分岐あり') }}</span>
                            @endif
                        </td>
                        <td class="text-nowrap">{{ $questionAnswer->updated_at->format('Y/m/d H:i') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.question-answers.edit', $questionAnswer) }}" class="btn btn-sm btn-outline-secondary">{{ __('編集') }}</a>

                            <form
                                method="POST"
                                action="{{ route('admin.question-answers.destroy', $questionAnswer) }}"
                                class="d-inline"
                                onsubmit="return confirm('{{ __('削除してよろしいですか?') }}');"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('削除') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">{{ __('Q&Aが登録されていません。') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $questionAnswers->links() }}
    </div>
@endsection
