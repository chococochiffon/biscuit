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
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name',64);
            $table->string('title',128)->nullable();
            $table->string('x_id',128)->nullable();
            $table->text('detail')->nullable();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->text('address1')->nullable();
            $table->text('address2')->nullable();
            $table->text('address3')->nullable();
            $table->text('nearest')->nullable();
            $table->boolean('is_sunday_start')->default(true);
            $table->text('business_hour_text')->nullable();
            $table->text('business_day_text')->nullable();
            $table->text('last_order_text')->nullable();
            $table->json('business_hour')->nullable();
            $table->json('business_days')->nullable();
            $table->time('last_order')->nullable();
            $table->json('notch')->nullable();
            $table->timestamps();
            $table->softDeletes()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
