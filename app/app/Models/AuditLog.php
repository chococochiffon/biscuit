<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/**
 * 監査ログ(誰が・いつ・何に・何をしたか)。記録は Support\AuditLogger を使う。
 * 追記するだけで変更・削除しないため、論理削除の方針の例外として SoftDeletes を使わず、updated_at も持たない。
 */
#[Fillable(['actor_type', 'actor_id', 'actor_name', 'action', 'subject_type', 'subject_id', 'subject_label', 'changes', 'metadata', 'ip_address', 'user_agent', 'route_name'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'changes' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * 記録したログは変更・削除できない(追記だけにする)。
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('監査ログは変更できません。'));
        static::deleting(fn () => throw new LogicException('監査ログは削除できません。'));
    }

    /**
     * 新しい順(記録日時、同じなら id の大きい順)に並べる。
     */
    #[Scope]
    protected function newest(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * 対象の種類の表示名(例: article → 記事、custom_page:recipe → カスタムページ(recipe))。
     */
    public function subjectTypeLabel(): ?string
    {
        return $this->subject_type === null ? null : self::labelForSubjectType($this->subject_type);
    }

    /**
     * 対象の種類の表示名を返す(一覧の絞り込みの選択肢にも使う)。
     */
    public static function labelForSubjectType(string $subjectType): string
    {
        if (str_starts_with($subjectType, 'custom_page:')) {
            return __('カスタムページ(:name)', ['name' => Str::after($subjectType, 'custom_page:')]);
        }

        return match ($subjectType) {
            'administrator' => __('管理者'),
            'article' => __('記事'),
            'article_content_image' => __('記事本文の画像'),
            'content_model_relation' => __('データ種別紐付け'),
            'custom_page_type' => __('カスタムページの種類'),
            'gallery_category' => __('ギャラリーの分類'),
            'gallery_image' => __('ギャラリー画像'),
            'layout' => __('レイアウト'),
            'question_answer' => __('Q&A'),
            'single_page' => __('固定ページ'),
            'site_setting' => __('サイト設定'),
            'tag' => __('タグ'),
            'user' => __('ユーザー'),
            default => $subjectType,
        };
    }
}
