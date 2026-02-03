<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            // Si quieres que siempre tenga un valor:
            $table->string('audience')->default('all_employees')->after('workday_url');

            // Si prefieres permitir null:
            // $table->string('audience')->nullable()->after('workday_url');
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
