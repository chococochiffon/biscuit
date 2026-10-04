<?php

namespace App\Support\Backup;

use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * データベースを SQL のファイルに書き出す・入れ直す(バックアップの database.sql)。本番の app イメージには mysqldump がないため PHP で書く。
 * 書き出しは、テーブルごとに DROP・CREATE・INSERT を並べる(生成カラムは入れ直すと失敗するため書かない。キャッシュ・ロック・セッションの表は
 * 行を書かない)。入れ直しは、いまのテーブルをすべて消してから SqlStatementReader で 1 文ずつ流す。MySQL(MariaDB)と SQLite(テスト)に対応する。
 */
class DatabaseDump
{
    /** 1 つの INSERT にまとめる行の数 */
    private const INSERT_ROWS = 100;

    private Connection $connection;

    public function __construct(?Connection $connection = null)
    {
        $this->connection = $connection ?? DB::connection();

        if (! in_array($this->connection->getDriverName(), ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new RuntimeException("データベース {$this->connection->getDriverName()} のバックアップには対応していません。");
        }
    }

    /**
     * すべてのテーブルを、作り直して入れ直す SQL に書き出す。
     *
     * @return array{driver: string, tables: int, rows: int}
     */
    public function export(string $path): array
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException("{$path} に書き込めません。");
        }

        $grammar = $this->connection->getQueryGrammar();
        $tables = $this->tables();
        $transient = $this->transientTables();
        $rows = 0;

        try {
            fwrite($handle, '-- Biscuit v'.config('biscuit.version').' のバックアップ('.CarbonImmutable::now()->toIso8601String().")\n");
            fwrite($handle, $this->isSqlite() ? "PRAGMA foreign_keys = OFF;\n" : $this->mysqlHeader());

            foreach ($tables as $table) {
                fwrite($handle, "\n-- {$table}\n");
                fwrite($handle, 'DROP TABLE IF EXISTS '.$grammar->wrapTable($table).";\n");
                foreach ($this->createStatements($table) as $statement) {
                    fwrite($handle, $statement.";\n");
                }
                if (! in_array($table, $transient, true)) {
                    $rows += $this->exportRows($table, $handle);
                }
            }

            fwrite($handle, $this->isSqlite() ? "\nPRAGMA foreign_keys = ON;\n" : "\nSET UNIQUE_CHECKS = 1;\nSET FOREIGN_KEY_CHECKS = 1;\n");
        } finally {
            fclose($handle);
        }

        return ['driver' => $this->connection->getDriverName(), 'tables' => count($tables), 'rows' => $rows];
    }

    /**
     * いまのテーブルをすべて消し(バックアップのあとで作ったテーブルも残さない)、SQL を流す。流した文の数を返す。
     */
    public function import(string $path): int
    {
        $schema = $this->schema();

        $schema->withoutForeignKeyConstraints(function () use ($schema) {
            foreach ($this->tables() as $table) {
                $schema->drop($table);
            }
        });

        $count = 0;
        foreach (SqlStatementReader::read($path, backslashEscapes: ! $this->isSqlite()) as $statement) {
            $this->connection->unprepared($statement);
            $count++;
        }

        return $count;
    }

    /**
     * いまのデータベースのテーブル(カスタムページの実行時に作ったテーブルも含む)。
     *
     * @return list<string>
     */
    private function tables(): array
    {
        $tables = $this->schema()->getTableListing($this->isSqlite() ? 'main' : $this->connection->getDatabaseName(), schemaQualified: false);
        sort($tables);

        return $tables;
    }

    /**
     * 構造だけを書き出し、行は書かないテーブル(キャッシュ・ロック・セッション)。戻すと、バックアップを作っていたときの
     * コマンドのロック(Isolatable)・古いキャッシュ・ログインが一緒に戻ってしまうため。
     *
     * @return list<string>
     */
    private function transientTables(): array
    {
        return array_values(array_filter([
            config('cache.stores.database.table'),
            config('cache.stores.database.lock_table') ?: config('cache.stores.database.table').'_locks',
            config('session.table'),
        ]));
    }

    /**
     * MySQL の SQL の先頭。timestamp 型は接続のタイムゾーンで読み書きされるため、読んだときと同じタイムゾーンで入れ直す。
     */
    private function mysqlHeader(): string
    {
        $timeZone = (string) $this->connection->selectOne('select @@session.time_zone as tz')->tz;

        return "SET NAMES utf8mb4;\nSET time_zone = ".$this->connection->getPdo()->quote($timeZone).";\nSET FOREIGN_KEY_CHECKS = 0;\nSET UNIQUE_CHECKS = 0;\n";
    }

    /**
     * テーブルを作る SQL(SQLite はテーブルのあとにインデックスも)。
     *
     * @return list<string>
     */
    private function createStatements(string $table): array
    {
        if ($this->isSqlite()) {
            return array_map(
                fn (object $row) => $row->sql,
                $this->connection->select("select sql from sqlite_master where tbl_name = ? and sql is not null order by type = 'index', name", [$table]),
            );
        }

        $row = (array) $this->connection->selectOne('SHOW CREATE TABLE '.$this->connection->getQueryGrammar()->wrapTable($table));

        return [(string) ($row['Create Table'] ?? array_values($row)[1])];
    }

    /**
     * テーブルの行を INSERT に書き出す(生成カラムは書かない)。書き出した行の数を返す。
     *
     * @param  resource  $handle
     */
    private function exportRows(string $table, $handle): int
    {
        $schema = $this->schema();
        $columns = array_values(array_map(
            fn (array $column) => $column['name'],
            array_filter($schema->getColumns($table), fn (array $column) => empty($column['generation'])),
        ));
        if ($columns === []) {
            return 0;
        }

        $grammar = $this->connection->getQueryGrammar();
        $prefix = 'INSERT INTO '.$grammar->wrapTable($table).' ('.implode(', ', array_map(fn ($column) => $grammar->wrap($column), $columns)).') VALUES ';

        // 主キーが 1 カラムなら、その順に少しずつ読む(大きいテーブルでもメモリに載せきらない)
        $primary = collect($schema->getIndexes($table))->first(fn (array $index) => $index['primary']);
        $query = $this->connection->table($table)->select($columns);
        $cursor = $primary !== null && count($primary['columns']) === 1 && in_array($primary['columns'][0], $columns, true)
            ? $query->lazyById(1000, $primary['columns'][0])
            : $query->cursor();

        $count = 0;
        $values = [];

        foreach ($cursor as $row) {
            $values[] = '('.implode(', ', array_map($this->literal(...), array_values((array) $row))).')';
            $count++;

            if (count($values) >= self::INSERT_ROWS) {
                fwrite($handle, $prefix.implode(",\n", $values).";\n");
                $values = [];
            }
        }

        if ($values !== []) {
            fwrite($handle, $prefix.implode(",\n", $values).";\n");
        }

        return $count;
    }

    private function literal(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            default => $this->connection->getPdo()->quote((string) $value),
        };
    }

    private function schema(): Builder
    {
        return $this->connection->getSchemaBuilder();
    }

    private function isSqlite(): bool
    {
        return $this->connection->getDriverName() === 'sqlite';
    }
}
