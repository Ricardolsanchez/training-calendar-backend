<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Migración obsoleta: ya no usamos tabla "classes".
        // Intencionalmente vacío.
    }

    public function down(): void
    {
        // Nada que revertir.
    }
};