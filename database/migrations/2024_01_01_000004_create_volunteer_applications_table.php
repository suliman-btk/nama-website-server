<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('volunteer_applications', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('application_type'); // volunteer, intern
            $table->text('message');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->string('resume_path')->nullable();
            $table->json('additional_info')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'application_type']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('volunteer_applications');
    }
};
