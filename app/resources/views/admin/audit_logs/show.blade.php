@extends('layouts.admin')

@section('title', __('操作ログの詳細'))

@section('content')
    <div class="mx-auto" style="max-width: 80rem;">
        <div class="mb-4 d-flex align-items-center justify-content-between">
            <h1 class="h5 mb-0">{{ __('操作ログの詳細') }}</h1>
            <a href="{{ route('admin.audit-logs.index') }}" class="text-secondary">{{ __('一覧へ戻る') }}</a>
        </div>

        <div class="card mb-4">
            <dl class="row mb-0 p-3 detail-list">
                <x-admin.detail-row :label="__('日時')" :label-cols="3">{{ $auditLog->created_at?->format('Y/m/d H:i:s') }}</x-admin.detail-row>
                <x-admin.detail-row :label="__('操作者')" :label-cols="3">{{ $auditLog->actor_name ?? '—' }}</x-admin.detail-row>
                <x-admin.detail-row :label="__('操作')" :label-cols="3">
                    <span class="badge text-bg-{{ $auditLog->action->badgeColor() }}">{{ $auditLog->action->label() }}</span>
                </x-admin.detail-row>
                <x-admin.detail-row :label="__('対象')" :label-cols="3">
                    @if ($auditLog->subject_type)
                        {{ $auditLog->subjectTypeLabel() }}@if ($auditLog->subject_id) (#{{ $auditLog->subject_id }})@endif
                        {{ $auditLog->subject_label }}
                        @if ($auditLog->subject_id)
                            <a
                                href="{{ route('admin.audit-logs.index', ['subject_type' => $auditLog->subject_type, 'subject_id' => $auditLog->subject_id]) }}"
                                class="ms-2 small"
                            >{{ __('この対象の履歴') }}</a>
                        @endif
                    @else
                        —
                    @endif
                </x-admin.detail-row>
                <x-admin.detail-row :label="__('IPアドレス')" :label-cols="3">{{ $auditLog->ip_address }}</x-admin.detail-row>
                <x-admin.detail-row :label="__('ブラウザ')" :label-cols="3" class="small text-break">{{ $auditLog->user_agent }}</x-admin.detail-row>
                <x-admin.detail-row :label="__('画面')" :label-cols="3" class="small">{{ $auditLog->route_name }}</x-admin.detail-row>
            </dl>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h6">{{ __('変更内容') }}</h2>
                <div class="form-text mb-2">{{ __(':max文字を超える値は先頭だけを残しています。', ['max' => \App\Support\AuditLogger::MAX_VALUE_LENGTH]) }}</div>

                @if ($auditLog->changes)
                    <table class="table table-sm align-top mb-0">
                        <thead>
                            <tr>
                                <th style="width: 20%;">{{ __('項目') }}</th>
                                <th style="width: 40%;">{{ __('変更前') }}</th>
                                <th style="width: 40%;">{{ __('変更後') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($auditLog->changes as $field => [$old, $new])
                                <tr>
                                    <td class="small text-nowrap">{{ $field }}</td>
                                    <td class="small text-break audit-log-value">{{ $old ?? '—' }}</td>
                                    <td class="small text-break audit-log-value">{{ $new ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted small mb-0">{{ __('変更した項目はありません。') }}</p>
                @endif
            </div>
        </div>

        @if ($auditLog->metadata)
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h6">{{ __('補足') }}</h2>
                    <pre class="small mb-0 audit-log-value">{{ json_encode($auditLog->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        @endif
    </div>
@endsection
