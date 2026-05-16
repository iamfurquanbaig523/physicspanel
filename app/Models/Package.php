<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Package extends Model {
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'discount_in_percentage',
        'final_price',
        'duration',
        'item_limit',
        'type',
        'icon',
        'description',
        'status',
        'ios_product_id',
        'is_global',
        'key_points',
        'listing_duration_type',
        'listing_duration_days'
    ];
    protected $appends = ['translated_name', 'translated_description','translated_key_points'];
    
    /**
     * Get listing duration type, fallback to package duration if null
     * 
     * @return string|null
     */
    public function getListingDurationTypeAttribute($value)
    {
        // If listing_duration_type is null, return 'package' to indicate it uses package duration
        if ($value === null) {
            return 'package';
        }
        return $value;
    }
    
    /**
     * Get listing duration days, fallback to package duration if null and type is package
     * 
     * @return int|string|null
     */
    public function getListingDurationDaysAttribute($value)
    {
        // If listing_duration_days is null and listing_duration_type is null or 'package', use package duration
        if ($value === null || $value === '') {
            $listingDurationType = $this->attributes['listing_duration_type'] ?? null;
            if ($listingDurationType === null || $listingDurationType === '' || $listingDurationType === 'package') {
                // Use raw duration attribute to avoid infinite loop
                return $this->attributes['duration'] ?? null;
            }
        }
        return $value;
    }

    public function user_purchased_packages() {
        return $this->hasMany(UserPurchasedPackage::class);
    }
    
    public function translations()
    {
        return $this->hasMany(PackageTranslation::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'package_categories', 'package_id', 'category_id');
    }

    public function package_categories()
    {
        return $this->hasMany(PackageCategory::class);
    }

    public function getIconAttribute($icon) {
        if (!empty($icon)) {
            return url(Storage::url($icon));
        }
        return $icon;
    }

    public function scopeSearch($query, $search) {
        $search = "%" . $search . "%";
        $query = $query->where(function ($q) use ($search) {
            $q->orWhere('name', 'LIKE', $search)
                ->orWhere('price', 'LIKE', $search)
                ->orWhere('discount_in_percentage', 'LIKE', $search)
                ->orWhere('final_price', 'LIKE', $search)
                ->orWhere('duration', 'LIKE', $search)
                ->orWhere('item_limit', 'LIKE', $search)
                ->orWhere('type', 'LIKE', $search)
                ->orWhere('description', 'LIKE', $search)
                ->orWhere('status', 'LIKE', $search)
                ->orWhere('created_at', 'LIKE', $search)
                ->orWhere('updated_at', 'LIKE', $search);
        });
        return $query;
    }
    public function getTranslatedNameAttribute() {
        $languageCode = request()->header('Content-Language') ?? app()->getLocale();
        if (!empty($languageCode) && $this->relationLoaded('translations')) {
            // NOTE : This code can be done in Cache
            $language = Language::select(['id', 'code'])->where('code', $languageCode)->first();

            $translation = $this->translations->first(static function ($data) use ($language) {
                return $data->language_id == $language->id;
            });

           return !empty($translation?->name) ? $translation->name : $this->name;
        }

        return $this->name;
    }
        public function getTranslatedDescriptionAttribute() {
        $languageCode = request()->header('Content-Language') ?? app()->getLocale();

        if (!empty($languageCode) && $this->relationLoaded('translations')) {
            $language = Language::select(['id', 'code'])->where('code', $languageCode)->first();

            $translation = $this->translations->first(static function ($data) use ($language) {
                return $data->language_id == $language->id;
            });

            return !empty($translation?->description) ? $translation->description : $this->description;
        }

        return $this->description;
    }

    public function scopeFilter($query, $filterObject) {
        if (!empty($filterObject)) {
            foreach ($filterObject as $column => $value) {
                if ($column == "type") {
                    $query->where('type', $value);
                } else {
                    $query->where((string)$column, (string)$value);
                }
            }
        }
        return $query;

    }

    public function getTranslatedKeyPointsAttribute()
    {
        $languageCode = request()->header('Content-Language') ?? app()->getLocale();

        // ---------- Default / fallback ----------
        if (!empty($this->key_points)) {
            $defaultKeyPoints = is_array($this->key_points)
                ? $this->key_points
                : (json_decode($this->key_points, true) ?? []);
        } else {
            $defaultKeyPoints = [];
        }

        // ---------- Translation ----------
        if (!empty($languageCode) && $this->relationLoaded('translations')) {
            $language = Language::select(['id', 'code'])
                ->where('code', $languageCode)
                ->first();

            if ($language) {
                $translation = $this->translations->first(static function ($data) use ($language) {
                    return $data->language_id == $language->id;
                });

                if (!empty($translation?->key_points)) {
                    $translatedKeyPoints = is_array($translation->key_points)
                        ? $translation->key_points
                        : json_decode($translation->key_points, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($translatedKeyPoints)) {
                        return $translatedKeyPoints;
                    }
                }
            }
        }

        return is_array($defaultKeyPoints) ? $defaultKeyPoints : [];
    }


}
