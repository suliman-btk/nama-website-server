<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'excerpt',
        'author',
        'featured_image',
        'status',
        'published_at',
        'metadata',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('published_at', 'desc');
    }
}
