<?php

namespace App\Support\CustomPages;

use App\Enums\ArticleApprovalStatus;
use App\Enums\CustomPageBaseType;
use App\Models\CustomPageType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * カスタムページの種類ごとのテーブルを作る。
 * テーブルの作成(DDL)は MySQL ではトランザクションで取り消せないため、途中で失敗したら作ったテーブルを削除して元に戻す。
 * 種類を論理削除してもテーブルは削除しない(物理削除しない方針のため)。
 */
class CustomPageSchema
{
    /**
     * 種類ごとのテーブル(本体・詳細(固定ページ型のみ)・カスタムフォームの項目定義・入力値)を作る。
     */
    public function create(CustomPageType $type): void
    {
        $created = [];

        try {
            Schema::create($type->tableName(), fn (Blueprint $table) => $this->defineEntryTable($table, $type));
            $created[] = $type->tableName();

            if ($type->hasDetails()) {
                Schema::create($type->detailsTableName(), fn (Blueprint $table) => $this->defineDetailsTable($table, $type));
                $created[] = $type->detailsTableName();
            }

            Schema::create($type->formsTableName(), fn (Blueprint $table) => $this->defineFormsTable($table));
            $created[] = $type->formsTableName();

            Schema::create($type->formValuesTableName(), fn (Blueprint $table) => $this->defineFormValuesTable($table, $type));
            $created[] = $type->formValuesTableName();
        } catch (Throwable $exception) {
            $this->dropTables($created);

            throw $exception;
        }
    }

    /**
     * 種類ごとのテーブルを削除する(種類の登録に失敗したときの後始末と、custom_page_types のマイグレーションの取り消し用。
     * 種類の削除(論理削除)では使わない)。
     */
    public function drop(CustomPageType $type): void
    {
        $this->dropTables($type->tableNames());
    }

    /**
     * 外部キーで参照している側(後に作ったもの)から順にテーブルを削除する。
     *
     * @param  list<string>  $tableNames
     */
    private function dropTables(array $tableNames): void
    {
        foreach (array_reverse($tableNames) as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }

    /**
     * 本体のテーブル。記事型は記事、固定ページ型は固定ページの主な項目を持つ。
     * 公開側の URL は「/カスタム名の複数形/スラッグ」(スラッグ未入力の記事型は id)で、スラッグは種類の中で一意にする(フォームリクエストで検証)。
     */
    private function defineEntryTable(Blueprint $table, CustomPageType $type): void
    {
        $table->id();
        $table->string('title');
        $table->string('slug')->nullable();

        if ($type->base_type === CustomPageBaseType::Article) {
            $table->longText('content')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('approval', 16)->default(ArticleApprovalStatus::Draft->value);
        } else {
            $table->string('short_sentences');
            $table->string('header_image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
        }

        $table->dateTime('publication_start_datetime')->useCurrent();
        $table->dateTime('publication_end_datetime')->nullable();
        $table->softDeletes();
        $table->timestamps();
    }

    /**
     * 詳細のテーブル(固定ページ型のみ。固定ページの詳細と同じく小見出し・本文・並び順を持つ)。
     */
    private function defineDetailsTable(Blueprint $table, CustomPageType $type): void
    {
        $table->id();
        $this->foreignIdTo($table, $type->entryForeignKey(), $type->tableName());
        $table->string('sub_title');
        $table->longText('contents')->nullable();
        $table->unsignedInteger('sort_order')->default(0);
        $table->softDeletes();
        $table->timestamps();
    }

    /**
     * カスタムフォームの項目定義のテーブル(種類ごとに、登録・編集画面へ足す入力項目を並び順で持つ)。
     * customs_form_options はプルダウン・ラジオ・チェックボックスの選択肢(JSON の配列)。
     */
    private function defineFormsTable(Blueprint $table): void
    {
        $table->id();
        $table->string('parts_name');
        $table->unsignedTinyInteger('customs_form_type')->default(0);
        $table->text('customs_form_options')->nullable();
        $table->unsignedInteger('sort_order')->default(0);
        $table->softDeletes();
        $table->timestamps();
    }

    /**
     * カスタムフォームの入力値のテーブル(ページ 1 件ごとに、項目ごとの入力値を JSON で持つ)。
     * 固定ページ型は詳細の行ごとの入力にも使えるよう、詳細を指す列(紐づけがない場合は null)も持つ。
     */
    private function defineFormValuesTable(Blueprint $table, CustomPageType $type): void
    {
        $table->id();
        $this->foreignIdTo($table, $type->formForeignKey(), $type->formsTableName());
        $this->foreignIdTo($table, $type->entryForeignKey(), $type->tableName());

        if ($type->hasDetails()) {
            $this->foreignIdTo($table, $type->detailForeignKey(), $type->detailsTableName(), nullable: true);
        }

        $table->text('value')->nullable();
        $table->softDeletes();
        $table->timestamps();
    }

    /**
     * 外部キーの列を足す。制約名は MySQL の識別子の長さ(64 文字)に収まるよう、テーブル名と列名から短い名前を作る。
     */
    private function foreignIdTo(Blueprint $table, string $column, string $referencedTable, bool $nullable = false): void
    {
        $definition = $table->foreignId($column);

        if ($nullable) {
            $definition->nullable();
        }

        $definition->constrained($referencedTable, 'id', 'fk_'.substr(md5($table->getTable().'.'.$column), 0, 16));
    }
}
