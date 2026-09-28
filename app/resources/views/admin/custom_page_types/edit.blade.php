@extends('layouts.admin')

@section('title', __('カスタムページの編集'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 64rem;">{{ __('カスタムページの編集') }}</h1>

    <form method="POST" action="{{ route('admin.custom-page-types.update', $customPageType) }}" class="mx-auto" style="max-width: 64rem;">
        @csrf
        @method('PUT')

        @include('admin.partials._form_errors')

        <dl class="row small mb-3">
            <dt class="col-sm-3">{{ __('カスタム名') }}</dt>
            <dd class="col-sm-9"><code>{{ $customPageType->name }}</code></dd>
            <dt class="col-sm-3">{{ __('型') }}</dt>
            <dd class="col-sm-9">{{ $customPageType->base_type->label() }}</dd>
            <dt class="col-sm-3">{{ __('テーブル') }}</dt>
            <dd class="col-sm-9">
                @foreach ($customPageType->tableNames() as $tableName)
                    <code class="me-2">{{ $tableName }}</code>
                @endforeach
            </dd>
        </dl>

        <div class="mb-4" style="max-width: 28rem;">
            <label for="label" class="form-label">{{ __('表示名') }}</label>
            <input id="label" type="text" name="label" value="{{ old('label', $customPageType->label) }}" maxlength="128" required class="form-control">
        </div>

        @php
            $formRows = \App\Support\RepeaterRows::build(
                'forms',
                $forms,
                fn (array $row) => [
                    'partsName' => $row['parts_name'] ?? null,
                    'formType' => $row['customs_form_type'] ?? null,
                    'options' => $row['options'] ?? null,
                ],
                fn ($form) => [
                    'partsName' => $form->parts_name,
                    'formType' => $form->customs_form_type->value,
                    'options' => implode("\n", $form->options()),
                ],
            );
        @endphp

        <div class="mb-3" data-role="repeater">
            <label class="form-label mb-0">{{ __('カスタムフォーム') }}</label>
            <div class="form-text mb-2">
                {{ __(':labelの登録・編集画面に、この順で入力項目を追加します。項目を削除すると、入力済みの値も表示されなくなります。', ['label' => $customPageType->label]) }}
            </div>

            <div data-role="repeater-rows" data-next-index="{{ $formRows->count() }}">
                @foreach ($formRows as $row)
                    @include('admin.custom_page_types._form_row', [
                        'index' => $row->index,
                        'id' => $row->id,
                        'partsName' => $row->partsName,
                        'formType' => $row->formType,
                        'options' => $row->options,
                        'sortOrder' => $row->sortOrder,
                    ])
                @endforeach
            </div>

            <button type="button" class="btn btn-outline-secondary btn-sm" data-role="repeater-add">
                {{ __('+ 行を追加') }}
            </button>

            <template data-role="repeater-template">
                @include('admin.custom_page_types._form_row', [
                    'index' => '__INDEX__',
                    'id' => null,
                    'partsName' => null,
                    'formType' => \App\Enums\CustomFormType::Text->value,
                    'options' => null,
                    'sortOrder' => 0,
                ])
            </template>
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">{{ __('更新する') }}</button>
            <a href="{{ route('admin.custom-page-types.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
