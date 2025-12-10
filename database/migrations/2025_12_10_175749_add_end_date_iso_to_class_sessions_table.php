<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->date('end_date_iso')->nullable()->after('date_iso');
        });

        // Copiamos la fecha actual como fin por defecto
        DB::statement('UPDATE class_sessions SET end_date_iso = date_iso WHERE end_date_iso IS NULL');
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn('end_date_iso');
        });
    }
};