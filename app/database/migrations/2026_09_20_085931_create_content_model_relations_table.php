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
        Schema::create('content_model_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('content_type');
            $table->string('model_name');
            $table->string('table_name');
            $table->softDeletes();
            $table->timestamps();

            $table->string('unique_content_type_model_name')
                ->nullable()
                ->storedAs("CASE WHEN deleted_at IS NULL THEN CONCAT(content_type, '-', model_name) ELSE NULL END")
                ->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_model_relations');
    }
};
