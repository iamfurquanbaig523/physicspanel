<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogFaq extends Model
{
    use HasFactory;

    protected $fillable = [
        'blog_id',
        'question',
        'answer',
        'sort_order',
        'is_visible',
        'include_in_schema',
        'schema_question',
        'schema_answer',
        'options',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_visible' => 'boolean',
        'include_in_schema' => 'boolean',
        'options' => 'array',
    ];

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }
}
