<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('class_sessions', 'calendar_url')) {
                $table->string('calendar_url', 2048)->nullable();
            }

            if (!Schema::hasColumn('class_sessions', 'calendar_event_id')) {
                $table->string('calendar_event_id', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('class_sessions', 'calendar_event_id')) {
                $table->dropColumn('calendar_event_id');
            }
            // NO borres calendar_url si ya existía antes (para no tumbar prod)
        });
    }
};
