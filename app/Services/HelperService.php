<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Category;
use App\Models\Setting;
use App\Services\CachingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use JsonException;

class HelperService
{
    public static function changeEnv($updateData = []): bool
    {
        return self::changeEnvFile(base_path() . '/.env', $updateData);
    }

    public static function changeEnvFile(string $path, array $updateData = []): bool
    {
        if (count($updateData) === 0 || ! is_file($path) || ! is_writable($path)) {
            return false;
        }

        $env = file_get_contents($path);
        $lines = preg_split('/\r\n|\r|\n/', $env === false ? '' : $env);
        $nextLines = [];
        $seen = [];

        foreach ($lines as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#') || ! str_contains($line, '=')) {
                $nextLines[] = $line;
                continue;
            }

            [$key] = explode('=', $line, 2);
            if (array_key_exists($key, $updateData)) {
                $nextLines[] = $key . '="' . str_replace('"', '', (string) $updateData[$key]) . '"';
                $seen[$key] = true;
                continue;
            }

            $nextLines[] = $line;
        }

        foreach ($updateData as $key => $value) {
            if (isset($seen[$key])) {
                continue;
            }

            $nextLines[] = $key . '="' . str_replace('"', '', (string) $value) . '"';
        }

        file_put_contents($path, implode("\n", $nextLines));

        return true;
    }

    /**
     * @description - This function will return the nested category Option tags using in memory optimization
     */
    public static function childCategoryRendering(&$categories, int $level = 0, ?string $parentCategoryID = ''): bool
    {
        // Foreach loop only on the parent category objects
        foreach (collect($categories)->where('parent_category_id', $parentCategoryID) as $key => $category) {
            echo "<option value='$category->id'>" . str_repeat('&nbsp;', $level * 4) . '|-- ' . $category->name . '</option>';
            // Once the parent category object is rendered we can remove the category from the main object so that redundant data can be removed
            $categories->forget($key);

            // Now fetch the subcategories of the main category
            $subcategories = $categories->where('parent_category_id', $category->id);
            if (! empty($subcategories)) {
                // Finally if subcategories are available then call the recursive function & see the magic
                self::childCategoryRendering($categories, $level + 1, $category->id);
            }
        }

        return false;
    }

    public static function buildNestedChildSubcategoryObject($categories)
    {
        // Used json_decode & encode simultaneously because i wanted to convert whole nested array into object
        try {
            return json_decode(json_encode(self::buildNestedChildSubcategoryArray($categories), JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return (object) [];
        }
    }

    private static function buildNestedChildSubcategoryArray($categories)
    {
        $children = [];
        // First Add Parent Categories to root level in an array
        foreach ($categories->toArray() as $value) {
            if ($value['parent_category_id'] == '') {
                $children[] = $value;
            }
        }

        // Then loop on the Parent Category to find the children categories
        foreach ($children as $key => $value) {
            $children[$key]['subcategories'] = self::findChildCategories($categories->toArray(), $value['id']);
        }

        return $children;
    }

    public static function findChildCategories($arr, $parent)
    {
        $children = [];
        foreach ($arr as $key => $value) {
            if ($value['parent_category_id'] == $parent) {
                $children[] = $value;
            }
        }
        foreach ($children as $key => $value) {
            $children[$key]['subcategories'] = self::findChildCategories($arr, $value['id']);
        }

        return $children;
    }

    /*
     * Sagar's Code :
     * in this i have approached the reverse object moving & removing.
     * which is not working as of now.
     * but will continue working on this in future as it seems bit optimized approach from the current one
    public static function buildNestedChildSubcategoryObject($categories, $finalCategories = []) {
        echo "<pre>";
        // Foreach loop only on the parent category objects
        if (!empty($finalCategories)) {
            $finalCategories = $categories->whereNull('parent_category_id');
        }
        foreach ($categories->whereNotNull('parent_category_id')->sortByDesc('parent_category_id') as $key => $category) {
            echo "----------------------------------------------------------------------<br>";
            $parentCategoryIndex = $categories->search(function ($data) use ($category) {
                return $data['id'] == $category->parent_category_id;
            });
            if (!$parentCategoryIndex) {
                continue;
            }
            // echo "*** This category will be moved to its parent category object ***<br>";
            // print_r($category->toArray());

            // Once the parent category object is rendered we can remove the category from the main object so that redundant data can be removed
            $categories[$parentCategoryIndex]->subcategories[] = $category->toArray();

            $categories->forget($key);
            echo "<br>*** After all the operation main categories object will look like this ***<br>";
            print_r($categories->toArray());

            if (!empty($categories)) {
                // Finally if subcategories are available then call the recursive function & see the magic
                return self::buildNestedChildSubcategoryObject($categories, $finalCategories);
            }
        }
        return $categories;
    } */

    public static function findParentCategory($category, $finalCategories = [])
    {
        $category = Category::find($category);

        if (! empty($category)) {
            $finalCategories[] = $category->id;

            if (! empty($category->parent_category_id)) {
                $finalCategories[] = self::findParentCategory($category->id, $finalCategories);
            }
        }

        return $finalCategories;
    }

    /**
     * Generate Slug for any model
     *
     * @param  $model  - Instance of Model
     */
    public static function generateUniqueSlug($model, string $slug, ?int $excludeID = null, int $count = 0): string
    {
        /* NOTE : This can be improved by directly calling in the UI on type of title via AJAX */
        $slug = Str::slug($slug);
        $newSlug = $count ? $slug . '-' . $count : $slug;

        $data = $model::where('slug', $newSlug);
        if ($excludeID !== null) {
            $data->where('id', '!=', $excludeID);
        }

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $data->withTrashed();
        }
        while ($data->exists()) {
            return self::generateUniqueSlug($model, $slug, $excludeID, $count + 1);
        }

        return $newSlug;
    }

    public static function findAllCategoryIds($model): array
    {
        $ids = [];

        foreach ($model as $item) {
            $ids[] = $item['id'];

            if (! empty($item['children'])) {
                $ids = array_merge($ids, self::findAllCategoryIds($item['children']));
            }
        }

        return $ids;
    }

    public static function generateRandomSlug($length = 10)
    {
        // Generate a random string of lowercase letters and numbers
        $characters = 'abcdefghijklmnopqrstuvwxyz-';
        $slug = '';

        for ($i = 0; $i < $length; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $slug .= $characters[$index];
        }

        return $slug;
    }

    /**
     * Apply location filters to Item query with fallback logic
     * Priority: area_id > city > state > country > latitude/longitude
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Http\Request $request
     * @param callable $applyAuthFilters Function to apply auth-specific filters
     * @return array ['query' => $query, 'message' => $locationMessage]
     */
    public static function applyLocationFilters($query, $request, $applyAuthFilters)
    {
        $isHomePage = $request->current_page === 'home';
        $locationMessage = null;
        $hasLocationFilter = $request->latitude !== null && $request->longitude !== null;
        $hasCityFilter = !empty($request->city);
        $hasStateFilter = !empty($request->state);
        $hasCountryFilter = !empty($request->country);
        $hasAreaFilter = !empty($request->area_id);
        $hasAreaLocationFilter = !empty($request->area_latitude) && !empty($request->area_longitude);

        $cityName = $request->city ?? null;
        $stateName = $request->state ?? null;
        $countryName = $request->country ?? null;
        $areaId = $request->area_id ?? null;

        $cityItemCount = 0;
        $stateItemCount = 0;
        $countryItemCount = 0;
        $areaItemCount = 0;
        $areaName = null;

        // Handle area location filter (find closest area by lat/long)
        if ($hasAreaLocationFilter && !$hasAreaFilter) {
            $areaLat = $request->area_latitude;
            $areaLng = $request->area_longitude;
            $haversine = "(6371 * acos(cos(radians($areaLat))
                    * cos(radians(latitude))
                    * cos(radians(longitude) - radians($areaLng))
                    + sin(radians($areaLat)) * sin(radians(latitude))))";

            $closestArea = Area::whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->selectRaw("areas.*, {$haversine} AS distance")
                // ->orderBy('distance', 'asc')

                ->orderByRaw(
                    '(6371 * acos(
        cos(radians(?)) *
        cos(radians(latitude)) *
        cos(radians(longitude) - radians(?)) +
        sin(radians(?)) * sin(radians(latitude))
    )) ASC',
                    [$areaLat, $areaLng, $areaLat]
                )

                ->first();

            if ($closestArea) {
                $hasAreaFilter = true;
                $areaId = $closestArea->id;
            }
        }

        // Get area name if area filter is set
        if ($hasAreaFilter) {
            $area = Area::find($areaId);
            $areaName = $area ? $area->name : __('the selected area');
        }

        // Save base query before location filters for fallback
        $baseQueryBeforeLocation = clone $query;

        // First, check for area filter (highest priority)
        if ($hasAreaFilter) {
            $areaQuery = clone $query;
            $areaQuery->where('area_id', $areaId);
            $areaQuery = $applyAuthFilters($areaQuery);
            $areaItemExists = $areaQuery->exists();

            if ($areaItemExists) {
                $query = $areaQuery;
                $areaItemCount = 1;
            } else {
                if ($isHomePage) {
                    $locationMessage = __('No Ads found in :area. Showing all available Ads.', ['area' => $areaName]);
                } else {
                    $query = $areaQuery;
                }
                $areaItemCount = 0;
            }
        }

        // Second, check for city filter
        if ($hasCityFilter && (!$hasAreaFilter || $areaItemCount == 0)) {
            $cityQuery = clone $query;
            $cityQuery->where('city', $cityName);
            $cityQuery = $applyAuthFilters($cityQuery);
            $cityItemExists = $cityQuery->exists();

            if ($cityItemExists) {
                $query = $cityQuery;
                $cityItemCount = 1;
                if ($hasAreaFilter && $areaItemCount == 0 && $isHomePage) {
                    $locationMessage = __('No Ads found in :city. Showing all available Ads.', ['city' => $cityName]);
                }
            } else {
                $cityItemCount = 0;
                if ($isHomePage) {
                    if (!$locationMessage) {
                        $locationMessage = __('No Ads found in :city. Showing all available Ads.', ['city' => $cityName]);
                    } else {
                        $locationMessage = __('No Ads found in :area or :city. Showing all available Ads.', ['area' => $areaName, 'city' => $cityName]);
                    }
                } else {
                    $query = $cityQuery;
                }
            }
        }

        // Third, check for state filter
        if ($hasStateFilter && (!$hasAreaFilter || $areaItemCount == 0) && (!$hasCityFilter || $cityItemCount == 0)) {
            $stateQuery = clone $query;
            $stateQuery->where('state', $stateName);
            $stateQuery = $applyAuthFilters($stateQuery);
            $stateItemExists = $stateQuery->exists();

            if ($stateItemExists) {
                $query = $stateQuery;
                $stateItemCount = 1;
                if (($hasAreaFilter && $areaItemCount == 0) || ($hasCityFilter && $cityItemCount == 0)) {
                    if ($isHomePage) {
                        $locationMessage = __('No Ads found in :state. Showing all available Ads.', ['state' => $stateName]);
                    }
                }
            } else {
                $stateItemCount = 0;
                if ($isHomePage) {
                    if (!$locationMessage) {
                        $locationMessage = __('No Ads found in :state. Showing all available Ads.', ['state' => $stateName]);
                    } else {
                        $parts = [];
                        if ($hasAreaFilter && $areaItemCount == 0) {
                            $parts[] = $areaName;
                        }
                        if ($hasCityFilter && $cityItemCount == 0) {
                            $parts[] = $cityName;
                        }
                        $parts[] = $stateName;
                        $locationMessage = __('No Ads found in :locations. Showing all available Ads.', ['locations' => implode(', ', $parts)]);
                    }
                } else {
                    $query = $stateQuery;
                }
            }
        }

        // Fourth, check for country filter
        if ($hasCountryFilter && (!$hasAreaFilter || $areaItemCount == 0) && (!$hasCityFilter || $cityItemCount == 0) && (!$hasStateFilter || $stateItemCount == 0)) {
            $countryQuery = clone $query;
            $countryQuery->where('country', $countryName);
            $countryQuery = $applyAuthFilters($countryQuery);
            $countryItemExists = $countryQuery->exists();

            if ($countryItemExists) {
                $query = $countryQuery;
                $countryItemCount = 1;
                if (($hasAreaFilter && $areaItemCount == 0) || ($hasCityFilter && $cityItemCount == 0) || ($hasStateFilter && $stateItemCount == 0)) {
                    if ($isHomePage) {
                        $locationMessage = __('No Ads found in :country. Showing all available Ads.', ['country' => $countryName]);
                    }
                }
            } else {
                $countryItemCount = 0;
                if ($isHomePage) {
                    if (!$locationMessage) {
                        $locationMessage = __('No Ads found in :country. Showing all available Ads.', ['country' => $countryName]);
                    } else {
                        $parts = [];
                        if ($hasAreaFilter && $areaItemCount == 0) {
                            $parts[] = $areaName;
                        }
                        if ($hasCityFilter && $cityItemCount == 0) {
                            $parts[] = $cityName;
                        }
                        if ($hasStateFilter && $stateItemCount == 0) {
                            $parts[] = $stateName;
                        }
                        $parts[] = $countryName;
                        $locationMessage = __('No Ads found in :locations. Showing all available Ads.', ['locations' => implode(', ', $parts)]);
                    }
                } else {
                    $query = $countryQuery;
                }
            }
        }

        // Fifth, handle latitude/longitude location-based search
        $hasHigherPriorityFilter = ($hasAreaFilter && $areaItemCount > 0) || ($hasCityFilter && $cityItemCount > 0) || ($hasStateFilter && $stateItemCount > 0) || ($hasCountryFilter && $countryItemCount > 0);
        if ($hasLocationFilter && ((!$hasAreaFilter && !$hasCityFilter && !$hasStateFilter && !$hasCountryFilter) || $hasHigherPriorityFilter)) {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $requestedRadius = (float)($request->radius ?? null);
            $exactLocationRadius = $request->radius;

            $haversine = '(6371 * acos(cos(radians(?))
                * cos(radians(latitude))
                * cos(radians(longitude) - radians(?))
                + sin(radians(?)) * sin(radians(latitude))))';

            $exactLocationQuery = clone $query;
            $exactLocationQuery
                ->select('items.*')
                ->selectRaw("$haversine AS distance", [$latitude, $longitude, $latitude])
                ->where('latitude', '!=', 0)
                ->where('longitude', '!=', 0)
                // CHANGE THIS: Use whereRaw instead of having to support pagination count
                // Use <= so radius=0.0 still returns items at the exact same coordinates (distance 0)
                ->whereRaw("$haversine <= ?", [$latitude, $longitude, $latitude, $exactLocationRadius])
                ->orderBy('distance', 'asc');

            if (Auth::check()) {
                $exactLocationQuery->with(['item_offers' => function ($q) {
                    $q->where('buyer_id', Auth::user()->id);
                }, 'user_reports' => function ($q) {
                    $q->where('user_id', Auth::user()->id);
                }]);

                $currentURI = explode('?', $request->getRequestUri(), 2);
                if ($currentURI[0] == '/api/my-items') {
                    $exactLocationQuery->where(['user_id' => Auth::user()->id])->withTrashed();
                } else {
                    $exactLocationQuery->where('status', 'approved')->has('user')->onlyNonBlockedUsers()->getNonExpiredItems();
                }
            } else {
                $exactLocationQuery->where('status', 'approved')->getNonExpiredItems();
            }

            $exactLocationExists = $exactLocationQuery->exists();

            if ($exactLocationExists) {
                $query = $exactLocationQuery;
            } else {
                $searchRadius = $requestedRadius !== null && $requestedRadius > 0 ? $requestedRadius : 50;

                $nearbyQuery = clone $query;
                $nearbyQuery
                    ->select('items.*')
                    ->selectRaw("$haversine AS distance", [$latitude, $longitude, $latitude])
                    ->where('latitude', '!=', 0)
                    ->where('longitude', '!=', 0)
                    // CHANGE THIS: Use whereRaw instead of having
                    ->whereRaw("$haversine < ?", [$latitude, $longitude, $latitude, $searchRadius])
                    ->orderBy('distance', 'asc');

                $nearbyQuery = $applyAuthFilters($nearbyQuery);
                $nearbyItemExists = $nearbyQuery->exists();

                if ($nearbyItemExists) {
                    $query = $nearbyQuery;
                    if (!$locationMessage) {
                        $locationMessage = __('No Ads found at your location. Showing nearby Ads.');
                    }
                } else {
                    if ($isHomePage) {
                        $query = clone $baseQueryBeforeLocation;
                        if (!$locationMessage) {
                            $locationMessage = __('No Ads found at your location. Showing all available Ads.');
                        }
                    } else {
                        $query = $nearbyQuery;
                    }
                }
            }
        }

        return ['query' => $query, 'message' => $locationMessage];
    }

    /**
     * Get watermark configuration status (enabled/disabled)
     *
     * @return bool
     */
    public static function getWatermarkConfigStatus(): bool
    {
        $enabled = CachingService::getSystemSettings('watermark_enabled');
        return !empty($enabled) && (int)$enabled === 1;
    }

    /**
     * Get watermark configuration as decoded array
     *
     * @return array
     */
    public static function getWatermarkConfigDecoded(): array
    {
        $settings = CachingService::getSystemSettings([
            'watermark_enabled',
            'watermark_image',
            'watermark_opacity',
            'watermark_size',
            'watermark_style',
            'watermark_position',
            'watermark_rotation',
        ]);

        return [
            'enabled' => (int)($settings['watermark_enabled'] ?? 0),
            'watermark_image' => $settings['watermark_image'] ?? null,
            'opacity' => (int)($settings['watermark_opacity'] ?? 25),
            'size' => (int)($settings['watermark_size'] ?? 10),
            'style' => $settings['watermark_style'] ?? 'tile',
            'position' => $settings['watermark_position'] ?? 'center',
            'rotation' => (int)($settings['watermark_rotation'] ?? -30),
        ];
    }

    /**
     * Get a specific setting value
     *
     * @param string $key
     * @return string|null
     */
    public static function getSettingData(string $key): ?string
    {
        return CachingService::getSystemSettings($key);
    }

    /**
     * Calculate item expiry date based on package listing duration
     * Priority: listing_duration > package expiry > default
     * 
     * @param \App\Models\Package|null $package
     * @param \App\Models\UserPurchasedPackage|null $userPackage
     * @return \Carbon\Carbon|null
     */
    public static function calculateItemExpiryDate($package = null, $userPackage = null)
    {

        if (!$package) {
            $freeAdUnlimited = Setting::where('name', 'free_ad_unlimited')->value('value') ?? 0;
            $freeAdDays = Setting::where('name', 'free_ad_duration_days')->value('value') ?? 0;
            // Unlimited free ads
            if ((int) $freeAdUnlimited === 1) {
                return null;
            }

            // Limited free ads
            if (!empty($freeAdDays) && (int) $freeAdDays > 0) {
                return Carbon::now()->addDays((int) $freeAdDays);
            }

            // Safety fallback (no expiry)
            return null;
        }

        // ---------------------------------------
        // PACKAGE LOGIC (existing, unchanged)
        // ---------------------------------------

        $listingDurationType = $package->listing_duration_type ?? null;
        $listingDurationDays = $package->listing_duration_days ?? null;

        // If listing_duration_type is null → use package expiry
        if ($listingDurationType === null) {
            if ($userPackage && $userPackage->end_date) {
                return Carbon::parse($userPackage->end_date);
            }

            if ($package->duration === 'unlimited') {
                return null;
            }

            return Carbon::now()->addDays((int) $package->duration);
        }

        if ($listingDurationType === 'package') {
            if ($package->duration === 'unlimited') {
                return null;
            }

            return Carbon::now()->addDays((int) $package->duration);
        }

        if ($listingDurationType === 'custom') {
            if (!empty($listingDurationDays) && (int) $listingDurationDays > 0) {
                return Carbon::now()->addDays((int) $listingDurationDays);
            }

            return Carbon::now()->addDays(30);
        }

        // Standard fallback
        if (!empty($listingDurationDays) && (int) $listingDurationDays > 0) {
            return Carbon::now()->addDays((int) $listingDurationDays);
        }

        return Carbon::now()->addDays(30);
    }
}
