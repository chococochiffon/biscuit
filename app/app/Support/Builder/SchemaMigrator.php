<?php

namespace App\Support\Builder;

use InvalidArgumentException;

/**
 * ページビルダーの内容(ノードの木の JSON)を、古い構造の版から新しい版へ変換する。
 * 構造を変えるときは版を上げ、migrations() に「前の版 → 次の版」の変換を足す。
 * 保存済みの JSON は書き換えず、読み出すときにこれでそろえる。
 *
 * 例外として、v1(行・カラムで流し込む配置)→ v2(自由配置。ブロックを座標で置く)は、描いたときの高さを測らないと座標を決められないため
 * ここでは変換せず、v1 のまま返す(変換は管理画面のエディタが行う。公開側は v1 も描く)。
 */
final class SchemaMigrator
{
    /**
     * 行・カラムで流し込む配置の版。保存はできず(BuilderValidator)、公開中の内容と、エディタが変換する前の内容(テンプレート・版の履歴・
     * 書き出したファイル)にだけ残る。
     */
    public const LEGACY_VERSION = 1;

    /**
     * 新しく作る内容の版(空の下書き・テンプレートなど)。
     */
    public const CURRENT_VERSION = 2;

    /**
     * 自由配置(ノードの layout)を使う版。
     */
    public const FREE_LAYOUT_VERSION = 2;

    /**
     * 受け付ける一番新しい版。
     */
    public const LATEST_VERSION = 2;

    /**
     * 版ごとの変換(キーの版の内容を受け取り、次の版の内容を返す。version は migrate() が書き換える)。
     * 変換のない版(v1)は、その版のまま返す。
     *
     * @return array<int, callable(array<string, mixed>): array<string, mixed>>
     */
    private function migrations(): array
    {
        return [];
    }

    /**
     * 内容を変換できるところまで新しい版へ変換して返す。版が不明・受け付ける版より新しい場合は例外にする。
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function migrate(array $content): array
    {
        $version = $content['version'] ?? null;

        if (! is_int($version) || $version < 1 || $version > self::LATEST_VERSION) {
            throw new InvalidArgumentException('Unsupported page builder schema version: '.json_encode($version));
        }

        $migrations = $this->migrations();

        while (isset($migrations[$version])) {
            $content = $migrations[$version]($content);
            $content['version'] = ++$version;
        }

        return $content;
    }
}
