<?php

namespace App\Installer;

/**
 * インストーラーの最初の段で、Biscuit を動かせる環境かを確かめる(PHP の版・拡張機能・書き込みの権限・APP_KEY)。
 * Docker で動いているか・各サービスの状態は、ホスト側の install.sh が確かめる。
 */
class RequirementChecker
{
    /**
     * 確かめた結果の一覧。
     *
     * @return list<array{key: string, label: string, ok: bool, message: string|null}>
     */
    public function check(): array
    {
        $results = [];
        $minimum = (string) config('installer.php_version');

        $results[] = $this->result('php', __('PHP :version 以上', ['version' => $minimum]), version_compare(PHP_VERSION, $minimum, '>='), __('今の PHP は :version です。', ['version' => PHP_VERSION]));

        foreach (config('installer.extensions') as $extension) {
            $results[] = $this->result("extension.{$extension}", __('PHP の拡張機能 :name', ['name' => $extension]), extension_loaded($extension), __('拡張機能 :name を有効にしてください。', ['name' => $extension]));
        }

        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache')] as $label => $path) {
            $results[] = $this->result("writable.{$label}", __(':path に書き込める', ['path' => $label]), is_dir($path) && is_writable($path), __(':path の所有者と権限を確認してください。', ['path' => $label]));
        }

        $envPath = base_path('.env');
        $results[] = $this->result('writable.env', __('.env に書き込める'), is_file($envPath) ? is_writable($envPath) : is_writable(base_path()), __('.env の所有者と権限を確認してください。'));
        $results[] = $this->result('app_key', __('アプリケーションキー(APP_KEY)がある'), (string) config('app.key') !== '', __('install.sh からインストーラーを起動してください(APP_KEY を作ります)。'));

        return $results;
    }

    /**
     * すべて満たしているか。
     *
     * @param  list<array{ok: bool}>  $results
     */
    public static function passes(array $results): bool
    {
        return array_filter($results, fn (array $result) => ! $result['ok']) === [];
    }

    /**
     * @return array{key: string, label: string, ok: bool, message: string|null}
     */
    private function result(string $key, string $label, bool $ok, string $message): array
    {
        return ['key' => $key, 'label' => $label, 'ok' => $ok, 'message' => $ok ? null : $message];
    }
}
