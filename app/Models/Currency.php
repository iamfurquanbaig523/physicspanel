<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'iso_code',
        'name',
        'symbol',
        'symbol_position',
        'decimal_places',
        'thousand_separator',
        'decimal_separator',
        'country_id',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function scopeSort($query, $sortBy, $order)
    {
        if ($sortBy === 'country_name' || $sortBy === 'country.name') {
            return $query
                ->leftJoin('countries', 'currencies.country_id', '=', 'countries.id')
                ->orderBy('countries.name', $order)
                ->select('currencies.*');
        }

        return $query->orderBy($sortBy, $order);
    }




    // protected $appends = ['translated_name'];

    // public function translations()
    // {
    //     return $this->hasMany(CurrencyTranslation::class);
    // }

    public function scopeSearch($query, $search)
    {
        $search = '%' . $search . '%';

        return $query->where(function ($q) use ($search) {
            $q->where('currencies.id', 'LIKE', $search)
                ->orWhere('currencies.iso_code', 'LIKE', $search)
                ->orWhere('currencies.name', 'LIKE', $search)
                ->orWhere('currencies.symbol', 'LIKE', $search)
                ->orWhereHas('country', function ($q) use ($search) {
                    $q->where('name', 'LIKE', $search);
                });
        });
    }


    // public function getTranslatedNameAttribute()
    // {
    //     $languageCode = request()->header('Content-Language') ?? app()->getLocale();

    //     if ($languageCode) {
    //         $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();
    //         $translation = $translations->firstWhere('language_id', $languageCode);

    //         return $translation?->name ?? $this->name;
    //     }

    //     return $this->name;
    // }
}
