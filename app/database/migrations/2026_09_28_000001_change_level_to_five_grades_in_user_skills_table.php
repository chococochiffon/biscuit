<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * スキルの習熟度(level)を 0〜100 から 1〜5 の 5 段階に変更する。
     * 登録済みの値は 20 刻みで換算する(0〜20 → 1、21〜40 → 2、…、81〜100 → 5)。
     */
    public function up(): void
    {
        DB::table('user_skills')->orderBy('id')->each(function (object $skill): void {
            DB::table('user_skills')->where('id', $skill->id)->update([
                'level' => max(1, min(5, (int) ceil($skill->level / 20))),
            ]);
        });

        Schema::table('user_skills', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('user_skills')->orderBy('id')->each(function (object $skill): void {
            DB::table('user_skills')->where('id', $skill->id)->update([
                'level' => $skill->level * 20,
            ]);
        });

        Schema::table('user_skills', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(0)->change();
        });
    }
};
