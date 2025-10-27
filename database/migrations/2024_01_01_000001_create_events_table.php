<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->text('short_description')->nullable();
            $table->datetime('start_date');
            $table->datetime('end_date')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('draft'); // draft, published, cancelled
            $table->string('featured_image')->nullable();
            $table->json('metadata')->nullable(); // for additional fields
            $table->timestamps();

            $table->index(['status', 'start_date']);
            $table->index('start_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('events');
    }
};
