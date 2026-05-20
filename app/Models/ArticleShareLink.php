<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleShareLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'blog_id',
        'platform',
        'code',
        'target_url',
        'click_count',
        'is_active',
    ];

    protected $casts = [
        'click_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }
}
