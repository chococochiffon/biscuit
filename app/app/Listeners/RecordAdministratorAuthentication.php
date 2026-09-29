<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * 管理画面(admin ガード)のログイン・ログアウト・ログイン失敗を監査ログに残す。
 * ログイン失敗は、入力されたメールアドレスだけを残す(パスワードは残さない)。
 * app/Listeners に置いた handle で始まるメソッドは、引数のイベントの型から Laravel が自動で登録する。
 */
class RecordAdministratorAuthentication
{
    private const GUARD = 'admin';

    public function handleLogin(Login $event): void
    {
        if ($event->guard === self::GUARD) {
            AuditLogger::record(AuditAction::Login, $event->user, actor: $event->user);
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->guard === self::GUARD && $event->user !== null) {
            AuditLogger::record(AuditAction::Logout, $event->user, actor: $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        if ($event->guard === self::GUARD) {
            AuditLogger::record(AuditAction::LoginFailed, metadata: ['email' => (string) ($event->credentials['email'] ?? '')]);
        }
    }
}
