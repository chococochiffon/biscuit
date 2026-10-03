<?php

namespace App\Installer;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * インストールの途中の状態。DB ができる前から使うため、local ディスクの installer/ にファイルで持つ
 * (ブラウザを閉じても、終えた段と入力した値から再開できる)。
 *
 * - state.json: 終えた段と、秘密でない入力値(DB 名・サイト名など)
 * - secrets: パスワードなどの秘密の値。APP_KEY で暗号化して保存し、平文では残さない
 *
 * インストールを終えたら clear() で消す(InstallerManager::lock())。
 */
class InstallationState
{
    public const DIRECTORY = 'installer';

    private const STATE_FILE = self::DIRECTORY.'/state.json';

    private const SECRETS_FILE = self::DIRECTORY.'/secrets';

    /**
     * 段を終えたか。
     */
    public function isCompleted(InstallerStep $step): bool
    {
        return in_array($step->value, $this->read()['completed'], true);
    }

    /**
     * インストールの途中か(どれかの段を終えている)。
     */
    public function hasProgress(): bool
    {
        return $this->read()['completed'] !== [];
    }

    /**
     * 段を終えたことを残す。
     */
    public function markCompleted(InstallerStep $step): void
    {
        $state = $this->read();
        $state['completed'] = array_values(array_unique([...$state['completed'], $step->value]));
        $this->write($state);
    }

    /**
     * 段を終えていないことにする(その段から先をやり直すとき)。
     */
    public function markIncomplete(InstallerStep $step): void
    {
        $state = $this->read();
        $state['completed'] = array_values(array_diff($state['completed'], [$step->value]));
        $this->write($state);
    }

    /**
     * 秘密でない入力値。
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->read()['values'][$key] ?? $default;
    }

    /**
     * 秘密でない入力値を残す(パスワードなどは putSecret() を使う)。
     */
    public function put(string $key, mixed $value): void
    {
        $state = $this->read();
        $state['values'][$key] = $value;
        $this->write($state);
    }

    /**
     * 秘密の値(読めなければ null)。
     */
    public function secret(string $key): ?string
    {
        return $this->readSecrets()[$key] ?? null;
    }

    /**
     * 秘密の値を、APP_KEY で暗号化して残す。
     */
    public function putSecret(string $key, string $value): void
    {
        $secrets = $this->readSecrets();
        $secrets[$key] = $value;
        Storage::disk('local')->put(self::SECRETS_FILE, Crypt::encryptString(json_encode($secrets)));
    }

    /**
     * 状態をすべて消す(インストールを終えたとき)。
     */
    public function clear(): void
    {
        Storage::disk('local')->delete([self::STATE_FILE, self::SECRETS_FILE]);
    }

    /**
     * @return array{completed: list<string>, values: array<string, mixed>}
     */
    private function read(): array
    {
        $state = json_decode((string) Storage::disk('local')->get(self::STATE_FILE), true);

        return [
            'completed' => is_array($state['completed'] ?? null) ? array_values(array_filter($state['completed'], is_string(...))) : [],
            'values' => is_array($state['values'] ?? null) ? $state['values'] : [],
        ];
    }

    /**
     * @param  array{completed: list<string>, values: array<string, mixed>}  $state
     */
    private function write(array $state): void
    {
        Storage::disk('local')->put(self::STATE_FILE, json_encode([...$state, 'updated_at' => now()->toIso8601String()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, string>
     */
    private function readSecrets(): array
    {
        $encrypted = Storage::disk('local')->get(self::SECRETS_FILE);

        if (! is_string($encrypted) || $encrypted === '') {
            return [];
        }

        try {
            $secrets = json_decode(Crypt::decryptString($encrypted), true);
        } catch (DecryptException) {
            // APP_KEY が変わったなどで読めなければ、秘密の値は入力し直してもらう
            return [];
        }

        return is_array($secrets) ? array_filter($secrets, is_string(...)) : [];
    }
}
