<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $table = 'applications';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'application_type',
        'status',
        'resume_path',
        'admin_notes',
        // Personal Information
        'nationality',
        'date_of_birth',
        // Address Information
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'zip_code',
        'country',
        // Education & Languages
        'education_level',
        'program_major',
        'languages',
        // Availability
        'available_days',
        'available_times',
        'interests',
        // Skills & Motivation
        'skills_experience',
        'motivation',
        // Emergency Contact
        'emergency_name',
        'emergency_relationship',
        'emergency_phone',
        // Reference
        'reference_name',
        'reference_contact',
        // Health & Consent
        'has_medical_condition',
        'agrees_to_terms',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'available_days' => 'array',
        'available_times' => 'array',
        'interests' => 'array',
        'has_medical_condition' => 'boolean',
        'agrees_to_terms' => 'boolean',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeVolunteers($query)
    {
        return $query->where('application_type', 'volunteer');
    }

    public function scopeInterns($query)
    {
        return $query->where('application_type', 'intern');
    }

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
