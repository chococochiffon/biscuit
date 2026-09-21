<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('single_pages', function (Blueprint $table) {
            $table->dropColumn('content');
        });

        Schema::table('single_pages', function (Blueprint $table) {
            $table->renameColumn('url', 'uri');
        });

        Schema::table('single_pages', function (Blueprint $table) {
            $table->string('short_sentences', 255)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('single_pages', function (Blueprint $table) {
            $table->text('short_sentences')->nullable()->change();
        });

        Schema::table('single_pages', function (Blueprint $table) {
            $table->renameColumn('uri', 'url');
        });

        Schema::table('single_pages', function (Blueprint $table) {
            $table->text('content')->nullable()->after('title');
        });
    }
};
