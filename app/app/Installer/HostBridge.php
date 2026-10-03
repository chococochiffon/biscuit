<?php

namespace App\Installer;

use Illuminate\Support\Facades\Storage;

/**
 * ブラウザ(Laravel)とホスト側の install.sh の境界。Web には Docker を操作する権限を持たせず、Laravel は決まった名前の合図
 * (ACTIONS のどれか)を local ディスクの installer/host-request.json に置くだけにする。install.sh はこのファイルを見て、
 * 合図ごとに決めてある処理だけを動かし、進み具合を installer/host-status.json に書く(秘密の値は書かない)。
 * ブラウザから命令の文字列は受け取らない。
 */
class HostBridge
{
    /**
     * install.sh が受け付ける合図。
     * - start-services: DB・公開側(chococo)・スケジューラーを起動し、ヘルスチェックのあと biscuit:install --step=application を動かす
     *
     * @var list<string>
     */
    public const ACTIONS = ['start-services'];

    public const REQUEST_FILE = InstallationState::DIRECTORY.'/host-request.json';

    public const STATUS_FILE = InstallationState::DIRECTORY.'/host-status.json';

    /**
     * 合図を置く(前の結果は消す)。
     */
    public function request(string $action): void
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException("Unknown installer action: {$action}");
        }

        Storage::disk('local')->delete(self::STATUS_FILE);
        Storage::disk('local')->put(self::REQUEST_FILE, json_encode(['action' => $action, 'requested_at' => now()->toIso8601String()]));
        InstallerLog::info('ホストに処理を頼みました。', ['action' => $action]);
    }

    /**
     * 合図を置いたまま、まだ install.sh が受け取っていないか。
     */
    public function isPending(): bool
    {
        return Storage::disk('local')->exists(self::REQUEST_FILE);
    }

    /**
     * install.sh が書いた進み具合(まだなければ null)。status は running・succeeded・failed、stage は今の処理の名前。
     *
     * @return array{action: string, status: string, stage: string|null, message: string|null, updated_at: string|null}|null
     */
    public function status(): ?array
    {
        $status = json_decode((string) Storage::disk('local')->get(self::STATUS_FILE), true);

        if (! is_array($status) || ! in_array($status['status'] ?? null, ['running', 'succeeded', 'failed'], true)) {
            return null;
        }

        return [
            'action' => (string) ($status['action'] ?? ''),
            'status' => $status['status'],
            'stage' => is_string($status['stage'] ?? null) ? $status['stage'] : null,
            'message' => is_string($status['message'] ?? null) ? mb_strimwidth($status['message'], 0, 500, '…') : null,
            'updated_at' => is_string($status['updated_at'] ?? null) ? $status['updated_at'] : null,
        ];
    }
}
