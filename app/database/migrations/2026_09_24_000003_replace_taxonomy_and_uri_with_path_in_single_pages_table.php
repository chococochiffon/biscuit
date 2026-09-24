<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * taxonomy・uri を親パス(parent_path)に統合し、スラッグ(slug)と公開側URLのパス(path)を追加する。
     * path は論理削除されていない行の間でのみ一意にする(unique_path)。
     */
    public function up(): void
    {
        Schema::table('single_pages', function (Blueprint $table) {
            $table->string('parent_path')->nullable()->after('short_sentences');
            $table->string('slug')->nullable()->after('parent_path');
            $table->string('path')->nullable()->after('slug');
        });

        // 既存データは taxonomy を親パス、uri をスラッグとして移行する(使えない文字はスラッグ化し、空なら page-{id})
        $usedPaths = [];

        foreach (DB::table('single_pages')->orderBy('id')->get() as $singlePage) {
            $parentPath = Str::slug((string) $singlePage->taxonomy) ?: null;
            $slug = Str::slug((string) $singlePage->uri) ?: 'page-'.$singlePage->id;
            $path = '/'.ltrim($parentPath.'/'.$slug, '/');

            if ($singlePage->deleted_at === null && in_array($path, $usedPaths, true)) {
                $slug .= '-'.$singlePage->id;
                $path .= '-'.$singlePage->id;
            }

            if ($singlePage->deleted_at === null) {
                $usedPaths[] = $path;
            }

            DB::table('single_pages')->where('id', $singlePage->id)->update([
                'parent_path' => $parentPath,
                'slug' => $slug,
                'path' => $path,
            ]);
        }

        Schema::table('single_pages', function (Blueprint $table) {
            $table->dropColumn(['taxonomy', 'uri']);
        });

        Schema::table('single_pages', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->string('path')->nullable(false)->change();
            $table->string('unique_path')
                ->nullable()
                ->storedAs('CASE WHEN deleted_at IS NULL THEN path ELSE NULL END')
                ->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('single_pages', function (Blueprint $table) {
            $table->string('taxonomy')->nullable()->after('short_sentences');
            $table->string('uri')->nullable()->after('taxonomy');
        });

        DB::table('single_pages')->update([
            'taxonomy' => DB::raw('parent_path'),
            'uri' => DB::raw('slug'),
        ]);

        Schema::table('single_pages', function (Blueprint $table) {
            $table->dropUnique(['unique_path']);
        });

        Schema::table('single_pages', function (Blueprint $table) {
            $table->dropColumn(['unique_path', 'parent_path', 'slug', 'path']);
        });
    }
};
