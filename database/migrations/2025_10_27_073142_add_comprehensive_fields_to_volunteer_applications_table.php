<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('volunteer_applications', function (Blueprint $table) {
            // Personal Information
            $table->string('nationality')->nullable();
            $table->date('date_of_birth')->nullable();

            // Address Information
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('country')->nullable();

            // Education & Languages
            $table->string('education_level')->nullable();
            $table->string('program_major')->nullable();
            $table->text('languages')->nullable(); // comma separated

            // Availability
            $table->json('available_days')->nullable(); // array of selected days
            $table->json('available_times')->nullable(); // array of selected times
            $table->json('interests')->nullable(); // array of selected interests

            // Skills & Motivation
            $table->text('skills_experience')->nullable();
            $table->text('motivation')->nullable();

            // Emergency Contact
            $table->string('emergency_name')->nullable();
            $table->string('emergency_relationship')->nullable();
            $table->string('emergency_phone')->nullable();

            // Reference
            $table->string('reference_name')->nullable();
            $table->string('reference_contact')->nullable();

            // Health & Consent
            $table->boolean('has_medical_condition')->default(false);
            $table->boolean('agrees_to_terms')->default(false);

            // Remove old fields that are now redundant
            $table->dropColumn(['message', 'additional_info']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('volunteer_applications', function (Blueprint $table) {
            // Drop all the new fields
            $table->dropColumn([
                'nationality',
                'date_of_birth',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'zip_code',
                'country',
                'education_level',
                'program_major',
                'languages',
                'available_days',
                'available_times',
                'interests',
                'skills_experience',
                'motivation',
                'emergency_name',
                'emergency_relationship',
                'emergency_phone',
                'reference_name',
                'reference_contact',
                'has_medical_condition',
                'agrees_to_terms'
            ]);

            // Restore old fields
            $table->text('message')->nullable();
            $table->json('additional_info')->nullable();
        });
    }
};
