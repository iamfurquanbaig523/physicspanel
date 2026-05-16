<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchQuery extends Model
{
    use HasFactory;

    protected $fillable = [
        'query',
        'page',
        'source',
        'results_count',
        'ip_address',
        'user_agent',
    ];

    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('query', 'like', "%{$search}%")
                ->orWhere('page', 'like', "%{$search}%")
                ->orWhere('source', 'like', "%{$search}%");
        });
    }
}
