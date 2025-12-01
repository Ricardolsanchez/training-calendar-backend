<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->text('notes')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('trainer_name')->nullable();
            $table->date('original_start_date')->nullable();
            $table->date('original_end_date')->nullable();
            $table->integer('original_training_days')->nullable();
            $table->integer('new_training_days')->nullable();
              $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
