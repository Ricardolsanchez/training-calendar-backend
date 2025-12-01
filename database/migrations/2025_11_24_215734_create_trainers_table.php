<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // Insertar trainers iniciales
        DB::table('trainers')->insert([
            ['id' => 1, 'name' => 'Sergio Osorio'],
            ['id' => 2, 'name' => 'Monica Mendoza'],
            ['id' => 3, 'name' => 'Kelvin Hodgson'],
            ['id' => 4, 'name' => 'Edma Murillo'],
            ['id' => 5, 'name' => 'Dora Ramirez'],
            ['id' => 6, 'name' => 'Ada Perez'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('trainers');
    }
};