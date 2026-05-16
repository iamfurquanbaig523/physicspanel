<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ItemOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'seller_id',
        'buyer_id',
        'amount',
    ];

    // protected $appends = [
    //     'formatted_amount',
    //     'formatted_price',
    //     'currency_symbol',
    //     'currency_position',
    //     'formatted_min_salary',
    //     'formatted_max_salary',
    //     'formatted_salary_range',
    // ];

    public function item()
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function seller()
    {
        return $this->belongsTo(User::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class);
    }

    public function chat()
    {
        return $this->hasMany(Chat::class, 'item_offer_id');
    }

    // public function scopeOwner($query)
    // {
    //     return $query->where('seller_id', Auth::user()->id)->orWhere('buyer_id', Auth::user()->id);
    // }

    public function scopeOwner($query)
    {
        return $query->where(function ($q) {
            $q->where('seller_id', Auth::id())
                ->orWhere('buyer_id', Auth::id());
        });
    }

    // public function getFormattedAmountAttribute()
    // {
    //     if (! $this->amount) {
    //         return null;
    //     }

    //     $item = $this->relationLoaded('item')
    //         ? $this->item
    //         : $this->item()->with('countryRelation.currency')->first();

    //     $symbol = $item?->currency_symbol ?? '₹';
    //     $position = $item?->currency_position ?? 'left';

    //     $formatted = number_format($this->amount);

    //     return $position === 'right'
    //         ? "{$formatted} {$symbol}"
    //         : "{$symbol} {$formatted}";
    // }

    // public function getFormattedPriceAttribute()
    // {
    //     if (! $this->price) {
    //         return null;
    //     }

    //     $symbol = $this->currency_symbol ?? '₹';
    //     $position = $this->currency_position ?? 'left';

    //     $formatted = number_format($this->price);

    //     return $position === 'right'
    //         ? "{$formatted} {$symbol}"
    //         : "{$symbol} {$formatted}";
    // }

    // public function getCurrencySymbolAttribute()
    // {
    //     return $this->countryRelation?->currency?->symbol ?? '₹';
    //     dd($this->countryRelation);
    // }

    // public function getCurrencyPositionAttribute()
    // {
    //     return $this->countryRelation?->currency?->symbol_position ?? 'left';
    // }

    // public function getFormattedMinSalaryAttribute()
    // {
    //     if (! $this->min_salary) {
    //         return null;
    //     }

    //     $symbol = $this->currency_symbol;
    //     $position = $this->currency_position;
    //     $formatted = number_format($this->min_salary);

    //     return $position === 'right'
    //         ? "{$formatted} {$symbol}"
    //         : "{$symbol} {$formatted}";
    // }

    // public function getFormattedMaxSalaryAttribute()
    // {
    //     if (! $this->max_salary) {
    //         return null;
    //     }

    //     $symbol = $this->currency_symbol;
    //     $position = $this->currency_position;
    //     $formatted = number_format($this->max_salary);

    //     return $position === 'right'
    //         ? "{$formatted} {$symbol}"
    //         : "{$symbol} {$formatted}";
    // }

    // public function getFormattedSalaryRangeAttribute()
    // {
    //     if (! $this->min_salary && ! $this->max_salary) {
    //         return null;
    //     }

    //     if ($this->min_salary && ! $this->max_salary) {
    //         return "From {$this->formatted_min_salary}";
    //     }
    //     if (! $this->min_salary && $this->max_salary) {
    //         return "Upto {$this->formatted_max_salary}";
    //     }

    //     if ($this->min_salary && $this->max_salary) {
    //         return "{$this->formatted_min_salary} - {$this->formatted_max_salary}";
    //     }

    //     return $this->formatted_min_salary ?? $this->formatted_max_salary;
    // }
}
