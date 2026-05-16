<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Package;
use App\Models\PackageCategory;
use App\Models\PackageTranslation;
use App\Models\PaymentTransaction;
use App\Models\UserFcmToken;
use App\Models\UserPurchasedPackage;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\NotificationService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PackageController extends Controller {

    private string $uploadFolder;

    public function __construct() {
        $this->uploadFolder = 'packages';
    }

    public function index() {
        ResponseService::noAnyPermissionThenRedirect(['advertisement-listing-package-list', 'advertisement-listing-package-create', 'advertisement-listing-package-update', 'advertisement-listing-package-delete']);
        $categories = Category::without('translations')
            ->where('status', 1)
            ->get()
            ->each->setAppends([]);
        $categories = HelperService::buildNestedChildSubcategoryObject($categories);
        $languages = CachingService::getLanguages()->values();
        $currency_symbol = CachingService::getSystemSettings('currency_symbol');
        return view('packages.index', compact('categories', 'currency_symbol', 'languages'));
    }

    public function create(Request $request) {
        ResponseService::noPermissionThenRedirect('advertisement-listing-package-create');
        $categories = Category::without('translations')
            ->where('status', 1)
            ->get()
            ->each->setAppends([]);
        $categories = HelperService::buildNestedChildSubcategoryObject($categories);
        $languages = CachingService::getLanguages()->values();
        $currency_symbol = CachingService::getSystemSettings('currency_symbol');
        $selected_categories = [];
        $selected_all_categories = [];
        return view('packages.create', compact('categories', 'currency_symbol', 'languages', 'selected_categories', 'selected_all_categories'));
    }
    public function store(Request $request) {
        ResponseService::noPermissionThenSendJson('advertisement-listing-package-create');
        
        $languages = CachingService::getLanguages();
        $defaultLangId = 1;
        $otherLanguages = $languages->where('id', '!=', $defaultLangId);

        // Support both new UI (`type`) and legacy UI (`package_types[]`)
        $resolvedPackageType = $request->input('type');
        if (empty($resolvedPackageType)) {
            $resolvedPackageType = $request->input('package_types.0', 'item_listing');
        }

        $rules = [
            "name.$defaultLangId"     => 'required|string',
            'price'                  => 'required|numeric',
            'discount_in_percentage' => 'required|numeric',
            'final_price'            => 'required|numeric',
            'package_duration_type'  => 'required|in:limited,unlimited',
            'duration'               => 'nullable|required_if:package_duration_type,limited|min:1',
            'type'                   => 'required|in:item_listing,advertisement',
            'icon'                   => 'required|mimes:jpeg,jpg,png|max:7168',
            'is_global'              => 'nullable|in:0,1',
            'selected_categories'     => 'required_unless:is_global,1|array|min:1',
            'ads_item_limit_type'    => 'required_if:type,item_listing|in:limited,unlimited',
            'ads_item_limit'         => 'required_if:ads_item_limit_type,limited',
            'ads_listing_duration_type' => 'required_if:type,item_listing|in:standard,package,custom',
            'ads_listing_duration_days' => 'nullable|required_if:ads_listing_duration_type,custom|integer|min:1',
            'featured_item_limit_type' => 'required_if:type,advertisement|in:limited,unlimited',
            'featured_item_limit'    => 'required_if:featured_item_limit_type,limited',
            'featured_ads_duration_type' => 'required_if:type,advertisement|in:standard,package,custom',
            'featured_ads_duration_days' => 'nullable|required_if:featured_ads_duration_type,custom|integer|min:1',
        ];

        foreach ($otherLanguages as $lang) {
            $langId = $lang->id;
            $rules["name.$langId"] = 'nullable|string';
        }

        // Get package type - needed before validation for is_global enforcement
        $packageType = $resolvedPackageType;
        
        // Set is_global to 1 for advertisement packages before validation
        if ($packageType === 'advertisement' && !$request->has('is_global')) {
            $request->merge(['is_global' => 1]);
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            
            // Prepare key points for default language
            $defaultKeyPoints = $request->input("key_points.$defaultLangId", []);
            $defaultKeyPoints = array_filter($defaultKeyPoints); // Remove empty values
            
            // If old description exists, add it as first key point
            // $oldDescription = $request->input("description.$defaultLangId");
            // if (!empty($oldDescription) && !in_array($oldDescription, $defaultKeyPoints)) {
            //     array_unshift($defaultKeyPoints, $oldDescription);
            // }
            
            // Auto-calculate final price if not provided or if price/discount changed
            $finalPrice = $request->final_price;
            if (empty($finalPrice) || ($request->has('price') && $request->has('discount_in_percentage'))) {
                $price = (float) $request->price;
                $discount = (float) $request->discount_in_percentage;
                if ($price > 0 && $discount >= 0 && $discount <= 100) {
                    $discountAmount = ($price * $discount) / 100;
                    $finalPrice = $price - $discountAmount;
                }
            }
            
            // Set is_global: 1 for advertisement packages by default, otherwise use request value or 0
            $isGlobal = ($packageType === 'advertisement') ? 1 : ($request->is_global ?? 0);
            
            $data = [
                'name' => $request->input("name.$defaultLangId"),
                'price' => $request->price,
                'discount_in_percentage' => $request->discount_in_percentage,
                'final_price' => $finalPrice,
                'ios_product_id' => $request->ios_product_id,
                'duration' => ($request->package_duration_type == "limited") ? $request->duration : "unlimited",
                'type' => $packageType,
                'is_global' => $isGlobal,
                'key_points' => !empty($defaultKeyPoints) ? json_encode($defaultKeyPoints, JSON_UNESCAPED_UNICODE) : null,
            ];
            
            // Handle item limits and duration types based on package type
            if ($packageType === 'item_listing') {
                // item_limit can be "unlimited" or a number
                if ($request->ads_item_limit_type == "limited") {
                    $data['item_limit'] = $request->ads_item_limit;
                } else {
                    $data['item_limit'] = "unlimited";
                }
                $data['listing_duration_type'] = $request->ads_listing_duration_type ?? 'standard';
                // Set days: 30 for 'standard', null for 'package', custom value for 'custom'
                if ($request->ads_listing_duration_type == 'standard') {
                    $data['listing_duration_days'] = 30;
                } elseif ($request->ads_listing_duration_type == 'package') {
                    $data['listing_duration_days'] = $data['duration']; // Uses package duration
                } elseif ($request->ads_listing_duration_type == 'custom') {
                    $data['listing_duration_days'] = $request->ads_listing_duration_days;
                } else {
                    $data['listing_duration_days'] = null;
                }
            } else if ($packageType === 'advertisement') {
                // item_limit can be "unlimited" or a number
                if ($request->featured_item_limit_type == "limited") {
                    $data['item_limit'] = $request->featured_item_limit;
                } else {
                    $data['item_limit'] = "unlimited";
                }
                // Use listing_duration for advertisement packages too
                $data['listing_duration_type'] = $request->featured_ads_duration_type ?? 'standard';
                // Set days: 30 for 'standard', null for 'package', custom value for 'custom'
                if ($request->featured_ads_duration_type == 'standard') {
                    $data['listing_duration_days'] = 30;
                } elseif ($request->featured_ads_duration_type == 'package') {
                    $data['listing_duration_days'] = $data['duration']; // Uses package duration
                } elseif ($request->featured_ads_duration_type == 'custom') {
                    $data['listing_duration_days'] = $request->featured_ads_duration_days;
                } else {
                    $data['listing_duration_days'] = null;
                }
            }
            
            if ($request->hasFile('icon')) {
                $data['icon'] = FileService::compressAndUpload($request->file('icon'), $this->uploadFolder);
            }
            $package = Package::create($data);

            // Handle categories
            if ($request->is_global == 1) {
                // Global package - no categories needed
            } else {
                if (!empty($request->selected_categories)) {
                    $categoryMappings = collect($request->selected_categories)->map(function ($categoryId) use ($package) {
                        return [
                            'category_id' => $categoryId,
                            'package_id' => $package->id,
                        ];
                    })->toArray();
                    PackageCategory::upsert($categoryMappings, ['package_id', 'category_id']);
                }
            }

            // Handle translations with key points
            foreach ($otherLanguages as $lang) {
                $langId = $lang->id;
                $translatedName = $request->input("name.$langId");
                $translatedKeyPoints = $request->input("key_points.$langId", []);
                $translatedKeyPoints = array_filter($translatedKeyPoints); // Remove empty values
                
                // If old description exists for this language, add it as first key point
                // $oldDescription = $request->input("description.$langId");
                // if (!empty($oldDescription) && !in_array($oldDescription, $translatedKeyPoints)) {
                //     array_unshift($translatedKeyPoints, $oldDescription);
                // }
                
                if (!empty($translatedName) || !empty($translatedKeyPoints)) {
                    PackageTranslation::create([
                        'package_id' => $package->id,
                        'language_id' => $langId,
                        'name' => $translatedName ?? '',
                        'key_points' => !empty($translatedKeyPoints) ? json_encode($translatedKeyPoints, JSON_UNESCAPED_UNICODE) : null,
                    ]);
                }
            }
            
            DB::commit();
            ResponseService::successResponse('Package Successfully Added', $data);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "PackageController -> store method");
            ResponseService::errorResponse();
        }

    }

    public function show(Request $request) {
        ResponseService::noPermissionThenSendJson('advertisement-listing-package-list');
        $offset = $request->offset ?? 0;
        $limit = $request->limit ?? 10;
        $sort = $request->sort ?? 'id';
        $order = $request->order ?? 'DESC';

        $sql = Package::with(['translations', 'categories']);
         if (!empty($request->search)) {
            $sql = $sql->search($request->search);
        }
        if (! empty($request->filter)) {
                // Fix escaped JSON if middleware or frontend sent &quot; instead of "
                $filterString = html_entity_decode($request->filter, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                try {
                    $filterData = json_decode($filterString, false, 512, JSON_THROW_ON_ERROR);
                    $sql = $sql->filter($filterData);
                } catch (\JsonException $e) {
                    return response()->json(['error' => 'Invalid JSON format in filter parameter'], 400);
                }
            }

       
        $total = $sql->count();
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $result = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        foreach ($result as $key => $row) {
            $tempRow = $row->toArray();
            // Show "Global" or "Category Based" instead of actual category names
            $tempRow['category_names'] = $row->is_global == 1 ? 'Global' : 'Category Based';
            if (Auth::user()->can('advertisement-listing-package-update')) {
                $tempRow['operate'] = BootstrapTableService::editButton(route('package.edit', $row->id));
            }
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function edit($id) {
        ResponseService::noPermissionThenRedirect('advertisement-listing-package-update');
        $package = Package::with(['package_categories', 'translations'])->findOrFail($id);
        
        $translations = [];
        $translations[1] = [
            'name' => $package->name,
            'description' => $package->description,
        ];
        
        foreach ($package->translations as $translation) {
            $translations[$translation->language_id] = [
                'name' => $translation->name,
                'description' => $translation->description,
            ];
        }
        
        $selected_categories = $package->package_categories->pluck('category_id')->toArray();
        $selected_all_categories = $selected_categories;
        
        foreach ($selected_categories as $catId) {
            $categoryId = $catId;
            while ($categoryId) {
                $parent = Category::without('translations')->where('id', $categoryId)->value('parent_category_id');
                if ($parent) {
                    $selected_all_categories[] = $parent;
                    $categoryId = $parent;
                } else {
                    $categoryId = null;
                }
            }
        }
        
        $selected_all_categories = array_unique($selected_all_categories);
        $categories = Category::without('translations')
            ->where('status', 1)
            ->get()
            ->each->setAppends([]);
        $categories = HelperService::buildNestedChildSubcategoryObject($categories);
        $languages = CachingService::getLanguages()->values();
        $currency_symbol = CachingService::getSystemSettings('currency_symbol');
        
        return view('packages.edit', compact('package', 'categories', 'selected_categories', 'selected_all_categories', 'languages', 'translations', 'currency_symbol'));
    }

    public function update(Request $request, $id) {
        ResponseService::noPermissionThenSendJson('advertisement-listing-package-update');
        
        $languages = CachingService::getLanguages();
        $defaultLangId = 1;
        $otherLanguages = $languages->where('id', '!=', $defaultLangId);

        $package = Package::with('package_categories')->findOrFail($id);
        
        $rules = [
            "name.$defaultLangId"     => 'required|string',
            "description.$defaultLangId" => 'nullable|string',
            'price'                  => 'required|numeric',
            'discount_in_percentage' => 'required|numeric',
            'final_price'            => 'required|numeric',
            'package_duration_type'  => 'required|in:limited,unlimited',
            'duration'               => 'nullable|required_if:package_duration_type,limited|integer|min:1',
            'icon'                   => 'nullable|mimes:jpeg,jpg,png|max:7168',
            'is_global'              => 'nullable|in:0,1',
            'selected_categories'     => 'required_unless:is_global,1|array|min:1',
        ];
        
        // Add validation rules based on package type
        if ($package->type === 'item_listing') {
            $rules['ads_item_limit_type'] = 'required|in:limited,unlimited';
            // Allow "unlimited" string or integer for item_limit
            $rules['ads_item_limit'] = 'required_if:ads_item_limit_type,limited';
            $rules['ads_listing_duration_type'] = 'required|in:standard,package,custom';
            $rules['ads_listing_duration_days'] = 'nullable|required_if:ads_listing_duration_type,custom|integer|min:1';
        } else if ($package->type === 'advertisement') {
            $rules['featured_item_limit_type'] = 'required|in:limited,unlimited';
            // Allow "unlimited" string or integer for item_limit
            $rules['featured_item_limit'] = 'required_if:featured_item_limit_type,limited';
            $rules['featured_ads_duration_type'] = 'required|in:standard,package,custom';
            $rules['featured_ads_duration_days'] = 'nullable|required_if:featured_ads_duration_type,custom|integer|min:1';
        }

        foreach ($otherLanguages as $lang) {
            $langId = $lang->id;
            $rules["name.$langId"] = 'nullable|string';
            $rules["description.$langId"] = 'nullable|string';
        }

        // Set is_global to 1 for advertisement packages before validation
        if ($package->type === 'advertisement' && !$request->has('is_global')) {
            $request->merge(['is_global' => 1]);
        }
        
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            // Handle duration based on package type
            $durationValue = "unlimited";
            if ($package->type === 'item_listing') {
                if ($request->ads_item_limit_type == "limited" && !empty($request->ads_item_limit)) {
                    // For item listing, item limit is handled separately
                }
                if (isset($request->package_duration_type)) {
                    $durationValue = ($request->package_duration_type == "limited") ? $request->duration : "unlimited";
                } else {
                    $durationValue = ($request->duration_type == "limited") ? $request->duration : "unlimited";
                }
            } else if ($package->type === 'advertisement') {
                if (isset($request->package_duration_type)) {
                    $durationValue = ($request->package_duration_type == "limited") ? $request->duration : "unlimited";
                } else {
                    $durationValue = ($request->duration_type == "limited") ? $request->duration : "unlimited";
                }
            }
            
            // Handle item limit based on package type (can be "unlimited" or number for both types)
            $itemLimitValue = "unlimited";
            if ($package->type === 'item_listing') {
                // Allow "unlimited" string or number
                if ($request->ads_item_limit_type == "limited") {
                    $itemLimitValue = $request->ads_item_limit;
                } else {
                    $itemLimitValue = "unlimited";
                }
            } else if ($package->type === 'advertisement') {
                // Allow "unlimited" string or number
                if ($request->featured_item_limit_type == "limited") {
                    $itemLimitValue = $request->featured_item_limit;
                } else {
                    $itemLimitValue = "unlimited";
                }
            } else {
                // Fallback to old field names
                $itemLimitValue = ($request->item_limit_type == "limited") ? $request->item_limit : "unlimited";
            }
            
            // Auto-calculate final price if not provided or if price/discount changed
            $finalPrice = $request->final_price;
            if (empty($finalPrice) || ($request->has('price') && $request->has('discount_in_percentage'))) {
                $price = (float) $request->price;
                $discount = (float) $request->discount_in_percentage;
                if ($price > 0 && $discount >= 0 && $discount <= 100) {
                    $discountAmount = ($price * $discount) / 100;
                    $finalPrice = $price - $discountAmount;
                }
            }
            
            // Set is_global: 1 for advertisement packages by default, otherwise use request value or existing value
            $isGlobal = ($package->type === 'advertisement') ? 1 : ($request->is_global ?? $package->is_global ?? 0);
            
            $data = [
                'name' => $request->input("name.$defaultLangId"),
                'description' => $request->input("description.$defaultLangId"),
                'price' => $request->price,
                'discount_in_percentage' => $request->discount_in_percentage,
                'final_price' => $finalPrice,
                'ios_product_id' => $request->ios_product_id,
                'duration'   => $durationValue,
                'item_limit' => $itemLimitValue,
                'is_global'  => $isGlobal,
            ];
            
            // Handle listing duration types based on package type (use listing_duration for both types)
            if ($package->type === 'item_listing') {
                $data['listing_duration_type'] = $request->ads_listing_duration_type ?? 'standard';
                // Set days: 30 for 'standard', null for 'package', custom value for 'custom'
                if ($request->ads_listing_duration_type == 'standard') {
                    $data['listing_duration_days'] = 30;
                } elseif ($request->ads_listing_duration_type == 'package') {
                    $data['listing_duration_days'] = $durationValue; // Uses package duration
                } elseif ($request->ads_listing_duration_type == 'custom') {
                    $data['listing_duration_days'] = $request->ads_listing_duration_days;
                } else {
                    $data['listing_duration_days'] = null;
                }
            } else if ($package->type === 'advertisement') {
                // Use listing_duration for advertisement packages too
                $data['listing_duration_type'] = $request->featured_ads_duration_type ?? 'standard';
                // Set days: 30 for 'standard', null for 'package', custom value for 'custom'
                if ($request->featured_ads_duration_type == 'standard') {
                    $data['listing_duration_days'] = 30;
                } elseif ($request->featured_ads_duration_type == 'package') {
                    $data['listing_duration_days'] = $durationValue; // Uses package duration
                } elseif ($request->featured_ads_duration_type == 'custom') {
                    $data['listing_duration_days'] = $request->featured_ads_duration_days;
                } else {
                    $data['listing_duration_days'] = null;
                }
            }

            if ($request->hasFile('icon')) {
                $data['icon'] = FileService::compressAndReplace($request->file('icon'), $this->uploadFolder, $package->getRawOriginal('icon'));
            }

            // Prepare key points for default language
            $defaultKeyPoints = $request->input("key_points.$defaultLangId", []);
            $defaultKeyPoints = array_filter($defaultKeyPoints); // Remove empty values
            
            // If old description exists, add it as first key point
            $oldDescription = $request->input("description.$defaultLangId");
            if (!empty($oldDescription) && !in_array($oldDescription, $defaultKeyPoints)) {
                array_unshift($defaultKeyPoints, $oldDescription);
            }
            
            $data['key_points'] = !empty($defaultKeyPoints) ? json_encode($defaultKeyPoints, JSON_UNESCAPED_UNICODE) : null;
            
            $package->update($data);

            // Handle categories
            if ($request->is_global == 1) {
                // Delete all category associations for global package
                $package->package_categories()->delete();
            } else {
                $old_selected_category = $package->package_categories->pluck('category_id')->toArray();
                $new_selected_category = $request->selected_categories ?? [];

                // Delete removed categories
                if ($new_selected_category) {
                    foreach (array_diff($old_selected_category, $new_selected_category) as $category_id) {
                        $package->package_categories->first(function ($data) use ($category_id) {
                            return $data->category_id == $category_id;
                        })->delete();
                    }

                    // Add new categories
                    $newSelectedCategory = [];
                    foreach (array_diff($new_selected_category, $old_selected_category) as $category_id) {
                        $newSelectedCategory[] = [
                            'category_id' => $category_id,
                            'package_id' => $package->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if (count($newSelectedCategory) > 0) {
                        PackageCategory::insert($newSelectedCategory);
                    }
                }
            }

            foreach ($otherLanguages as $lang) {
                $langId = $lang->id;
                $translatedName = $request->input("name.$langId");
                $translatedDescription = $request->input("description.$langId");
                $translatedKeyPoints = $request->input("key_points.$langId", []);
                $translatedKeyPoints = array_filter($translatedKeyPoints); // Remove empty values
                
                // If old description exists for this language, add it as first key point
                if (!empty($translatedDescription) && !in_array($translatedDescription, $translatedKeyPoints)) {
                    array_unshift($translatedKeyPoints, $translatedDescription);
                }

                PackageTranslation::updateOrCreate(
                    [
                        'package_id' => $package->id,
                        'language_id' => $langId,
                    ],
                    [
                        'name' => $translatedName ?? '',
                        'description' => $translatedDescription ?? '',
                        'key_points' => !empty($translatedKeyPoints) ? json_encode($translatedKeyPoints, JSON_UNESCAPED_UNICODE) : null,
                    ]
                );
            }

            DB::commit();
            ResponseService::successResponse("Package Successfully Updated");
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "PackageController ->  update");
            ResponseService::errorResponse();
        }
    }
    public function userPackagesIndex() {
        ResponseService::noPermissionThenRedirect('user-package-list');
        return view('packages.user');
    }

    public function userPackagesShow(Request $request) {
        ResponseService::noPermissionThenSendJson('user-package-list');
        $offset = $request->offset ?? 0;
        $limit = $request->limit ?? 10;
        $sort = $request->sort ?? 'id';
        $order = $request->order ?? 'DESC';

        $sql = UserPurchasedPackage::with('user:id,name', 'package:id,name');
        if (!empty($request->search)) {
            $sql = $sql->search($request->search);
        }
        $total = $sql->count();
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $result = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        foreach ($result as $key => $row) {
            $rows[] = $row->toArray();
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function paymentTransactionIndex() {
        ResponseService::noPermissionThenRedirect('payment-transactions-list');
        return view('packages.payment-transactions');
    }

    public function paymentTransactionShow(Request $request) {
        ResponseService::noPermissionThenSendJson('payment-transactions-list');
        $offset = $request->offset ?? 0;
        $limit = $request->limit ?? 10;
        $sort = $request->sort ?? 'id';
        $order = $request->order ?? 'DESC';

        $sql = PaymentTransaction::with('user')->orderBy($sort, $order);
        if (!empty($request->search)) {
            $sql = $sql->search($request->search);
        }
        $total = $sql->count();
        $sql->skip($offset)->take($limit);
        $result = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();

        foreach ($result as $key => $row) {
            $tempRow = $row->toArray();
            $tempRow['created_at'] = Carbon::createFromFormat('Y-m-d H:i:s', $row->created_at)->format('d-m-y H:i:s');
            $tempRow['updated_at'] = Carbon::createFromFormat('Y-m-d H:i:s', $row->updated_at)->format('d-m-y H:i:s');
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function bankTransferIndex() {
        ResponseService::noPermissionThenRedirect('payment-transactions-list');
        return view('packages.bank-transfer');
    }
    public function bankTransferShow(Request $request) {
        ResponseService::noPermissionThenSendJson('payment-transactions-list');
        $offset = $request->offset ?? 0;
        $limit = $request->limit ?? 10;
        $sort = $request->sort ?? 'id';
        $order = $request->order ?? 'DESC';

        $sql = PaymentTransaction::with('user')->where('payment_gateway' ,'BankTransfer')->orderBy($sort, $order);
        if (!empty($request->search)) {
            $sql = $sql->search($request->search);
        }
        $total = $sql->count();
        $sql->skip($offset)->take($limit);
        $result = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        foreach ($result as $key => $row) {
            $tempRow = $row->toArray();
            $tempRow['created_at'] = Carbon::createFromFormat('Y-m-d H:i:s', $row->created_at)->format('d-m-y H:i:s');
            $tempRow['updated_at'] = Carbon::createFromFormat('Y-m-d H:i:s', $row->updated_at)->format('d-m-y H:i:s');
            if (Auth::user()->can('featured-advertisement-package-update')) {
                $tempRow['operate'] = BootstrapTableService::editButton(route('package.bank-transfer.update-status', $row->id), true, '#editStatusModal', 'edit-status', $row->id);
            }
            $tempRow['payment_status'] = $row->payment_status_uper;
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }
    public function updateStatus(Request $request, $id)
    {

        $validator = Validator::make($request->all(), [
            'payment_status' => 'required|in:succeed,rejected'
        ]);
        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        try {
                DB::beginTransaction();

                $transaction = PaymentTransaction::findOrFail($id);
                $transaction->update(['payment_status' => $request->payment_status]);
                
                $userTokens = UserFcmToken::where('user_id', $transaction->user_id)->pluck('fcm_token')->toArray();

                if ($request->payment_status === 'succeed') {
                    $parts = explode('-', $transaction->order_id);
                    $package_id = $parts[2];
                    $package = Package::find((int) $package_id);
                    
                if ($package) {
                        UserPurchasedPackage::create([
                            'package_id'  => $package->id,
                            'user_id'     => $transaction->user_id,
                            'start_date'  => Carbon::now(),
                            'end_date'    => $package->duration == "unlimited" ? null : Carbon::now()->addDays($package->duration),
                            'total_limit' => $package->item_limit == "unlimited" ? null : $package->item_limit,
                            'payment_transactions_id' => $transaction->id,
                            'listing_duration_type' => $package->listing_duration_type,
                        'listing_duration_days' => $package->listing_duration_days
                        ]);
                    }
                }

                DB::commit(); // Close the DB transaction as soon as database work is don  e

                // NOW handle the external notification logic after commit
                if (!empty($userTokens)) {
                    if ($request->payment_status === 'succeed') {
                        $title = "Package Purchased";
                        $body = 'Amount :- ' . $transaction->amount;
                        NotificationService::sendFcmNotification($userTokens, $title, $body, 'payment');
                    } elseif ($request->payment_status === 'rejected') {
                        $title = "Payment Rejected";
                        $body = "Your payment of " . $transaction->amount . " has been rejected.";
                        NotificationService::sendFcmNotification($userTokens, $title, $body, 'payment');
                    }
                }
            return ResponseService::successResponse('Payment Status Updated Successfully');
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, 'PackageController ->updateStatus');
            return ResponseService::errorResponse('Something Went Wrong');
        }
    }

}
