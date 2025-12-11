<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La columna "description" YA existe en class_sessions (Supabase),
        // así que no hacemos nada para evitar error de columna duplicada.
        // Si quisieras ser extra segura:
        // Schema::table('class_sessions', function (Blueprint $table) {
        //     if (!Schema::hasColumn('class_sessions', 'description')) {
        //         $table->text('description')->nullable();
        //     }
        // });
    }

    public function down(): void
    {
        // No tiramos la columna, porque ya está creada en producción.
    }
};
