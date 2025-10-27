<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'status',
        'admin_reply',
        'replied_at',
        'metadata',
    ];

    protected $casts = [
        'replied_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeReplied($query)
    {
        return $query->where('status', 'replied');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
