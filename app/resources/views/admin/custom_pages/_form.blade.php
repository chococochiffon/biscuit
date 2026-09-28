@include('admin.partials._form_errors')

@php
    /**
     * @var \App\Models\CustomPageType $customPageType
     * @var \App\Models\CustomPages\CustomPageEntry|null $entry
     * @var \Illuminate\Support\Collection<int, \App\Models\CustomPages\CustomForm> $forms
     * @var string $submitLabel 送信ボタンの表示名
     */
    $entry ??= null;
    $values ??= collect();
@endphp

{{-- 左: タイトル・本文(記事型)または概要と詳細(固定ページ型)・カスタムフォーム・送信ボタン / 右: 公開の設定 --}}
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="mb-3">
                <label for="title" class="form-label">{{ __('タイトル') }}</label>
                <input id="title" type="text" name="title" value="{{ old('title', $entry?->title) }}" maxlength="255" required class="form-control">
            </div>

            @if ($customPageType->hasDetails())
                <div class="mb-3">
                    <label for="short_sentences" class="form-label">{{ __('概要') }}</label>
                    <input id="short_sentences" type="text" name="short_sentences" value="{{ old('short_sentences', $entry?->short_sentences) }}" maxlength="255" required class="form-control">
                </div>

                @php
                    $detailRows = \App\Support\RepeaterRows::build(
                        'details',
                        $details ?? [],
                        fn (array $row) => ['subTitle' => $row['sub_title'] ?? '', 'contents' => $row['contents'] ?? ''],
                        fn ($detail) => ['subTitle' => $detail->sub_title, 'contents' => $detail->contents],
                    );

                    // 新規登録時(入力値の復元もない場合)は、固定ページと同じく空の詳細ブロックを1つ表示する
                    if ($entry === null && old('details') === null) {
                        $detailRows = collect([(object) [
                            'index' => '0',
                            'id' => null,
                            'sortOrder' => 0,
                            'subTitle' => '',
                            'contents' => '',
                        ]]);
                    }
                @endphp

                {{-- 詳細は固定ページと同じ入力(single-pages.js の initSinglePageDetailRows())を使う --}}
                <div class="mb-3">
                    <label class="form-label mb-0">{{ __('詳細') }}</label>
                    <div class="form-text mb-2">{{ __('最大:max件まで登録できます。', ['max' => config('limits.single_page_details')]) }}</div>

                    <div id="single-page-detail-rows" data-next-index="{{ $detailRows->count() }}" data-max-rows="{{ config('limits.single_page_details') }}">
                        @foreach ($detailRows as $row)
                            @include('admin.single_pages._detail_row', [
                                'index' => $row->index,
                                'id' => $row->id,
                                'subTitle' => $row->subTitle,
                                'contents' => $row->contents,
                                'sortOrder' => $row->sortOrder,
                            ])
                        @endforeach
                    </div>

                    <button type="button" id="single-page-detail-add" class="btn btn-outline-secondary btn-sm">
                        {{ __('+ 詳細を追加') }}
                    </button>

                    <template id="single-page-detail-row-template">
                        @include('admin.single_pages._detail_row', [
                            'index' => '__INDEX__',
                            'id' => null,
                            'subTitle' => '',
                            'contents' => '',
                            'sortOrder' => 0,
                        ])
                    </template>
                </div>
            @else
                {{-- 本文は記事と同じリッチテキストエディタ(content-editor.js の initContentEditor())を使う --}}
                <div class="mb-3">
                    <label class="form-label is-required">{{ __('本文') }}</label>
                    <div id="content-editor" data-upload-url="{{ route('admin.articles.content-images') }}" style="height: 480px;"></div>
                    <textarea id="content-input" name="content" hidden>{{ old('content', $entry?->content) }}</textarea>
                </div>
            @endif
        </div>

        <div class="card mb-3">
            <h2 class="h6 mb-3">{{ __('カスタムフォーム') }}</h2>

            @forelse ($forms as $form)
                @include('admin.custom_pages._custom_field', [
                    'form' => $form,
                    'value' => old("custom_fields.{$form->id}", $values[$form->id] ?? null),
                ])
            @empty
                <p class="small text-muted mb-0">
                    {{ __('カスタムフォームの項目はまだありません。') }}
                    <a href="{{ route('admin.custom-page-types.edit', $customPageType) }}">{{ __('カスタムフォームの設定') }}</a>
                </p>
            @endforelse
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
            <a href="{{ route('admin.custom-pages.entries.index', $customPageType) }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            @unless ($customPageType->hasDetails())
                <div class="mb-3">
                    <label for="approval" class="form-label">{{ __('ステータス') }}</label>
                    <select id="approval" name="approval" class="form-select" required>
                        @foreach (\App\Enums\ArticleApprovalStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(old('approval', $entry?->approval?->value ?? \App\Enums\ArticleApprovalStatus::Draft->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless

            <div class="mb-3">
                <label for="publication_start_datetime" class="form-label">{{ __('公開開始') }}</label>
                <input
                    id="publication_start_datetime"
                    type="text"
                    name="publication_start_datetime"
                    value="{{ old('publication_start_datetime', ($entry?->publication_start_datetime ?? now())->format('Y-m-d H:i')) }}"
                    required
                    class="form-control"
                    data-role="datetime-picker"
                    autocomplete="off"
                >
            </div>

            <div class="mb-0">
                <label for="publication_end_datetime" class="form-label">{{ __('公開終了') }}</label>
                <input
                    id="publication_end_datetime"
                    type="text"
                    name="publication_end_datetime"
                    value="{{ old('publication_end_datetime', $entry?->publication_end_datetime?->format('Y-m-d H:i')) }}"
                    class="form-control"
                    data-role="datetime-picker"
                    autocomplete="off"
                >
                <div class="form-text">{{ __('未指定の場合は終了日時を設定しません。') }}</div>
            </div>
        </div>
    </div>
</div>
