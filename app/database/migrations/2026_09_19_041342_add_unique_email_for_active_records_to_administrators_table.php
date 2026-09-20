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
        Schema::table('administrators', function (Blueprint $table) {
            $table->dropUnique('administrators_email_unique');
            $table->string('unique_email')
                ->nullable()
                ->storedAs('CASE WHEN deleted_at IS NULL THEN email ELSE NULL END')
                ->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('administrators', function (Blueprint $table) {
            $table->dropColumn('unique_email');
            $table->unique('email');
        });
    }
};
