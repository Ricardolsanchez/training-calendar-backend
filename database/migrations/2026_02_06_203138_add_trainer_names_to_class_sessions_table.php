<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->json('trainer_names')->nullable();
        });
    }

    public function down()
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn('trainer_names');
        });
    }
};
