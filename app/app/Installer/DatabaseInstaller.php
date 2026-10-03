<?php

namespace App\Installer;

/**
 * データベースの段: 入力された DB 名・ユーザー・パスワードを .env の DB_* に書く(Docker Compose も同じ値から MySQL の DB とユーザーを作る)。
 * MySQL の root のパスワードは自動で生成し、.env の DB_ROOT_PASSWORD にだけ書く(画面には出さない)。
 * 接続の確認は、次の段(アプリケーション)でサービスを起動してから行う(MySQL は最初の起動でこの値から DB とユーザーを作るため)。
 */
class DatabaseInstaller
{
    public function __construct(private EnvironmentWriter $environment, private InstallationState $state) {}

    public function configure(string $database, string $username, string $password): void
    {
        $this->environment->set([
            'DB_CONNECTION' => config('installer.database.connection'),
            'DB_HOST' => config('installer.database.host'),
            'DB_PORT' => config('installer.database.port'),
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $password,
            'DB_ROOT_PASSWORD' => PasswordPolicy::generate(),
        ]);

        $this->state->put('db_database', $database);
        $this->state->put('db_username', $username);
        $this->state->markCompleted(InstallerStep::Database);

        InstallerLog::info('データベースの設定を .env に書きました。', ['database' => $database, 'username' => $username, 'password' => $password]);
    }
}
