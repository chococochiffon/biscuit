<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\Administrator;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 操作ログ(監査ログ)の閲覧。スーパー管理者だけが使える(AppServiceProvider の view-audit-logs)。
 * ログは追記するだけで、画面からは変更・削除できない。
 */
class AuditLogController extends Controller
{
    /**
     * 操作ログを新しい順に一覧表示する。
     * GET パラメータで期間(from・to。日付)・操作者(actor。管理者の id)・対象の種類(subject_type)・対象の id(subject_id)・操作(action)で絞り込み、
     * 不正な値はリダイレクトせずに無視する。
     */
    public function index(Request $request): View
    {
        $filters = Validator::make($request->query(), [
            'from' => ['date_format:Y-m-d'],
            'to' => ['date_format:Y-m-d'],
            'actor' => ['integer'],
            'subject_type' => ['string', 'max:128'],
            'subject_id' => ['integer'],
            'action' => [Rule::enum(AuditAction::class)],
        ])->valid();

        $auditLogs = AuditLog::query()
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', $to.' 23:59:59'))
            ->when($filters['actor'] ?? null, fn ($query, $actor) => $query->where('actor_type', 'administrator')->where('actor_id', $actor))
            ->when($filters['subject_type'] ?? null, fn ($query, $subjectType) => $query->where('subject_type', $subjectType))
            ->when($filters['subject_id'] ?? null, fn ($query, $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->newest()
            ->paginate(config('limits.admin_per_page'))
            ->withQueryString();

        $administrators = Administrator::withTrashed()->orderBy('name')->get(['id', 'name', 'deleted_at']);
        $subjectTypes = AuditLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type')
            ->mapWithKeys(fn (string $subjectType) => [$subjectType => AuditLog::labelForSubjectType($subjectType)]);
        $isSearching = $filters !== [];

        return view('admin.audit_logs.index', compact('auditLogs', 'administrators', 'subjectTypes', 'filters', 'isSearching'));
    }

    /**
     * 操作ログ 1 件の詳細(変更前・変更後の値と補足)を表示する。
     */
    public function show(AuditLog $auditLog): View
    {
        return view('admin.audit_logs.show', compact('auditLog'));
    }
}
