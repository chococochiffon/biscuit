<?php

namespace App\Support\Backup;

use RuntimeException;

/**
 * バックアップの tar を gzip で圧縮・展開する。PharData の compress()・tar.gz を開く処理は中身を丸ごとメモリに載せ、
 * 画像が多いと CLI の memory_limit(128MB)を超えて PHP ごと落ちる(catch も finally も動かない)ため、少しずつ読み書きする。
 */
class Gzip
{
    /** 1 回に読み書きする大きさ */
    private const CHUNK = 1024 * 1024;

    public static function compress(string $source, string $target): void
    {
        $from = @fopen($source, 'rb');
        $to = @gzopen($target, 'wb6');
        if ($from === false || $to === false) {
            self::close($from, 'fclose', $to, 'gzclose');
            throw new RuntimeException("{$source} を圧縮できません。");
        }

        $written = true;
        while ($written && ! feof($from)) {
            $chunk = fread($from, self::CHUNK);
            $written = $chunk !== false && ($chunk === '' || gzwrite($to, $chunk) !== false);
        }

        // gzclose() が残りを書き出すため、その結果も見る
        $closed = self::close($from, 'fclose', $to, 'gzclose');
        if (! $written || ! $closed) {
            throw new RuntimeException("{$target} を書けません(ディスクの空きを確かめてください)。");
        }
    }

    public static function decompress(string $source, string $target): void
    {
        $from = @gzopen($source, 'rb');
        $to = @fopen($target, 'wb');
        if ($from === false || $to === false) {
            self::close($from, 'gzclose', $to, 'fclose');
            throw new RuntimeException("{$source} を展開できません。");
        }

        $read = true;
        $written = true;
        while ($read && $written && ! gzeof($from)) {
            $chunk = gzread($from, self::CHUNK);
            $read = $chunk !== false;
            $written = ! $read || $chunk === '' || fwrite($to, $chunk) !== false;
        }

        $closed = self::close($from, 'gzclose', $to, 'fclose');
        if (! $read) {
            throw new RuntimeException("{$source} を展開できません(壊れているかもしれません)。");
        }
        if (! $written || ! $closed) {
            throw new RuntimeException("{$target} を書けません(ディスクの空きを確かめてください)。");
        }
    }

    /**
     * 開けたほうを閉じる。書き込み側を閉じられたかを返す。
     *
     * @param  resource|false  $from
     * @param  resource|false  $to
     */
    private static function close($from, callable $closeFrom, $to, callable $closeTo): bool
    {
        if ($from !== false) {
            $closeFrom($from);
        }

        return $to !== false && $closeTo($to);
    }
}
