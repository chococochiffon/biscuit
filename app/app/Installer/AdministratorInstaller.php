<?php

namespace App\Installer;

use App\Enums\AdministratorRole;
use App\Models\Administrator;

/**
 * 管理者の段: 最初の管理者(スーパー管理者)を作る。固定の管理者(admin@example.com など)は作らず、必ずここで入力してもらう。
 * 作ったあとは段を済みにし、もう一度呼ばれても 2 人目は作らない。
 */
class AdministratorInstaller
{
    public function __construct(private InstallationState $state) {}

    public function create(string $name, string $email, string $password): Administrator
    {
        if ($this->state->isCompleted(InstallerStep::Administrator) && ($existing = Administrator::query()->orderBy('id')->first()) !== null) {
            return $existing;
        }

        $administrator = Administrator::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => AdministratorRole::SuperAdmin,
        ]);

        $this->state->markCompleted(InstallerStep::Administrator);
        InstallerLog::info('最初の管理者(スーパー管理者)を作りました。', ['email' => $email, 'password' => $password]);

        return $administrator;
    }
}
