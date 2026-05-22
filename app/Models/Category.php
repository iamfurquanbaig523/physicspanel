<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class Category extends Model
{
    use HasFactory, HasRecursiveRelationships;

    protected $fillable = [
        'name',
        'series_title',
        'parent_category_id',
        'image',
        'icon',
        'accent_color',
        'slug',
        'status',
        'is_coming_soon',
        'description',
        'series_description',
        'series_content',
        'show_in_header_nav',
        'header_nav_order',
        'show_in_mobile_nav',
        'mobile_nav_order',
        'meta_title',
        'meta_description',
        'is_job_category',
        'price_optional',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_coming_soon' => 'boolean',
        'show_in_header_nav' => 'boolean',
        'show_in_mobile_nav' => 'boolean',
        'header_nav_order' => 'integer',
        'mobile_nav_order' => 'integer',
        'sequence' => 'integer',
    ];

    public function getParentKeyName()
    {
        return 'parent_category_id';
    }

    protected $appends = ['translated_name', 'translated_description'];

    protected $with = ['translations'];

    public function subcategories()
    {
        return $this->hasMany(self::class, 'parent_category_id');
    }

    public function custom_fields()
    {
        return $this->hasMany(CustomFieldCategory::class);
    }

    public function getImageAttribute($image)
    {
        if (! empty($image)) {
            $storageUrl = Storage::url($image);

            if (! app()->runningInConsole() && request()?->getHost() && in_array(request()->getHost(), ['localhost', '127.0.0.1'], true)) {
                $localPath = parse_url($storageUrl, PHP_URL_PATH) ?: $storageUrl;
                $basePath = rtrim(request()->getBaseUrl(), '/');

                if ($basePath !== '' && str_starts_with($localPath, $basePath.'/')) {
                    return request()->getSchemeAndHttpHost().$localPath;
                }

                return rtrim(request()->getSchemeAndHttpHost().$basePath, '/').'/'.ltrim($localPath, '/');
            }

            return url($storageUrl);
        }

        return $image;
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function blogs()
    {
        return $this->hasMany(Blog::class);
    }

    public function approved_items()
    {
        return $this->hasMany(Item::class)->where('status', 'approved');
    }

    public function getAllItemsCountAttribute()
    {
        // Count items in this category
        $totalItems = $this->items()->where('status', 'approved')->getNonExpiredItems()->count();

        // Count items from ALL descendants (not just loaded ones) using recursive query
        $descendantIds = $this->descendants()
            ->where('status', 1)
            ->pluck('id')
            ->toArray();

        if (! empty($descendantIds)) {
            $descendantItemsCount = Item::without('translations')
                ->whereIn('category_id', $descendantIds)
                ->where('status', 'approved')
                ->getNonExpiredItems()
                ->count();

            $totalItems += $descendantItemsCount;
        }

        return $totalItems;
    }

    public function scopeSearch($query, $search)
    {
        $search = '%'.$search.'%';

        return $query->where(function ($q) use ($search) {
            $q->orWhere('name', 'LIKE', $search)
                ->orWhere('description', 'LIKE', $search)
                ->orWhereHas('translations', function ($q) use ($search) {
                    $q->where('description', 'LIKE', $search);
                });
        });
    }

    public function slider(): MorphOne
    {
        return $this->morphOne(Slider::class, 'model');
    }

    public function translations()
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function getTranslatedNameAttribute()
    {
        $languageCode = request()->header('Content-Language') ?? app()->getLocale();
        if (! empty($languageCode)) {
            // NOTE : This code can be done in Cache
            $language = Language::select(['id', 'code'])->where('code', $languageCode)->first();

            if (empty($language)) {
                return $this->name;
            }

            $languageId = $language->id;
            $translation = $this->translations->first(static function ($data) use ($languageId) {
                return $data->language_id == $languageId;
            });

            return ! empty($translation?->name) ? $translation->name : $this->name;
        }

        return $this->name;
    }

    public function getTranslatedDescriptionAttribute()
    {
        $languageCode = request()->header('Content-Language') ?? app()->getLocale();
        if (! empty($languageCode)) {
            // NOTE : This code can be done in Cache
            $language = Language::select(['id', 'code'])->where('code', $languageCode)->first();

            if (empty($language)) {
                return $this->description;
            }

            $languageId = $language->id;
            $translation = $this->translations->first(static function ($data) use ($languageId) {
                return $data->language_id == $languageId;
            });

            return ! empty($translation?->description) ? $translation->description : $this->description;
        }

        return $this->description;
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_category_id');
    }

    public function getFullPathAttribute()
    {
        $names = [];
        $current = $this;
        $visited = [];

        while ($current) {
            if (in_array($current->id, $visited, true)) {
                break; // prevent loop
            }
            $visited[] = $current->id;

            $names[] = $current->name;
            $current = $current->parent;
        }

        return implode(' > ', array_reverse($names));
    }

    public function getItemsGroupedByStatusAttribute()
    {
        $counts = [];

        // Count items in this category
        $items = $this->items()->get();
        foreach ($items as $item) {
            $counts[$item->status] = ($counts[$item->status] ?? 0) + 1;
        }

        // Include subcategories recursively
        foreach ($this->subcategories as $subcategory) {
            $subCounts = $subcategory->items_grouped_by_status;
            foreach ($subCounts as $status => $count) {
                $counts[$status] = ($counts[$status] ?? 0) + $count;
            }
        }

        return $counts;
    }

    public function getOtherItemsCountAttribute()
    {
        $totalItems = $this->items()->where('status', '!=', 'approved')->count();
        foreach ($this->subcategories as $subcategory) {
            $totalItems += $subcategory->other_items_count;
        }

        return $totalItems;
    }
}
