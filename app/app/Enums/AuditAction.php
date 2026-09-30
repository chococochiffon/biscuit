<?php

namespace App\Enums;

/**
 * 監査ログの操作の種類。
 */
enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case StatusChanged = 'status_changed';
    case Reordered = 'reordered';
    case Uploaded = 'uploaded';
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case PasswordResetRequested = 'password_reset_requested';
    case PasswordReset = 'password_reset';
    case Invited = 'invited';
    case InvitationAccepted = 'invitation_accepted';

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => __('登録'),
            self::Updated => __('更新'),
            self::Deleted => __('削除'),
            self::StatusChanged => __('状態の変更'),
            self::Reordered => __('並び替え'),
            self::Uploaded => __('アップロード'),
            self::Login => __('ログイン'),
            self::Logout => __('ログアウト'),
            self::LoginFailed => __('ログイン失敗'),
            self::PasswordResetRequested => __('パスワード再設定の依頼'),
            self::PasswordReset => __('パスワード再設定'),
            self::Invited => __('招待'),
            self::InvitationAccepted => __('招待の受諾'),
        };
    }

    /**
     * 一覧で操作を見分けるためのバッジの色(Bootstrap の text-bg-*)。
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Created, self::Login, self::Invited => 'success',
            self::Updated, self::StatusChanged, self::Reordered, self::Uploaded, self::PasswordReset, self::InvitationAccepted => 'primary',
            self::Deleted, self::LoginFailed => 'danger',
            self::Logout, self::PasswordResetRequested => 'secondary',
        };
    }
}
