<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VolunteerApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'application_type',
        'message',
        'status',
        'resume_path',
        'additional_info',
        'admin_notes',
    ];

    protected $casts = [
        'additional_info' => 'array',
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
