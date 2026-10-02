<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ページビルダーの版の履歴のテーブルを作成する。ページ(page_builders)・グローバルコンポーネント(page_builder_components)を
     * 公開するたびに、公開した内容を 1 版として残す(エディタの「版の履歴」から下書きに読み込める)。
     * すでに公開中の内容は、最後に公開した日時の版として取り込む(公開した管理者は分からないため null)。
     */
    public function up(): void
    {
        Schema::create('page_builder_versions', function (Blueprint $table) {
            $table->id();
            $table->string('versionable_type', 50)->comment('対象の種類(page_builder・page_builder_component)');
            $table->unsignedBigInteger('versionable_id')->comment('対象の id');
            $table->foreignId('administrator_id')->nullable()->constrained('administrators')->comment('公開した管理者(分からなければ null)');
            $table->unsignedSmallInteger('schema_version')->default(1)->comment('ノードの木の JSON の構造の版');
            $table->json('content')->comment('公開した内容(ノードの木)');
            $table->unsignedInteger('node_count')->default(0)->comment('ブロックの数');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['versionable_type', 'versionable_id']);
        });

        foreach (['page_builders' => 'page_builder', 'page_builder_components' => 'page_builder_component'] as $source => $type) {
            DB::table($source)->whereNotNull('published_content')->orderBy('id')->each(function (object $row) use ($type) {
                DB::table('page_builder_versions')->insert([
                    'versionable_type' => $type,
                    'versionable_id' => $row->id,
                    'schema_version' => $row->schema_version,
                    'content' => $row->published_content,
                    'node_count' => $this->countNodes(json_decode($row->published_content, true)['children'] ?? []),
                    'deleted_at' => $row->deleted_at,
                    'created_at' => $row->published_at ?? $row->updated_at,
                    'updated_at' => $row->published_at ?? $row->updated_at,
                ]);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_builder_versions');
    }

    /**
     * ノードの数(子孫を含む)。
     *
     * @param  array<int, mixed>  $nodes
     */
    private function countNodes(array $nodes): int
    {
        return array_sum(array_map(fn (mixed $node) => 1 + $this->countNodes(is_array($node) ? $node['children'] ?? [] : []), $nodes));
    }
};
