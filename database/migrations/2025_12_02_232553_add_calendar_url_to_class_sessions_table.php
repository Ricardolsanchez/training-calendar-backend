<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            // 👇 Solo la crea si NO existe
            if (!Schema::hasColumn('class_sessions', 'calendar_url')) {
                $table->string('calendar_url', 2048)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('class_sessions', 'calendar_url')) {
                $table->dropColumn('calendar_url');
            }
        });
    }
};

