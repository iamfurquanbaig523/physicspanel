<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Slider extends Model
{
    use HasFactory;

    protected $fillable = ['image', 'item_id', 'third_party_link', 'sequence', 'name', 'sold_out', 'country_id', 'state_id', 'city_id'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function getImageAttribute($image)
    {
        if (! empty($image)) {
            return url(Storage::url($image));
        }

        return $image;
    }

    public function scopeSearch($query, $search)
    {
        $search = '%'.$search.'%';
        $query = $query->where(function ($q) use ($search) {
            $q->orWhere('sequence', 'LIKE', $search)
                ->orWhere('model_type', 'LIKE', $search)
                ->orWhere('third_party_link', 'LIKE', $search)
                ->orWhere('model_id', 'LIKE', $search)
                ->orWhereHas('model', function ($q) use ($search) {
                    $q->where('name', 'LIKE', $search);
                });
        });

        return $query;
    }

    public function scopeSort($query, $column, $order)
    {
        switch ($column) {

            case 'model_name':
                $query->when(request('model_type') === 'App\\Models\\Item', function ($q) use ($order) {
                    $q->leftJoin('items', 'items.id', '=', 'sliders.model_id')
                        ->orderBy('items.name', $order);
                });

                $query->when(request('model_type') === 'App\\Models\\Category', function ($q) use ($order) {
                    $q->leftJoin('categories', 'categories.id', '=', 'sliders.model_id')
                        ->orderBy('categories.name', $order);
                });

                break;

            case 'item_name':
                $query->leftJoin('items', 'items.id', '=', 'sliders.item_id')
                    ->orderBy('items.name', $order);
                break;

            case 'country_name':
                $query->leftJoin('countries', 'countries.id', '=', 'sliders.country_id')
                    ->orderBy('countries.name', $order);
                break;

            case 'state_name':
                $query->leftJoin('states', 'states.id', '=', 'sliders.state_id')
                    ->orderBy('states.name', $order);
                break;

            case 'city_name':
                $query->leftJoin('cities', 'cities.id', '=', 'sliders.city_id')
                    ->orderBy('cities.name', $order);
                break;

            default:
                $query->orderBy($column, $order);
                break;
        }

        return $query->select('sliders.*');
    }

    public function categories()
    {
        return $this->hasOne(Category::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
