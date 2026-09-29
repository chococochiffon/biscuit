<?php

namespace App\Support;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\CustomPages\CustomPageEntry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 監査ログ(誰が・いつ・何に・何をしたか)を記録する。
 * 管理画面の操作ごとに 1 件を、保存処理と同じトランザクションの中で記録する(取り消した操作はログにも残らない)。
 * 操作者はログイン中の管理者(admin ガード)、いなければ API トークンでログイン中のユーザー(sanctum ガード。chococo のマイページ)。
 * IP アドレス・User-Agent・ルート名は現在のリクエストから取る。
 */
class AuditLogger
{
    /**
     * 変更内容に残す文字列の最大文字数(本文の HTML など、これより長い値は切り詰める)。
     */
    public const MAX_VALUE_LENGTH = 1000;

    /**
     * 変更内容に残さない項目(自動で入る値と秘密の値。モデルの $hidden と unique_ で始まる生成カラムも残さない)。
     *
     * @var list<string>
     */
    private const EXCLUDED_ATTRIBUTES = ['id', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'];

    /**
     * トランザクションの中で $create を実行して登録したモデルを返し、登録を記録する。
     *
     * @template TModel of Model
     *
     * @param  callable(): TModel  $create
     * @return TModel
     */
    public static function createWithLog(callable $create): Model
    {
        return DB::transaction(function () use ($create) {
            $model = $create();
            self::created($model);

            return $model;
        });
    }

    /**
     * トランザクションの中で $update を実行して、更新前後の差分を記録する。
     *
     * @param  callable(): mixed  $update
     * @param  array<string, mixed>  $metadata
     */
    public static function updateWithLog(Model $model, callable $update, array $metadata = [], AuditAction $action = AuditAction::Updated): void
    {
        DB::transaction(function () use ($model, $update, $metadata, $action) {
            $before = self::snapshot($model);
            $update();
            self::updated($model, $before, $metadata, $action);
        });
    }

    /**
     * トランザクションの中でモデルを削除(論理削除)し、削除を記録する。削除に伴う処理があれば $delete に渡す。
     *
     * @param  (callable(): mixed)|null  $delete
     * @param  array<string, mixed>  $metadata
     */
    public static function deleteWithLog(Model $model, ?callable $delete = null, array $metadata = []): void
    {
        DB::transaction(function () use ($model, $delete, $metadata) {
            $delete === null ? $model->delete() : $delete();
            self::deleted($model, $metadata);
        });
    }

    /**
     * 登録を記録する。変更内容には登録した値を [null, 値] で残す。
     * 本体の列以外に残したい値(記事のタグなど)は $extra に渡す。
     *
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $extra
     */
    public static function created(Model $subject, array $metadata = [], array $extra = []): AuditLog
    {
        return self::record(AuditAction::Created, $subject, changes: self::diff([], self::snapshot($subject, $extra)), metadata: $metadata);
    }

    /**
     * 更新を記録する。$before には更新前に snapshot() で取った値を渡す(更新後の値は $subject から取る)。
     * 本体の列以外に比べたい値(記事のタグなど)は、更新前は snapshot() の $extra、更新後は $extra に渡す。
     *
     * @param  array<string, string|null>  $before
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $extra
     */
    public static function updated(Model $subject, array $before, array $metadata = [], AuditAction $action = AuditAction::Updated, array $extra = []): AuditLog
    {
        return self::record($action, $subject, changes: self::diff($before, self::snapshot($subject, $extra)), metadata: $metadata);
    }

    /**
     * 削除(論理削除)を記録する。変更内容には削除した時点の値を [値, null] で残す。
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function deleted(Model $subject, array $metadata = []): AuditLog
    {
        return self::record(AuditAction::Deleted, $subject, changes: self::diff(self::snapshot($subject), []), metadata: $metadata);
    }

    /**
     * 監査ログを 1 件記録する。対象はモデル(種類・id・名前をモデルから決める)か、モデルのない対象の種類(例: layout)を渡す。
     *
     * @param  array<string, array{0: string|null, 1: string|null}>  $changes
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        AuditAction $action,
        Model|string|null $subject = null,
        ?string $label = null,
        array $changes = [],
        array $metadata = [],
        ?Authenticatable $actor = null,
    ): AuditLog {
        $actor ??= Auth::guard('admin')->user() ?? Auth::guard('sanctum')->user();
        $request = request();

        return AuditLog::query()->create([
            'actor_type' => $actor ? Str::snake(class_basename($actor)) : null,
            'actor_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $actor?->getAttribute('name'),
            'action' => $action,
            'subject_type' => $subject instanceof Model ? self::subjectType($subject) : $subject,
            'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
            'subject_label' => $label ?? ($subject instanceof Model ? self::subjectLabel($subject) : null),
            'changes' => $changes === [] ? null : $changes,
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 509),
            'route_name' => $request->route()?->getName(),
        ]);
    }

    /**
     * 変更内容を比べるための、モデルの今の値(項目名 → 文字列。長い値は切り詰める)。
     * 本体の列以外に比べたい値(記事のタグなど)は $extra に足す。
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, string|null>
     */
    public static function snapshot(Model $model, array $extra = []): array
    {
        $excluded = [...self::EXCLUDED_ATTRIBUTES, ...$model->getHidden()];

        return collect($model->getAttributes())
            ->reject(fn ($value, string $key) => in_array($key, $excluded, true) || str_starts_with($key, 'unique_'))
            ->merge($extra)
            ->map(fn ($value) => self::normalize($value))
            ->all();
    }

    /**
     * 値が変わった項目だけを、項目名 → [変更前, 変更後] で返す。
     *
     * @param  array<string, string|null>  $before
     * @param  array<string, string|null>  $after
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old !== $new) {
                $changes[$key] = [$old, $new];
            }
        }

        return $changes;
    }

    /**
     * 対象の種類(例: article。カスタムページは custom_page:種類の名前)。
     */
    public static function subjectType(Model $model): string
    {
        return $model instanceof CustomPageEntry
            ? 'custom_page:'.$model->customPageType()->name
            : Str::snake(class_basename($model));
    }

    /**
     * 対象の名前(タイトル・名前など。どれもなければ #id)。
     */
    private static function subjectLabel(Model $model): string
    {
        foreach (['title', 'name', 'tag_name', 'site_title', 'label', 'short_question_text', 'model_name'] as $attribute) {
            if (filled($model->getAttribute($attribute))) {
                return Str::limit((string) $model->getAttribute($attribute), 250);
            }
        }

        return '#'.$model->getKey();
    }

    private static function normalize(mixed $value): ?string
    {
        $value = match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            $value instanceof \BackedEnum => (string) $value->value,
            default => (string) $value,
        };

        // Str::limit() は表示幅で数え全角文字を 2 文字分とするため、文字数で切る
        return $value === null || mb_strlen($value) <= self::MAX_VALUE_LENGTH
            ? $value
            : mb_substr($value, 0, self::MAX_VALUE_LENGTH).'...';
    }
}
