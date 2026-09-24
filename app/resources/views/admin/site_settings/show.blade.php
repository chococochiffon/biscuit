@extends('layouts.admin')

@section('title', __('サイト設定詳細'))

@section('content')
    <div class="mx-auto" style="max-width: 80rem;">
        <div class="mb-4 d-flex align-items-center justify-content-between" style="max-width: 40rem;">
            <h1 class="h5 mb-0">{{ __('サイト設定詳細') }}</h1>
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('admin.site-settings.edit', $siteSetting) }}" class="link-primary">{{ __('編集する') }}</a>
            </div>
        </div>

        <div class="card" style="max-width: 40rem;">
            <dl class="row mb-0 p-3">
                <dt class="col-4 text-muted fw-normal">{{ __('サイトタイトル') }}</dt>
                <dd class="col-8">{{ $siteSetting->site_title }}</dd>

                <dt class="col-4 text-muted fw-normal">{{ __('説明') }}</dt>
                <dd class="col-8">{{ $siteSetting->description }}</dd>

                <dt class="col-4 text-muted fw-normal">{{ __('サイトアイコン') }}</dt>
                <dd class="col-8">
                    <img
                        src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($siteSetting->site_icon ?: \App\Models\SiteSetting::DEFAULT_SITE_ICON_PATH) }}"
                        alt="{{ __('サイトアイコン') }}"
                        class="img-thumbnail"
                        style="width: 96px; height: 96px; object-fit: cover;"
                    >
                </dd>

                <dt class="col-4 text-muted fw-normal">{{ __('サイト画像') }}</dt>
                <dd class="col-8 mb-0">
                    <img
                        src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($siteSetting->site_image ?: \App\Models\SiteSetting::DEFAULT_SITE_IMAGE_PATH) }}"
                        alt="{{ __('サイト画像') }}"
                        class="img-thumbnail"
                        style="width: 240px; height: 160px; object-fit: cover;"
                    >
                </dd>
            </dl>
        </div>

        <h2 class="h6 mt-4 mb-3">{{ __('SNSリンク') }}</h2>

        <div class="card" style="max-width: 40rem;">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('サービス') }}</th>
                        <th>{{ __('表示名') }}</th>
                        <th>{{ __('URL') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($socialLinks as $socialLink)
                        <tr>
                            <td class="text-nowrap">{{ $socialLink->service->label() }}</td>
                            <td>{{ $socialLink->name }}</td>
                            <td class="text-break"><a href="{{ $socialLink->url }}" target="_blank" rel="noopener">{{ $socialLink->url }}</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">{{ __('SNSリンクが登録されていません。') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h2 class="h6 mt-4 mb-3">{{ __('API設定') }}</h2>

        <div class="card">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('呼び出し名') }}</th>
                        <th>{{ __('見出し') }}</th>
                        <th>{{ __('表示箇所') }}</th>
                        <th>{{ __('呼び出し方') }}</th>
                        <th>{{ __('データ種別') }}</th>
                        <th>{{ __('表示件数') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($callContents as $callContent)
                        <tr>
                            <td>{{ $callContent->call_name }}</td>
                            <td>
                                {{ $callContent->title }}
                                @if ($callContent->subtitle)
                                    <div class="small text-body-secondary">{{ $callContent->subtitle }}</div>
                                @endif
                            </td>
                            <td>{{ $callContent->place->label() }}</td>
                            <td>{{ $callContent->call_type->label() }}</td>
                            <td>{{ $callContent->contentModelRelation->content_type->label() }} / {{ $callContent->contentModelRelation->model_name }}</td>
                            <td>{{ $callContent->view_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('API設定が登録されていません。') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
