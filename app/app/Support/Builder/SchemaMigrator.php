<?php

namespace App\Support\Builder;

use InvalidArgumentException;

/**
 * ページビルダーの内容(ノードの木の JSON)を、古い構造の版から今の版(CURRENT_VERSION)へ変換する。
 * 構造を変えるときは CURRENT_VERSION を上げ、migrations() に「前の版 → 次の版」の変換を足す。
 * 保存済みの JSON は書き換えず、読み出すときにこれで今の版へそろえる。
 */
final class SchemaMigrator
{
    /**
     * 今の構造の版。
     */
    public const CURRENT_VERSION = 1;

    /**
     * 版ごとの変換(キーの版の内容を受け取り、次の版の内容を返す。version は migrate() が書き換える)。
     *
     * @return array<int, callable(array<string, mixed>): array<string, mixed>>
     */
    private function migrations(): array
    {
        return [];
    }

    /**
     * 内容を今の版へ変換して返す。版が不明・今の版より新しい場合は変換できないため例外にする。
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function migrate(array $content): array
    {
        $version = $content['version'] ?? null;

        if (! is_int($version) || $version < 1 || $version > self::CURRENT_VERSION) {
            throw new InvalidArgumentException('Unsupported page builder schema version: '.json_encode($version));
        }

        $migrations = $this->migrations();

        while ($version < self::CURRENT_VERSION) {
            $content = $migrations[$version]($content);
            $content['version'] = ++$version;
        }

        return $content;
    }
}
