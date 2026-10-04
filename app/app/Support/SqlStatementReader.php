<?php

namespace App\Support;

use Generator;
use RuntimeException;

/**
 * バックアップの database.sql(BackupService::dumpDatabase() が書いたもの)を、1 文ずつ取り出す。
 * ファイルを一度に読まず 1 行ずつ読み、引用符(' " `)の中の ; と改行では区切らない。
 * MySQL の文字列はバックスラッシュでエスケープし、SQLite は ' を 2 つ重ねる(どちらも PDO::quote() の形)。
 * 文の外の、行の先頭の -- はコメントとして読み飛ばす。
 */
class SqlStatementReader
{
    /**
     * @param  bool  $backslashEscapes  文字列の中のバックスラッシュをエスケープとして扱うか(MySQL は true、SQLite は false)
     * @return Generator<int, string>
     */
    public static function read(string $path, bool $backslashEscapes): Generator
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("{$path} を読めません。");
        }

        try {
            $statement = '';
            // いま中にいる引用符(外なら null)
            $quote = null;

            while (($line = fgets($handle)) !== false) {
                if ($quote === null && trim($statement) === '' && str_starts_with(ltrim($line), '--')) {
                    continue;
                }

                $length = strlen($line);
                $start = 0;
                $position = 0;

                while ($position < $length) {
                    if ($quote === null) {
                        $position += strcspn($line, ";'\"`", $position);
                        if ($position >= $length) {
                            break;
                        }

                        $char = $line[$position];
                        if ($char === ';') {
                            $statement .= substr($line, $start, $position - $start);
                            if (trim($statement) !== '') {
                                yield trim($statement);
                            }
                            $statement = '';
                            $start = $position + 1;
                        } else {
                            $quote = $char;
                        }
                        $position++;

                        continue;
                    }

                    $position += strcspn($line, $backslashEscapes && $quote === "'" ? "'\\" : $quote, $position);
                    if ($position >= $length) {
                        break;
                    }

                    if ($line[$position] === '\\') {
                        // バックスラッシュは次の 1 文字ごと飛ばす(\' で引用符を閉じない)
                        $position += 2;

                        continue;
                    }

                    // '' のように重ねた引用符は、閉じてすぐ開き直すものとして扱えばよい
                    $quote = null;
                    $position++;
                }

                $statement .= substr($line, $start);
            }

            if ($quote !== null) {
                throw new RuntimeException('SQL の引用符が閉じていません(ファイルが途中で切れています)。');
            }
            if (trim($statement) !== '') {
                yield trim($statement);
            }
        } finally {
            fclose($handle);
        }
    }
}
