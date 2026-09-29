@extends('layouts.admin')

@section('title', __('操作ログ'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('操作ログ') }}</h1>
    </div>

    <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="card mb-3 admin-search-card">
        @include('admin.partials._search_toggle', ['target' => 'audit-log-search-body', 'isSearching' => $isSearching])

        <div id="audit-log-search-body" @class(['collapse', 'show' => $isSearching])>
            <div class="d-flex flex-wrap gap-3 align-items-end pt-3">
                <div>
                    <label class="form-label small">{{ __('期間') }}</label>
                    <div class="d-flex align-items-center gap-1">
                        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm" aria-label="{{ __('開始日') }}">
                        <span class="text-muted">〜</span>
                        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm" aria-label="{{ __('終了日') }}">
                    </div>
                </div>

                <div>
                    <label for="search-actor" class="form-label small">{{ __('操作者') }}</label>
                    <select id="search-actor" name="actor" class="form-select form-select-sm form-select-auto">
                        <option value="">{{ __('すべて') }}</option>
                        @foreach ($administrators as $administrator)
                            <option value="{{ $administrator->id }}" @selected((string) ($filters['actor'] ?? '') === (string) $administrator->id)>
                                {{ $administrator->name }}@if ($administrator->trashed()) {{ __('(削除済み)') }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="search-subject-type" class="form-label small">{{ __('対象の種類') }}</label>
                    <select id="search-subject-type" name="subject_type" class="form-select form-select-sm form-select-auto">
                        <option value="">{{ __('すべて') }}</option>
                        @foreach ($subjectTypes as $subjectType => $subjectTypeLabel)
                            <option value="{{ $subjectType }}" @selected(($filters['subject_type'] ?? '') === $subjectType)>{{ $subjectTypeLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="search-action" class="form-label small">{{ __('操作') }}</label>
                    <select id="search-action" name="action" class="form-select form-select-sm form-select-auto">
                        <option value="">{{ __('すべて') }}</option>
                        @foreach (\App\Enums\AuditAction::cases() as $action)
                            <option value="{{ $action->value }}" @selected(($filters['action'] ?? '') === $action->value)>{{ $action->label() }}</option>
                        @endforeach
                    </select>
                </div>

                @if (isset($filters['subject_id']))
                    <input type="hidden" name="subject_id" value="{{ $filters['subject_id'] }}">
                @endif

                <div class="d-flex gap-2 text-nowrap">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('検索') }}</button>
                    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('クリア') }}</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('日時') }}</th>
                    <th>{{ __('操作者') }}</th>
                    <th>{{ __('操作') }}</th>
                    <th>{{ __('対象') }}</th>
                    <th>{{ __('変更した項目') }}</th>
                    <th>{{ __('IPアドレス') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($auditLogs as $auditLog)
                    <tr>
                        <td class="text-nowrap">{{ $auditLog->created_at?->format('Y/m/d H:i:s') }}</td>
                        <td>{{ $auditLog->actor_name ?? '—' }}</td>
                        <td><span class="badge text-bg-{{ $auditLog->action->badgeColor() }}">{{ $auditLog->action->label() }}</span></td>
                        <td>
                            @if ($auditLog->subject_type)
                                <div class="small text-muted">{{ $auditLog->subjectTypeLabel() }}</div>
                            @endif
                            {{ $auditLog->subject_label ?? ($auditLog->metadata['email'] ?? '') }}
                        </td>
                        <td class="small text-muted">{{ \Illuminate\Support\Str::limit(implode(', ', array_keys($auditLog->changes ?? [])), 60) }}</td>
                        <td class="small text-nowrap">{{ $auditLog->ip_address }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.audit-logs.show', $auditLog) }}" class="btn btn-sm btn-outline-secondary">{{ __('詳細') }}</a>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="7">{{ __('該当する操作ログがありません。') }}</x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $auditLogs->links() }}
    </div>
@endsection
