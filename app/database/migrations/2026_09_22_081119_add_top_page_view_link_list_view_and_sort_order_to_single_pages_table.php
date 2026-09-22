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
            $table->boolean('top_page_view')->default(false)->after('uri');
            $table->boolean('link_list_view')->default(false)->after('top_page_view');
            $table->integer('sort_order')->default(0)->after('link_list_view');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('single_pages', function (Blueprint $table) {
            $table->dropColumn(['top_page_view', 'link_list_view', 'sort_order']);
        });
    }
};
