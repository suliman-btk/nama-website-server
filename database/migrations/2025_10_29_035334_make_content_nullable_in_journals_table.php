<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('journals', function (Blueprint $table) {
            // Make content field nullable
            $table->text('content')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('journals', function (Blueprint $table) {
            // Revert content field to not nullable
            $table->text('content')->nullable(false)->change();
        });
    }
};
