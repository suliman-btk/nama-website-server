<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->text('excerpt')->nullable();
            $table->string('author');
            $table->string('featured_image')->nullable();
            $table->string('status')->default('draft'); // draft, published
            $table->datetime('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('published_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('journals');
    }
};
