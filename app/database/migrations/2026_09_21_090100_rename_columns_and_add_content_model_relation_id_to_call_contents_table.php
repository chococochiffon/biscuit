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
        Schema::table('call_contents', function (Blueprint $table) {
            $table->renameColumn('content_type', 'call_type');
            $table->renameColumn('model_name', 'call_name');
        });

        Schema::table('call_contents', function (Blueprint $table) {
            $table->foreignId('content_model_relation_id')->after('call_name')->constrained();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_contents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_model_relation_id');
        });

        Schema::table('call_contents', function (Blueprint $table) {
            $table->renameColumn('call_type', 'content_type');
            $table->renameColumn('call_name', 'model_name');
        });
    }
};
