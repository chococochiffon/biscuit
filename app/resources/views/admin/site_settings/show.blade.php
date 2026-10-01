@extends('layouts.admin')

@section('title', __('サイト設定詳細'))

@section('content')
    <div class="mx-auto" style="max-width: 80rem;">
        <div class="mb-4 d-flex align-items-center justify-content-between">
            <h1 class="h5 mb-0">{{ __('サイト設定詳細') }}</h1>
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('admin.site-settings.edit', $siteSetting) }}" class="link-primary">{{ __('編集する') }}</a>
            </div>
        </div>

        {{-- 左: 基本設定・画像 / 右: トップスライダー画像・SNSリンク（API設定は下に全幅で表示） --}}
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <dl class="row mb-0 p-3 detail-list">
                        <x-admin.detail-row :label="__('サイトタイトル')">{{ $siteSetting->site_title }}</x-admin.detail-row>

                        <x-admin.detail-row :label="__('説明')">{{ $siteSetting->description }}</x-admin.detail-row>

                        <x-admin.detail-row :label="__('フロントのURL')" class="text-break">
                            @if ($siteSetting->front_url)
                                <a href="{{ $siteSetting->front_url }}" target="_blank" rel="noopener">{{ $siteSetting->front_url }}</a>
                            @endif
                        </x-admin.detail-row>

                        <x-admin.detail-row :label="__('APIのURL')" class="text-break">
                            @if ($siteSetting->api_url)
                                <a href="{{ $siteSetting->api_url }}" target="_blank" rel="noopener">{{ $siteSetting->api_url }}</a>
                            @endif
                        </x-admin.detail-row>

                        <x-admin.detail-row :label="__('トップでページビルダーを使う')">{{ $siteSetting->top_use_builder ? __('使う') : __('使わない') }}</x-admin.detail-row>

                        <x-admin.detail-row :label="__('サイトアイコン')">
                            <img
                                src="{{ $siteSetting->site_icon_url }}"
                                alt="{{ __('サイトアイコン') }}"
                                class="img-thumbnail"
                                style="width: 96px; height: 96px; object-fit: cover;"
                            >
                        </x-admin.detail-row>

                        <x-admin.detail-row :label="__('サイト画像')">
                            <img
                                src="{{ $siteSetting->site_image_url }}"
                                alt="{{ __('サイト画像') }}"
                                class="img-thumbnail"
                                style="width: 240px; height: 160px; object-fit: cover;"
                            >
                        </x-admin.detail-row>
                    </dl>
                </div>
            </div>

            <div class="col-lg-6">
                <h2 class="h6 mb-3">{{ __('トップスライダー画像') }}</h2>

                <div class="card">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('画像') }}</th>
                                <th>{{ __('リンク先URL') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topSliderImages as $topSliderImage)
                                <tr>
                                    <td style="width: 200px;">
                                        <img src="{{ $topSliderImage->top_image_url }}" alt="{{ __('トップスライダー画像') }}" class="img-thumbnail" style="width: 192px; height: 108px; object-fit: cover;">
                                    </td>
                                    <td class="text-break">
                                        @if ($topSliderImage->url)
                                            <a href="{{ $topSliderImage->url }}" target="_blank" rel="noopener">{{ $topSliderImage->url }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <x-admin.empty-row colspan="2">{{ __('トップスライダー画像が登録されていません。') }}</x-admin.empty-row>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h2 class="h6 mt-4 mb-3">{{ __('SNSリンク') }}</h2>

                <div class="card">
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
                                <x-admin.empty-row colspan="3">{{ __('SNSリンクが登録されていません。') }}</x-admin.empty-row>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
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
                        <x-admin.empty-row colspan="6">{{ __('API設定が登録されていません。') }}</x-admin.empty-row>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
