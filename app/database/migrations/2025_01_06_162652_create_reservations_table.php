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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->onDelete('cascade');
            $table->boolean('is_reservation')->default(true);
            $table->string('nickname')->nullable();
            $table->string('email')->nullable();
            $table->string('first_name')->nullable();
            $table->string('family_name')->nullable();
            $table->string('x_id')->nullable();
            $table->integer('visit')->default(0);
            $table->time('visiting_time')->default('21:00:00');
            $table->string('staying_time')->default('00:30');
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
            $table->boolean('is_option_item')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
