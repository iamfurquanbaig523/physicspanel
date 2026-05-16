<?php

namespace App\Http\Controllers;

use App\Http\Resources\ItemCollection;
use App\Models\Area;
use App\Models\BlockUser;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Chat;
use App\Models\City;
use App\Models\ContactUs;
use App\Models\Country;
use App\Models\Currency;
use App\Models\CustomField;
use App\Models\Faq;
use App\Models\Favourite;
use App\Models\FeaturedItems;
use App\Models\FeatureSection;
use App\Models\Item;
use App\Models\ItemCustomFieldValue;
use App\Models\ItemImages;
use App\Models\ItemOffer;
use App\Models\JobApplication;
use App\Models\Language;
use App\Models\Notifications;
use App\Models\NumberOtp;
use App\Models\Package;
use App\Models\PaymentConfiguration;
use App\Models\PaymentTransaction;
use App\Models\ReportReason;
use App\Models\SellerRating;
use App\Models\SeoSetting;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\SocialLogin;
use App\Models\State;
use App\Models\Tip;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Models\UserPurchasedPackage;
use App\Models\UserReports;
use App\Models\VerificationField;
use App\Models\VerificationFieldValue;
use App\Models\VerificationRequest;
use App\Services\CachingService;
use App\Services\CurrencyFormatterService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\NotificationService;
use App\Services\Payment\PaymentService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use JsonException;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Throwable;
use Twilio\Rest\Client as TwilioRestClient;

class ApiController extends Controller
{
    private string $uploadFolder;

    public function __construct()
    {
        $this->uploadFolder = 'item_images';
        if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER) && ! empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $this->middleware('auth:sanctum');
        }
    }

    public function getSystemSettings(Request $request)
    {
        try {
            $query = Setting::select(['id', 'name', 'value', 'type']); // include 'id' to support translation loading

            if (! empty($request->type)) {
                $query->where('name', $request->type);
            }

            $settings = $query->with('translations')->get();

            $tempRow = [];

            foreach ($settings as $row) {
                if (in_array($row->name, [
                    'account_holder_name',
                    'bank_name',
                    'account_number',
                    'ifsc_swift_code',
                    'bank_transfer_status',
                    'place_api_key',
                    'mail_password',
                    'mail_from_address',
                    'mail_username',
                    'twilio_account_sid',
                    'twilio_auth_token',
                    'twilio_my_phone_number',
                    'admin_user_email',
                    'admin_user_password',
                    'S3_aws_access_key_id',
                    's3_aws_secret_access_key',
                    's3_aws_default_region',
                    's3_aws_bucket',
                    's3_aws_url'
                ])) {
                    continue;
                }
                $tempRow[$row->name] = $row->translated_value ?? $row->value;
            }

            // --- determine current language ---
            $languageCode = $request->header('Content-Language') ?? app()->getLocale();
            $language = Language::where('code', $languageCode)->first();

            if (! $language) {
                $defaultLanguageCode = Setting::where('name', 'default_language')->value('value');
                $language = Language::where('code', $defaultLanguageCode)->first();
            }

            $tempRow['demo_mode'] = config('app.demo_mode');
            $tempRow['languages'] = CachingService::getLanguages();
            $tempRow['admin'] = User::role('Super Admin')->select(['name', 'profile'])->first();

            // 👇 add current language info
            $tempRow['current_language'] = $language?->code ?? app()->getLocale();

            ResponseService::successResponse(__('Data Fetched Successfully'), $tempRow);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getSystemSettings');
            ResponseService::errorResponse();
        }
    }

    public function userSignup(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:email,google,phone,apple',
                'firebase_id' => 'nullable|string',
                'flag' => 'boolean',
                'platform_type' => 'nullable|in:android,ios',
                'region_code' => 'nullable|string',
                'country_code' => 'nullable|string',
                'mobile' => 'required_if:type,phone',
                'password' => 'nullable|string|min:6',
                'is_login' => 'boolean',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $type = $request->type;
            $firebase_id = $request->firebase_id;
            if ($type == 'phone' && ! empty($request->password) && $request->boolean('is_login')) {

                $mobile = ltrim($request->mobile, '+');
                $countryCode = ltrim($request->country_code, '+');
                $user = User::where('type', 'phone')
                    ->where('mobile', $mobile)  
                    ->withTrashed()
                    ->first();
                
                    if (!$user) {
                        return ResponseService::errorResponse(
                            __('User not found. Please signup first.')
                        );
                    }

                    if ($user->deleted_at) {
                        return ResponseService::errorResponse(
                            __('User is deactivated. Please contact the administrator.')
                        );
                    }

                    // password exists check
                    if (empty($user->password)) {
                        return ResponseService::errorResponse(
                            __('Password is not set. Please set your password using the forgot password option.')
                        );
                    }

                    if (! Hash::check($request->password, $user->password)) {
                        return ResponseService::errorResponse(__('Invalid password.'));
                    }
            }
            $socialLogin = null;
            if (! empty($firebase_id)) {
                $socialLogin = SocialLogin::where('firebase_id', $firebase_id)
                    ->where('type', $type)
                    ->with('user', fn($q) => $q->withTrashed())
                    ->whereHas('user', fn($q) => $q->role('User'))
                    ->first();
            }

            if ($socialLogin && ! empty($socialLogin->user->deleted_at)) {
                return ResponseService::errorResponse(__('User is deactivated. Please Contact the administrator'));
            }
            if (empty($socialLogin)) {
                DB::beginTransaction();

                if ($request->type == 'phone') {
                    $unique['mobile'] = $request->mobile;
                } else {
                    $unique['email'] = $request->email;
                }

                $existingUser = User::withTrashed()->where($unique)->first();

                if ($existingUser && $existingUser->trashed()) {
                    return ResponseService::errorResponse(__('Your account has been deactivated.'), null, config('constants.RESPONSE_CODE.DEACTIVATED_ACCOUNT'));
                }

                $dataToUpdate = [
                    'region_code' => $request->region_code ?? null,
                    'profile' => $request->hasFile('profile') ? $request->file('profile')->store('user_profile', 'public') : $request->profile,
                ];
                if (! empty($request->password)) {
                    $dataToUpdate['password'] = Hash::make($request->password);
                }

                $user = User::updateOrCreate($unique, array_merge($request->all(), $dataToUpdate));

                if (! empty($firebase_id)) {
                    SocialLogin::updateOrCreate(
                        ['type' => $type, 'user_id' => $user->id],
                        ['firebase_id' => $firebase_id]
                    );
                }
                $user->assignRole('User');
                Auth::login($user);
                $auth = User::find($user->id);
                DB::commit();
            } else {
                if ($socialLogin->user && ! empty($countryCode)) {
                    $socialLogin->user->update([
                        'country_code' => ltrim($countryCode, '+'),
                    ]);
                }
                // Social Login exists
                Auth::login($socialLogin->user);
                $auth = Auth::user();
            }
            if (! $auth->hasRole('User')) {
                ResponseService::errorResponse(__('Invalid Login Credentials'), null, config('constants.RESPONSE_CODE.INVALID_LOGIN'));
            }
            if (! empty($request->fcm_id)) {
                UserFcmToken::updateOrCreate(['fcm_token' => $request->fcm_id], ['user_id' => $auth->id, 'platform_type' => $request->platform_type, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
            }
            $auth->fcm_id = $request->fcm_id;
            if (! empty($request->registration)) {
                // If registration is passed then don't create token
                $token = null;
            } else {
                $token = $auth->createToken($auth->name ?? '')->plainTextToken;
            }
            if ($auth) {
                NotificationService::sendNewDeviceLoginEmail($auth, $request);
            }
            ResponseService::successResponse(__('User logged-in successfully'), $auth, ['token' => $token]);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, 'API Controller -> Signup');
            ResponseService::errorResponse();
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'number' => 'required|string',
                'country_code' => 'required|string',
                'new_password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $mobile = ltrim($request->number, '+');
            $countryCode = ltrim($request->country_code, '+');

            $mobileWithCode = $countryCode . $mobile;

            $user = User::where('type', 'phone')
                ->where('mobile', $mobile)->first();

            if (! $user) {
                ResponseService::errorResponse(__('User not found.'));
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            ResponseService::successResponse(__('Password reset successfully.'));
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> resetPassword');
            ResponseService::errorResponse();
        }
    }

    public function getUser(Request $request)
    {
        try {
            $auth = Auth::user();

            if (! $auth) {
                ResponseService::errorResponse(__('User not authenticated'));
            }

            if (! $auth->hasRole('User')) {
                ResponseService::errorResponse(__('Invalid User Role'));
            }
            $user = User::find($auth->id);

            ResponseService::successResponse(__('User fetched successfully'), $user);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> GetUser');
            ResponseService::errorResponse();
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string',
                'profile' => 'nullable|mimes:jpg,jpeg,png|max:7168',
                'email' => 'nullable|email|unique:users,email,' . Auth::user()->id,
                'mobile' => [
                    'nullable',
                    Rule::unique('users')->ignore(Auth::user()->id)->where(function ($query) use ($request) {
                        return $query->where('country_code', '+' . $request->country_code);
                    }),
                ],
                'fcm_id' => 'nullable',
                'address' => 'nullable',
                'show_personal_details' => 'boolean',
                'country_code' => 'nullable|string',
                'region_code' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $app_user = Auth::user();
            // Email should not be updated when type is google.
            $data = $app_user->type == 'google' ? $request->except('email') : $request->all();

            if ($request->hasFile('profile')) {
                $data['profile'] = FileService::compressAndReplace($request->file('profile'), 'profile', $app_user->getRawOriginal('profile'));
            }

            if (! empty($request->fcm_id)) {
                UserFcmToken::updateOrCreate(['fcm_token' => $request->fcm_id], ['user_id' => $app_user->id, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
            }
            $data['show_personal_details'] = $request->show_personal_details;

            $app_user->update($data);
            ResponseService::successResponse(__('Profile Updated Successfully'), $app_user);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> updateProfile');
            ResponseService::errorResponse();
        }
    }

    public function getPackage(Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'platform' => 'nullable|in:android,ios',
            'type' => 'nullable|in:advertisement,item_listing',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $packages = Package::with(['translations', 'categories', 'package_categories'])->where('status', 1);

            if (Auth::check()) {
                $packages = $packages->with('user_purchased_packages', function ($q) {
                    $q->onlyActive();
                });
            }

            if (isset($request->platform) && $request->platform == 'ios') {
                $packages->whereNotNull('ios_product_id');
            }

            if (! empty($request->type)) {
                $packages = $packages->where('type', $request->type);
            }
            $packages = $packages->orderBy('id', 'ASC')->get();

            $formatter = app(CurrencyFormatterService::class);

            $packages = $packages->map(function ($package) use ($formatter) {
                if (Auth::check()) {
                    $package['is_active'] = count($package->user_purchased_packages) > 0;
                } else {
                    $package['is_active'] = false;
                }

                // Only load package categories if package is not global
                if ($package->is_global != 1) {
                    // Add selected category IDs
                    $package['selected_category_ids'] = $package->package_categories->pluck('category_id')->toArray();

                    // Add categories data
                    $package['categories'] = $package->categories->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'slug' => $category->slug ?? null,
                            'parent_category_id' => $category->parent_category_id ?? null,
                        ];
                    });
                } else {
                    $package['selected_category_ids'] = [];
                    $package['categories'] = [];
                }

                // Decode key_points JSON if it exists
                if (! empty($package->key_points)) {
                    $keyPoints = json_decode($package->key_points, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($keyPoints)) {
                        $package['key_points'] = $keyPoints;
                    } else {
                        // If it's already an array or invalid JSON, keep as is
                        $package['key_points'] = is_array($package->key_points) ? $package->key_points : [];
                    }
                } else {
                    $package['key_points'] = [];
                }
                $iso_code = Setting::where('name', 'currency_iso_code')->value('value');
                $symbol = Setting::where('name', 'currency_symbol')->value('value');
                $position = Setting::where('name', 'currency_symbol_position')->value('value');

                $currency = (object) [
                    'iso_code' => $iso_code,
                    'symbol' => $symbol,
                    'symbol_position' => $position,
                ];

                // Formatted price fields
                $package['formatted_final_price'] = $formatter->formatPrice($package->final_price ?? 0, $currency);
                // If original/base price exists, format it too; fall back to final_price
                $basePrice = $package->price ?? $package->final_price ?? 0;
                $package['formatted_price'] = $formatter->formatPrice($basePrice, $currency);

                // If listing_duration_type is null, set it to 'package' and use package duration
                if (empty($package->listing_duration_type)) {
                    $package['listing_duration_type'] = 'package';
                    $package['listing_duration_days'] = $package->duration;
                }

                $package->user_purchased_packages = $package->user_purchased_packages->map(function ($purchased) use ($package) {
                    // 1. Calculate Duration/Days
                    if ($purchased->start_date && $purchased->end_date) {
                        $start = \Carbon\Carbon::parse($purchased->start_date);
                        $end = \Carbon\Carbon::parse($purchased->end_date);
                        $purchased['duration'] = $start->diffInDays($end);
                    } else {
                        $purchased['duration'] = "unlimited";
                    }

                    // 2. Map item_limit
                    $purchased['item_limit'] = $purchased->total_limit;

                    // 3. Fallback Logic for listing_duration_type & days
                    // Priority: Purchased Record -> Parent Package Record -> Default Setup
                    $purchased['listing_duration_type'] = $purchased->listing_duration_type
                        ?? $package->listing_duration_type
                        ?? 'package';

                    $days = $purchased->listing_duration_days ?? $package->listing_duration_days ?? $package->duration;

                    // If the final determined value is 0 or "0", return "unlimited"
                    $purchased['listing_duration_days'] = ($days == 0 && $days !== null) ? "unlimited" : $days;

                    return $purchased;
                });
                return $package;
            });
            ResponseService::successResponse(__('Data Fetched Successfully'), $packages);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getPackage');
            ResponseService::errorResponse();
        }
    }

    public function assignFreePackage(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'package_id' => 'required|exists:packages,id',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $user = Auth::user();

            $package = Package::where(['final_price' => 0, 'id' => $request->package_id])->firstOrFail();
            $activePackage = UserPurchasedPackage::where(['package_id' => $request->package_id, 'user_id' => Auth::user()->id])->first();
            if (! empty($activePackage)) {
                ResponseService::errorResponse(__('You already have purchased this package'));
            }

            UserPurchasedPackage::create([
                'user_id' => $user->id,
                'package_id' => $request->package_id,
                'start_date' => Carbon::now(),
                'total_limit' => $package->item_limit == 'unlimited' ? null : $package->item_limit,
                'end_date' => $package->duration == 'unlimited' ? null : Carbon::now()->addDays($package->duration),
                'listing_duration_type' => $package->listing_duration_type,
                'listing_duration_days' => $package->listing_duration_days
            ]);
            ResponseService::successResponse(__('Package Purchased Successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> assignFreePackage');
            ResponseService::errorResponse();
        }
    }

    public function getLimits(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'package_type' => 'required|in:item_listing,advertisement',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $setting = Setting::where('name', 'free_ad_listing')->first()['value'];
            if ($setting == 1 && $request->package_type != 'advertisement') {
                return ResponseService::successResponse(__('User is allowed to create Advertisement'));
            }
            $user_package = UserPurchasedPackage::onlyActive()->whereHas('package', function ($q) use ($request) {
                $q->where('type', $request->package_type);
            })->count();
            if ($user_package > 0) {
                ResponseService::successResponse(__('User is allowed to create Advertisement'));
            }
            ResponseService::errorResponse(__('User is not allowed to create Advertisement'), $user_package);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getLimits');
            ResponseService::errorResponse();
        }
    }

    public function getCurrencies(Request $request)
    {
        try {
            $countryName = $request->query('country');

            $selectedCurrencyId = null;

            // If country param is passed
            if ($countryName) {
                $country = Country::where('name', $countryName)
                    ->select('id', 'currency_id')
                    ->first();

                $selectedCurrencyId = $country?->id;
            }

            $currencies = Currency::select(
                'id',
                'iso_code',
                'symbol',
                'symbol_position',
                'country_id'
            )
                ->orderBy('iso_code')
                ->get()
                ->map(function ($currency) use ($selectedCurrencyId) {
                    return [
                        'id' => $currency->id,
                        'iso_code' => $currency->iso_code,
                        'symbol' => $currency->symbol,
                        'position' => $currency->symbol_position,
                        'selected' => $selectedCurrencyId == $currency->country_id ? 1 : 0,
                    ];
                });
            ResponseService::successResponse(__('Currency fetched Successfully'), $currencies);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getCurrencies');
            ResponseService::errorResponse();
        }
    }

    public function addItem(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'category_id' => 'required|integer',
                'description' => 'required',
                'latitude' => 'required',
                'longitude' => 'required',
                'address' => 'required',
                'contact' => 'nullable|numeric',
                'show_only_to_premium' => 'required|boolean',
                'video_link' => 'nullable|url',
                'gallery_images' => 'nullable|array|min:1',
                'gallery_images.*' => 'nullable|mimes:jpeg,png,jpg|max:7168',
                'image' => 'required|mimes:jpeg,png,jpg|max:7168',
                'country' => 'required',
                'state' => 'nullable',
                'city' => 'required',
                'custom_field_files' => 'nullable|array',
                'custom_field_files.*' => 'nullable|mimes:jpeg,png,jpg,pdf,doc|max:7168',
                'slug' => [
                    'nullable',
                    'regex:/^(?!-)(?!.*--)(?!.*-$)(?!-$)[a-z0-9-]+$/',
                ],
                'region_code' => 'nullable|string',
                'currency_id' => 'required|exists:currencies,id',
                'all_category_ids' => 'nullable|array',
                'all_category_ids.*' => 'integer|exists:categories,id',
            ]);
            $translations = json_decode($request->input('translations', '{}'), true, 512, JSON_THROW_ON_ERROR);
            if (! empty($translations)) {
                foreach ($translations as $languageId => $translation) {
                    Validator::make($translation, [
                        'name' => 'required|string|max:255',
                        'slug' => 'nullable|regex:/^[a-z0-9-]+$/',
                        'description' => 'nullable|string',
                        'address' => 'nullable|string',
                        'video_link' => 'nullable|url',
                        'rejected_reason' => 'nullable|string',
                        'admin_edit_reason' => 'nullable|string',
                    ])->validate();
                }
            }
            $category = Category::findOrFail($request->category_id);

            $isJobCategory = $category->is_job_category;
            $isPriceOptional = $category->price_optional;

            if ($isJobCategory || $isPriceOptional) {
                $validator = Validator::make($request->all(), [
                    'min_salary' => 'nullable|numeric|min:0',
                    'max_salary' => 'nullable|numeric|gte:min_salary',
                ]);
            } else {
                $validator = Validator::make($request->all(), [
                    'price' => 'required|numeric|min:0',
                ]);
            }
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            DB::beginTransaction();
            $user = Auth::user();
            $free_ad_listing = Setting::where('name', 'free_ad_listing')->value('value') ?? 0;
            $auto_approve_item = Setting::where('name', 'auto_approve_item')->value('value') ?? 0;

            if ($auto_approve_item == 1 || $user->auto_approve_item == 1) {
                $status = 'approved';
            } else {
                $status = 'review';
            }

            // Get all active packages for the user
            $user_packages = null;
            $selectedPackageId = null;
            $selectedUserPackage = null;

            // Only check package if free_ad_listing is not enabled
            if ($free_ad_listing != 1) {
                // Get ALL active packages for the user
                $user_packages = UserPurchasedPackage::onlyActive()
                    ->whereHas('package', static function ($q) {
                        $q->where('type', 'item_listing');
                    })
                    ->where('user_id', $user->id)
                    ->with('package.package_categories')
                    ->get();

                if ($user_packages->isEmpty()) {
                    DB::rollBack();
                    ResponseService::errorResponse(__('No Active Package found for Advertisement Creation'));
                }

                // Get all_category_ids from request (should contain category and all parent categories)
                $allCategoryIds = $request->input('all_category_ids', []);

                // If all_category_ids not provided, build it from category_id
                if (empty($allCategoryIds) && $request->category_id) {
                    $allCategoryIds = [$request->category_id];
                    $currentCategoryId = $request->category_id;

                    // Traverse up the parent chain
                    while ($currentCategoryId) {
                        $parentCategory = Category::find($currentCategoryId);
                        if ($parentCategory && $parentCategory->parent_category_id) {
                            $allCategoryIds[] = $parentCategory->parent_category_id;
                            $currentCategoryId = $parentCategory->parent_category_id;
                        } else {
                            break;
                        }
                    }
                    $allCategoryIds = array_unique($allCategoryIds);
                }

                // Check for global package first
                $globalPackage = $user_packages->firstWhere(function ($userPackage) {
                    return $userPackage->package && $userPackage->package->is_global == 1;
                });

                if ($globalPackage) {
                    // Use global package
                    $selectedPackageId = $globalPackage->package_id;
                    $selectedUserPackage = $globalPackage;
                } else {
                    // No global package, check if any package contains any category from all_category_ids
                    $matchingPackage = null;

                    foreach ($user_packages as $user_package) {
                        if ($user_package->package && $user_package->package->is_global != 1) {
                            $packageCategoryIds = $user_package->package->package_categories
                                ->pluck('category_id')
                                ->toArray();

                            // Check if any category from all_category_ids is in this package
                            $hasMatchingCategory = ! empty(array_intersect($allCategoryIds, $packageCategoryIds));

                            if ($hasMatchingCategory) {
                                $matchingPackage = $user_package;
                                break;
                            }
                        }
                    }

                    if ($matchingPackage) {
                        $selectedPackageId = $matchingPackage->package_id;
                        $selectedUserPackage = $matchingPackage;
                    } else {
                        DB::rollBack();
                        ResponseService::errorResponse(__('Selected category is not available in your package'));
                    }
                }
            }

            // Increment used_limit for the selected package
            if ($selectedUserPackage) {
                $selectedUserPackage->used_limit++;
                $selectedUserPackage->save();
            }

            $slug = trim($request->input('slug') ?? '');
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($slug));
            $slug = trim($slug, '-');
            if (empty($slug)) {
                $slug = HelperService::generateRandomSlug();
            }
            $uniqueSlug = HelperService::generateUniqueSlug(new Item, $slug);

            // Calculate expiry date based on package listing duration
            $package = $selectedPackageId ? Package::find($selectedPackageId) : null;
            $expiryDate = HelperService::calculateItemExpiryDate($package, $selectedUserPackage);

            $data = [
                ...$request->all(),
                'name' => $request->name,
                'slug' => $uniqueSlug,
                'status' => $status,
                'active' => 'deactive',
                'user_id' => $user->id,
                'package_id' => $selectedPackageId ?? null,
                'expiry_date' => $expiryDate,
            ];
            if ($request->hasFile('image')) {
                $data['image'] = FileService::compressAndUpload($request->file('image'), $this->uploadFolder, true);
            }
            $item = Item::create($data);
            if (! empty($translations)) {
                foreach ($translations as $languageId => $translationData) {
                    // Optional: Check if language ID exists
                    if (Language::where('id', $languageId)->exists()) {
                        $item->translations()->create([
                            'language_id' => $languageId,
                            'name' => $translationData['name'],
                            'description' => $translationData['description'] ?? '',
                            'address' => $translationData['address'] ?? '',
                            'rejected_reason' => $translationData['rejected_reason'] ?? null,
                            'admin_edit_reason' => $translationData['admin_edit_reason'] ?? null,
                        ]);
                    }
                }
            }

            if ($request->hasFile('gallery_images')) {
                $galleryImages = [];
                foreach ($request->file('gallery_images') as $file) {
                    $galleryImages[] = [
                        'image' => FileService::compressAndUpload($file, $this->uploadFolder, true),
                        'item_id' => $item->id,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                }

                if (count($galleryImages) > 0) {
                    ItemImages::insert($galleryImages);
                }
            }
            if ($request->custom_fields) {
                $itemCustomFieldValues = [];
                foreach (json_decode($request->custom_fields, true, 512, JSON_THROW_ON_ERROR) as $key => $custom_field) {
                    $itemCustomFieldValues[] = [
                        'item_id' => $item->id,
                        'language_id' => 1,
                        'custom_field_id' => $key,
                        'value' => json_encode($custom_field, JSON_THROW_ON_ERROR),
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::insert($itemCustomFieldValues);
                }
            }

            if ($request->custom_field_files) {
                $itemCustomFieldValues = [];
                foreach ($request->custom_field_files as $key => $file) {
                    $itemCustomFieldValues[] = [
                        'item_id' => $item->id,
                        'language_id' => 1,
                        'custom_field_id' => $key,
                        'value' => ! empty($file) ? FileService::upload($file, 'custom_fields_files') : '',
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::insert($itemCustomFieldValues);
                }
            }
            // Handle Translated Custom Field Values
            if ($request->has('custom_field_translations')) {
                $customFieldTranslations = $request->input('custom_field_translations');

                if (! is_array($customFieldTranslations)) {
                    $customFieldTranslations = html_entity_decode($customFieldTranslations);
                    $customFieldTranslations = json_decode($customFieldTranslations, true, 512, JSON_THROW_ON_ERROR);
                }

                $translatedEntries = [];

                foreach ($customFieldTranslations as $languageId => $fieldsByCustomField) {
                    foreach ($fieldsByCustomField as $customFieldId => $translatedValue) {
                        $translatedEntries[] = [
                            'item_id' => $item->id,
                            'custom_field_id' => $customFieldId,
                            'language_id' => $languageId,
                            'value' => json_encode($translatedValue, JSON_THROW_ON_ERROR),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if (! empty($translatedEntries)) {
                    ItemCustomFieldValue::insert($translatedEntries);
                }
            }

            // Optimize: Only load essential relationships to prevent memory timeout
            $result = Item::with([
                'user:id,name,email,mobile,profile,country_code',
                'category:id,name,image,is_job_category,price_optional',
                'gallery_images:id,image,item_id',
                'area:id,name',
                'translations',
            ])
                ->where('items.id', $item->id)
                ->first();

            // Load expensive relationships only if needed (lazy loading) to prevent memory timeout
            if ($result) {
                $result->loadMissing(['featured_items', 'favourites']);
                // Only load custom fields if they exist (avoid loading all nested translations)
                if ($result->item_custom_field_values()->exists()) {
                    $result->load('item_custom_field_values.custom_field:id,name,type');
                }
            }

            $result = new ItemCollection(collect([$result]));

            DB::commit();
            ResponseService::successResponse(__('Advertisement Added Successfully'), $result);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, 'API Controller -> addItem');
            ResponseService::errorResponse();
        }
    }

    public function getItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer',
            'offset' => 'nullable|integer',
            'id' => 'nullable',
            'custom_fields' => 'nullable',
            'slug' => 'nullable|string',
            'category_id' => 'nullable',
            'user_id' => 'nullable',
            'min_price' => 'nullable',
            'max_price' => 'nullable',
            'sort_by' => 'nullable|in:new-to-old,old-to-new,price-high-to-low,price-low-to-high,popular_items',
            'posted_since' => 'nullable|in:all-time,today,within-1-week,within-2-week,within-1-month,within-3-month',
            'current_page' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            // TODO : need to simplify this whole module
            $maxPrice = Item::max('price') ?? 0;

            $sql = Item::with(
                'user:id,name,email,mobile,profile,created_at,is_verified,show_personal_details,country_code',
                'category:id,name,image,is_job_category,price_optional',
                'gallery_images:id,image,item_id',
                'featured_items',
                'favourites',
                'item_custom_field_values.custom_field.translations',
                'area:id,name',
                'job_applications',
                'translations',
                'currency',

            )
                ->withCount('featured_items')
                ->withCount('job_applications')
                ->select('items.*')
                ->whereHas('category', function ($q) {
                    $q->where('status', '!=', 0)
                        ->where(function ($query) {
                            // Either no parent or parent status != 0
                            $query->whereDoesntHave('parent') // no parent category
                                ->orWhereHas('parent', function ($q2) {
                                    $q2->where('status', '!=', 0);
                                });
                        });
                })
                ->when($request->id, function ($sql) use ($request) {
                    $sql->where('items.id', $request->id);
                })->when(($request->category_id), function ($sql) use ($request) {
                    $category = Category::where('id', $request->category_id)->with('children')->first();
                    $categoryIDS = HelperService::findAllCategoryIds(collect([$category]));

                    return $sql->whereIn('category_id', $categoryIDS);
                })->when(($request->category_slug), function ($sql) use ($request) {
                    $category = Category::where('slug', $request->category_slug)->with('children')->first();
                    $categoryIDS = HelperService::findAllCategoryIds(collect([$category]));

                    return $sql->whereIn('category_id', $categoryIDS);
                })->when((isset($request->min_price) || isset($request->max_price)), function ($sql) use ($request, $maxPrice) {
                    $min_price = $request->min_price ?? 0;
                    $max_price = $request->max_price ?? $maxPrice;

                    return $sql->whereBetween('price', [$min_price, $max_price]);
                })->when($request->posted_since, function ($sql) use ($request) {
                    return match ($request->posted_since) {
                        // Qualify column to avoid ambiguity once joins are applied (e.g. featured_items)
                        'today' => $sql->whereDate('items.created_at', '>=', now()),
                        'within-1-week' => $sql->whereDate('items.created_at', '>=', now()->subDays(7)),
                        'within-2-week' => $sql->whereDate('items.created_at', '>=', now()->subDays(14)),
                        'within-1-month' => $sql->whereDate('items.created_at', '>=', now()->subMonths()),
                        'within-3-month' => $sql->whereDate('items.created_at', '>=', now()->subMonths(3)),
                        default => $sql
                    };
                })->when($request->area_id, function ($sql) use ($request) {
                    return $sql->where('area_id', $request->area_id);
                })->when($request->user_id, function ($sql) use ($request) {
                    return $sql->where('user_id', $request->user_id);
                })->when($request->slug, function ($sql) use ($request) {
                    return $sql->where('slug', $request->slug);
                });

            if ($request->sort_by == 'new-to-old') {
                $sql->orderBy('items.id', 'DESC');
            } elseif ($request->sort_by == 'old-to-new') {
                $sql->orderBy('items.id', 'ASC');
            } elseif ($request->sort_by == 'price-high-to-low') {
                $sql->orderByRaw('
                    COALESCE(price, max_salary, min_salary, 0) DESC
                ');
            } elseif ($request->sort_by == 'price-low-to-high') {
                $sql->orderByRaw('
                    COALESCE(price, min_salary, max_salary, 0) ASC
                ');
            } elseif ($request->sort_by == 'popular_items') {
                $sql->orderBy('clicks', 'DESC');
            } else {
                $sql->orderBy('items.id', 'DESC');
            }

            // Status
            if (! empty($request->status)) {
                if (in_array($request->status, ['review', 'approved', 'rejected', 'sold out', 'soft rejected', 'permanent rejected', 'resubmitted'])) {
                    $sql->where('status', $request->status)->getNonExpiredItems()->whereNull('deleted_at');
                } elseif ($request->status == 'inactive') {
                    // If status is inactive then display only trashed items
                    $sql->onlyTrashed()->getNonExpiredItems();
                } elseif ($request->status == 'featured') {
                    // If status is featured then display only featured items
                    $sql->where('status', 'approved')->has('featured_items')->getNonExpiredItems();
                } elseif ($request->status == 'expired') {
                    $sql->whereNotNull('expiry_date')
                        ->where('expiry_date', '<', Carbon::now())->whereNull('deleted_at');
                }
            }

            // Feature Section Filtration
            // Only apply feature section filters if user hasn't provided conflicting filters
            // User filters should override feature section defaults
            if (! empty($request->featured_section_id) || ! empty($request->featured_section_slug)) {
                if (! empty($request->featured_section_id)) {
                    $featuredSection = FeatureSection::findOrFail($request->featured_section_id);
                } else {
                    $featuredSection = FeatureSection::where('slug', $request->featured_section_slug)->firstOrFail();
                }

                // Check if user has provided filters that should override feature section filters
                $hasUserPriceFilter = isset($request->min_price) || isset($request->max_price);
                $hasUserSortFilter = ! empty($request->sort_by);
                $hasUserCategoryFilter = ! empty($request->category_id) || ! empty($request->category_slug);

                // Apply feature section filters only if user hasn't provided conflicting filters
                $sql = match ($featuredSection->filter) {
                    'price_criteria' => $hasUserPriceFilter
                        ? $sql // User price filter already applied, skip feature section price filter
                        // : $sql->whereBetween('price', [$featuredSection->min_price, $featuredSection->max_price]),
                        : $sql->where(function ($query) use ($featuredSection) {
                            $query->whereBetween('price', [$featuredSection->min_price, $featuredSection->max_price])
                                ->orWhere(function ($q) use ($featuredSection) {
                                    $q->whereBetween('min_salary', [$featuredSection->min_price, $featuredSection->max_price])
                                        ->whereBetween('max_salary', [$featuredSection->min_price, $featuredSection->max_price]);
                                });
                        }),
                    'most_viewed' => $hasUserSortFilter
                        ? $sql // User sort already applied, skip feature section sort
                        : $sql->reorder()->orderBy('clicks', 'DESC'),

                    'category_criteria' => $hasUserCategoryFilter
                        ? $sql // User category filter already applied, skip feature section category filter
                        : (static function () use ($featuredSection, $sql) {
                            $category = Category::whereIn('id', explode(',', $featuredSection->value))->with('children')->get();
                            $categoryIDS = HelperService::findAllCategoryIds($category);

                            return $sql->whereIn('category_id', $categoryIDS);
                        })(),

                    'most_liked' => $hasUserSortFilter
                        ? $sql // User sort already applied, skip feature section sort
                        : $sql->reorder()->withCount('favourites'), // ->orderBy('favourites_count', 'DESC'),

                    'featured_ads' => $sql->where('status', 'approved')->has('featured_items')->getNonExpiredItems(),
                };
            }

            if (! empty($request->search)) {
                $sql->search($request->search);
            }

            function removeBackslashesRecursive($data)
            {
                $cleaned = [];
                foreach ($data as $key => $value) {
                    $cleanKey = stripslashes($key);
                    if (is_array($value)) {
                        $cleaned[$cleanKey] = removeBackslashesRecursive($value);
                    } else {
                        $cleaned[$cleanKey] = stripslashes($value);
                    }
                }

                return $cleaned;
            }
            // Optimize custom fields filtering - use whereHas instead of joins for better performance
            $cleanedParameters = removeBackslashesRecursive($request->all());
            if (! empty($cleanedParameters['custom_fields'])) {
                $customFields = $cleanedParameters['custom_fields'];
                $sql->whereHas('item_custom_field_values', function ($query) use ($customFields) {
                    $query->whereIn('custom_field_id', array_keys($customFields));
                    foreach ($customFields as $customFieldId => $value) {
                        $query->where(function ($q) use ($customFieldId, $value) {
                            if (is_array($value)) {
                                foreach ($value as $arrayValue) {
                                    $q->orWhere(function ($subQ) use ($customFieldId, $arrayValue) {
                                        $subQ->where('custom_field_id', $customFieldId)
                                            ->where('value', 'LIKE', '%' . trim($arrayValue) . '%');
                                    });
                                }
                            } else {
                                $q->orWhere(function ($subQ) use ($customFieldId, $value) {
                                    $subQ->where('custom_field_id', $customFieldId)
                                        ->where('value', 'LIKE', '%' . trim($value) . '%');
                                });
                            }
                        });
                    }
                }, '>=', count($customFields));
            }

            if (Auth::check()) {
                $sql->with(['item_offers' => function ($q) {
                    $q->where('buyer_id', Auth::user()->id);
                }, 'user_reports' => function ($q) {
                    $q->where('user_id', Auth::user()->id);
                }]);

                $currentURI = explode('?', $request->getRequestUri(), 2);

                if ($currentURI[0] == '/api/my-items') { // TODO: This if condition is temporary fix. Need something better
                    $sql->where(['user_id' => Auth::user()->id])->withTrashed();
                } else {
                    $sql->where('status', 'approved')->has('user')->onlyNonBlockedUsers()->getNonExpiredItems();
                }
            } else {
                //  Other users should only get approved items
                $sql->where('status', 'approved')->getNonExpiredItems();
            }

            // Helper function to apply auth filters
            $applyAuthFilters = function ($query) use ($request) {
                if (Auth::check()) {
                    $query->with(['item_offers' => function ($q) {
                        $q->where('buyer_id', Auth::user()->id);
                    }, 'user_reports' => function ($q) {
                        $q->where('user_id', Auth::user()->id);
                    }]);

                    $currentURI = explode('?', $request->getRequestUri(), 2);
                    if ($currentURI[0] == '/api/my-items') {
                        $query->where(['user_id' => Auth::user()->id])->withTrashed();
                    } else {
                        $query->where('status', 'approved')->has('user')->onlyNonBlockedUsers()->getNonExpiredItems();
                    }
                } else {
                    $query->where('status', 'approved')->getNonExpiredItems();
                }

                return $query;
            };

            // Apply location filters using shared function
            $locationResult = HelperService::applyLocationFilters($sql, $request, $applyAuthFilters);
            $sql = $locationResult['query'];
            $locationMessage = $locationResult['message'];

            $baseQuery = clone $sql;

            // FEATURED QUERY
            $featuredQuery = clone $baseQuery;
            $featuredQuery
                ->whereHas('featured_items', function ($q) {
                    $q->whereDate('start_date', '<=', now())
                        ->where(function ($q) {
                            $q->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', now());
                        });
                })
                ->join('featured_items as fi', 'fi.item_id', '=', 'items.id')
                ->orderBy('fi.created_at', 'DESC')
                // IMPORTANT: do not override select() here because location filters may have added
                // `distance` via selectRaw(... AS distance). Overriding select would remove it and
                // break ORDER BY distance with "Unknown column 'distance'".
                ->addSelect('items.*');

            // NORMAL QUERY
            $normalQuery = clone $baseQuery;
            $normalQuery
                ->whereDoesntHave('featured_items', function ($q) {
                    $q->whereDate('start_date', '<=', now())
                        ->where(function ($q) {
                            $q->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', now());
                        });
                })
                ->orderBy('items.created_at', 'DESC');

            // Cast to integers to prevent "string - int" error
            $limit = (int) ($request->limit ?? 10);
            $page = (int) ($request->page ?? 1);

            $totalFeatured = (clone $featuredQuery)->distinct('items.id')->count('items.id');
            $totalNormal = (clone $normalQuery)->count();
            $totalItems = $totalFeatured + $totalNormal;

            // Ensure totalPages is at least 1 to avoid division by zero
            $totalPages = (int) max(1, ceil($totalItems / $limit));

            // Distribute featured items across pages
            $featuredPerPage = (int) ceil($totalFeatured / $totalPages);

            // Now subtraction will work because both are integers
            $normalPerPage = $limit - $featuredPerPage;

            if ($normalPerPage < 0) {
                $normalPerPage = 0;
            }

            $featuredOffset = ($page - 1) * $featuredPerPage;
            $normalOffset = ($page - 1) * $normalPerPage;

            if ($normalPerPage < 0) {
                $normalPerPage = 0;
            }

            $featuredItems = $featuredQuery
                ->skip($featuredOffset)
                ->take($featuredPerPage)
                ->get();

            $normalItems = $normalQuery
                ->skip($normalOffset)
                ->take($normalPerPage)
                ->get();

            $items = $featuredItems->merge($normalItems);

            $paginator = new LengthAwarePaginator(
                $items,
                $totalItems,
                $limit,
                $page,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );

            // Execute query and get results
            if (! empty($request->id)) {
                /*
                 * Collection does not support first OR find method's result as of now. It's a part of R&D
                 * So currently using this shortcut method get() to fetch the first data
                 */
                $result = $sql->get();
                // dd($result);
                if (count($result) == 0) {
                    ResponseService::errorResponse(__('No item Found'));
                }
            } else {
                if (! empty($request->limit)) {
                    $result = $sql->paginate($request->limit);
                } else {
                    $result = $sql->paginate();
                }
            }

            // Prepare response with location message if applicable
            $responseData = new ItemCollection($paginator);
            // Use location message if available, otherwise use default success message
            $responseMessage = ! empty($locationMessage) ? $locationMessage : __('Advertisement Fetched Successfully');

            // return response()->json($responseData);

            ResponseService::successResponse($responseMessage, $responseData);
            // if (!empty($request->id)) {
            //     /*
            //      * Collection does not support first OR find method's result as of now. It's a part of R&D
            //      * So currently using this shortcut method get() to fetch the first data
            //      */
            //     $result = $sql->get();
            //     if (count($result) == 0) {
            //         ResponseService::errorResponse(__('No item Found'));
            //     }
            // } else {
            //     if (!empty($request->limit)) {
            //         $result = $sql->paginate($request->limit);
            //     } else {
            //         $result = $sql->paginate();
            //     }

            // }
            //                // Add three regular items
            //                for ($i = 0; $i < 3 && $regularIndex < $regularItemCount; $i++) {
            //                    $items->push($regularItems[$regularIndex]);
            //                    $regularIndex++;
            //                }
            //
            //                // Add one featured item if available
            //                if ($featuredIndex < $featuredItemCount) {
            //                    $items->push($featuredItems[$featuredIndex]);
            //                    $featuredIndex++;
            //                }
            //            }
            // Return success response with the fetched items

            // ResponseService::successResponse(__('Advertisement Fetched Successfully'), new ItemCollection($result));

        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getItem');
            ResponseService::errorResponse();
        }
    }

    public function updateItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'name' => 'nullable',
            'slug' => [
                'nullable',
                'regex:/^(?!-)(?!.*--)(?!.*-$)(?!-$)[a-z0-9-]+$/',
            ],
            'price' => 'nullable',
            'description' => 'nullable',
            'latitude' => 'nullable',
            'longitude' => 'nullable',
            'address' => 'nullable',
            'contact' => 'nullable',
            'image' => 'nullable|mimes:jpeg,jpg,png|max:7168',
            'custom_fields' => 'nullable',
            'custom_field_files' => 'nullable|array',
            'custom_field_files.*' => 'nullable|mimes:jpeg,png,jpg,pdf,doc|max:7168',
            'gallery_images' => 'nullable|array',
            'currency_id' => 'nullable|exists:currencies,id',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        DB::beginTransaction();

        try {

            $item = Item::owner()->findOrFail($request->id);
            $auto_approve_item = Setting::where('name', 'auto_approve_edited_item')->value('value') ?? 0;
            if ($auto_approve_item == 1) {
                $status = 'approved';
            } else {
                $status = 'review';
            }
            $slugInput = $request->input('slug') ?? '';
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($slugInput)));
            $slug = trim($slug, '-');

            // If slug is empty after cleaning, use existing item slug
            if (empty($slug)) {
                $slug = $item->slug;
            }

            // Generate unique slug
            $uniqueSlug = HelperService::generateUniqueSlug(new Item, $slug, $request->id);

            $data = $request->all();
            $data['slug'] = $uniqueSlug;
            $data['status'] = $status;
            if ($request->hasFile('image')) {
                $data['image'] = FileService::compressAndReplace($request->file('image'), $this->uploadFolder, $item->getRawOriginal('image'), true);
            }

            $item->update($data);
            // Update or create item translations
            $translations = json_decode($request->input('translations', '{}'), true, 512, JSON_THROW_ON_ERROR);
            if (! empty($translations)) {
                foreach ($translations as $languageId => $translationData) {
                    if (Language::where('id', $languageId)->exists()) {
                        $item->translations()->updateOrCreate(
                            ['language_id' => $languageId],
                            [
                                'name' => $translationData['name'],
                                'description' => $translationData['description'] ?? '',
                                'address' => $translationData['address'] ?? '',
                                'rejected_reason' => $translationData['rejected_reason'] ?? null,
                                'admin_edit_reason' => $translationData['admin_edit_reason'] ?? null,
                            ]
                        );
                    }
                }
            }

            // Update Custom Field values for item
            if ($request->custom_fields) {
                $itemCustomFieldValues = [];
                foreach (json_decode($request->custom_fields, true, 512, JSON_THROW_ON_ERROR) as $key => $custom_field) {
                    $itemCustomFieldValues[] = [
                        'item_id' => $item->id,
                        'custom_field_id' => $key,
                        'value' => json_encode($custom_field, JSON_THROW_ON_ERROR),
                        'updated_at' => time(),
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::upsert($itemCustomFieldValues, ['item_id', 'custom_field_id'], ['value', 'updated_at']);
                }
            }

            // Add new gallery images
            if ($request->hasFile('gallery_images')) {
                $galleryImages = [];
                foreach ($request->file('gallery_images') as $file) {
                    $galleryImages[] = [
                        'image' => FileService::compressAndUpload($file, $this->uploadFolder, true),
                        'item_id' => $item->id,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                }
                if (count($galleryImages) > 0) {
                    ItemImages::insert($galleryImages);
                }
            }

            if ($request->custom_field_files) {
                $itemCustomFieldValues = [];
                foreach ($request->custom_field_files as $key => $file) {
                    $value = ItemCustomFieldValue::where(['item_id' => $item->id, 'custom_field_id' => $key])->first();
                    if (! empty($value)) {
                        $file = FileService::replace($file, 'custom_fields_files', $value->getRawOriginal('value'));
                    } else {
                        $file = '';
                    }

                    $itemCustomFieldValues[] = [
                        'item_id' => $item->id,
                        'language_id' => 1,
                        'custom_field_id' => $key,
                        'value' => $file,
                        'updated_at' => time(),
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::updateOrCreate(
                        ['item_id' => $item->id, 'custom_field_id' => $key],
                        ['value' => $file, 'language_id' => 1, 'updated_at' => time()]
                    );
                }
            }
            // Update or insert custom field translations
            if ($request->has('custom_field_translations')) {
                $customFieldTranslations = $request->input('custom_field_translations');

                if (! is_array($customFieldTranslations)) {
                    $customFieldTranslations = html_entity_decode($customFieldTranslations);
                    $customFieldTranslations = json_decode($customFieldTranslations, true, 512, JSON_THROW_ON_ERROR);
                }
                $translatedEntries = [];

                foreach ($customFieldTranslations as $languageId => $fieldsByCustomField) {
                    foreach ($fieldsByCustomField as $customFieldId => $translatedValue) {
                        $translatedEntries[] = [
                            'item_id' => $item->id,
                            'custom_field_id' => $customFieldId,
                            'language_id' => $languageId,
                            'value' => json_encode($translatedValue, JSON_THROW_ON_ERROR),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ];
                    }
                }

                if (! empty($translatedEntries)) {
                    // Ensure combination is unique
                    ItemCustomFieldValue::upsert(
                        $translatedEntries,
                        ['item_id', 'custom_field_id', 'language_id'], // unique keys
                        ['value', 'updated_at']
                    );
                }
            }

            // Delete gallery images
            if (! empty($request->delete_item_image_id)) {
                $item_ids = explode(',', $request->delete_item_image_id);
                foreach (ItemImages::whereIn('id', $item_ids)->get() as $itemImage) {
                    FileService::delete($itemImage->getRawOriginal('image'));
                    $itemImage->delete();
                }
            }

            $result = Item::with('user:id,name,email,mobile,profile,country_code', 'category:id,name,image,is_job_category,price_optional', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field.translations', 'area', 'translations')->where('items.id', $item->id)->get();
            /*
               * Collection does not support first OR find method's result as of now. It's a part of R&D
               * So currently using this shortcut method
              */
            $result = new ItemCollection($result);

            DB::commit();
            ResponseService::successResponse(__('Advertisement Fetched Successfully'), $result);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, 'API Controller -> updateItem');
            ResponseService::errorResponse();
        }
    }

    public function deleteItem(Request $request)
    {
        try {
            // Validation rules
            $rules = [
                'item_id' => 'nullable|exists:items,id',
                'item_ids' => 'nullable|string', // comma-separated IDs
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return ResponseService::validationError($validator->errors()->first());
            }

            // Normalize IDs
            $itemIds = [];

            if ($request->filled('item_id')) {
                $itemIds[] = $request->item_id;
            }

            if ($request->filled('item_ids')) {
                $ids = explode(',', $request->item_ids);
                $ids = array_map('trim', $ids);
                $ids = array_filter($ids, 'strlen');
                $itemIds = array_merge($itemIds, $ids);
            }

            if (empty($itemIds)) {
                return ResponseService::validationError(__('Please provide item_id or item_ids'));
            }

            $results = [];

            foreach ($itemIds as $id) {
                try {
                    $item = Item::owner()->with('gallery_images')->withTrashed()->findOrFail($id);

                    // Delete main image
                    FileService::delete($item->getRawOriginal('image'));

                    // Delete gallery images
                    if ($item->gallery_images->count() > 0) {
                        foreach ($item->gallery_images as $gallery) {
                            FileService::delete($gallery->getRawOriginal('image'));
                        }
                    }

                    // Delete item
                    $item->forceDelete();

                    $results[] = [
                        'status' => 'success',
                        'message' => __('Advertisement Deleted Successfully'),
                        'item_id' => $id,
                    ];
                } catch (Throwable $e) {
                    $results[] = [
                        'status' => 'failed',
                        'message' => __('Failed to delete item'),
                        'item_id' => $id,
                    ];
                }
            }

            // Single item response
            if (count($results) === 1) {
                if ($results[0]['status'] === 'success') {
                    return ResponseService::successResponse(
                        __('Advertisement Deleted Successfully'),
                        ['id' => $results[0]['item_id']]
                    );
                } else {
                    return ResponseService::errorResponse($results[0]['message']);
                }
            }

            // Multiple items response
            return ResponseService::successResponse(__('Items processed successfully'), $results);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> deleteItem');

            return ResponseService::errorResponse();
        }
    }

    public function updateItemStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer',
            'status' => 'required|in:sold out,inactive,active,resubmitted',
            // 'sold_to' => 'required_if:status,==,sold out|integer'
            'sold_to' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $item = Item::owner()->whereNotIn('status', ['review', 'permanent rejected'])->withTrashed()->findOrFail($request->item_id);
            if ($item->status == 'permanent rejected' && $request->status == 'resubmitted') {
                ResponseService::errorResponse(__('This Advertisement is permanently rejected and cannot be resubmitted'));
            }
            if ($request->status == 'inactive') {
                $item->delete();
            } elseif ($request->status == 'active') {
                $item->restore();
                $item->update(['status' => 'review']);
            } elseif ($request->status == 'sold out') {
                $item->update([
                    'status' => 'sold out',
                    'sold_to' => $request->sold_to,
                ]);
            } else {
                $item->update(['status' => $request->status]);
            }
            ResponseService::successResponse(__('Advertisement Status Updated Successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'ItemController -> updateItemStatus');
            ResponseService::errorResponse(__('Something Went Wrong'));
        }
    }

    public function getItemBuyerList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $buyer_ids = ItemOffer::where('item_id', $request->item_id)->select('buyer_id')->pluck('buyer_id');
            $users = User::select(['id', 'name', 'profile'])->whereIn('id', $buyer_ids)->get();
            ResponseService::successResponse(__('Buyer List fetched Successfully'), $users);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'ItemController -> updateItemStatus');
            ResponseService::errorResponse(__('Something Went Wrong'));
        }
    }

    public function getSubCategories(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|integer',
            'listing' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $subcategoryLimit = 15; // Limit subcategories per level for display

            // Get package category IDs for validation when category_id is passed with listing=1
            $packageCategoryIds = [];
            $hasGlobalPackage = false;
            $freeAdListing = Setting::where('name', 'free_ad_listing')->value('value') ?? 0;

            // Validate access when category_id is passed and listing=1
            if (! empty($request->category_id) && ! empty($request->listing) && $request->listing == 1 && $freeAdListing != 1 && Auth::check()) {
                // Get ALL active packages for the user (user can have multiple packages)
                $userPackages = UserPurchasedPackage::onlyActive()
                    ->whereHas('package', static function ($q) {
                        $q->where('type', 'item_listing');
                    })
                    ->where('user_id', Auth::id())
                    ->with('package.package_categories')
                    ->get();

                // Check if user has any global package
                foreach ($userPackages as $userPackage) {
                    if ($userPackage->package && $userPackage->package->is_global == 1) {
                        $hasGlobalPackage = true;
                        break;
                    }
                }

                // If no global package, collect category IDs from non-global packages
                if (! $hasGlobalPackage) {
                    foreach ($userPackages as $userPackage) {
                        if ($userPackage->package && $userPackage->package->is_global != 1 && $userPackage->package->package_categories) {
                            $packageCatIds = $userPackage->package->package_categories->pluck('category_id')->toArray();
                            $packageCategoryIds = array_merge($packageCategoryIds, $packageCatIds);
                        }
                    }
                    $packageCategoryIds = array_unique($packageCategoryIds);
                }
                // dd($packageCategoryIds);
                // Get the requested category
                $requestedCategory = Category::find($request->category_id);
                if ($requestedCategory) {
                    // Check if user has access to the category or any of its subcategories
                    if (! $hasGlobalPackage) {
                        $hasAccess = false;

                        // First, check if the category itself is in the purchased packages
                        if (in_array($requestedCategory->id, $packageCategoryIds)) {
                            $hasAccess = true;
                        } else {
                            // If category is not in package, check recursively if any subcategory is in package
                            $descendantIds = $requestedCategory->descendants()
                                ->where('status', 1)
                                ->pluck('id')
                                ->toArray();

                            // Check if any descendant (subcategory at any level) is in the package
                            if (! empty($descendantIds)) {
                                $matchingDescendants = array_intersect($descendantIds, $packageCategoryIds);
                                if (! empty($matchingDescendants)) {
                                    $hasAccess = true;
                                }
                            }
                        }

                        // If no access found, return error
                        if (! $hasAccess) {
                            ResponseService::errorResponse(__('You need to purchase a package for this category to access it.'));
                        }
                    }
                    // If hasGlobalPackage is true, user has access to all categories
                } else {
                    ResponseService::errorResponse(__('Category not found.'));
                }
            }

            // Recursive function to load subcategories recursively
            $loadSubcategoriesRecursively = function ($query, $depth = 0, $maxDepth = 5) use (&$loadSubcategoriesRecursively) {
                if ($depth >= $maxDepth) {
                    return;
                }

                $query->where('status', 1)
                    ->orderBy('sequence', 'ASC')
                    ->with('translations')
                    ->withCount(['approved_items', 'subcategories' => function ($q) {
                        $q->where('status', 1);
                    }]);

                // Recursively load nested subcategories
                $query->with(['subcategories' => function ($subQuery) use (&$loadSubcategoriesRecursively, $depth, $maxDepth) {
                    $loadSubcategoriesRecursively($subQuery, $depth + 1, $maxDepth);
                }]);
            };

            // Helper to limit subcategories after loading (for performance - limits displayed subcategories)
            // Note: Item counts are calculated from ALL descendants, not just loaded ones
            $limitSubcategoriesAfterLoad = function ($categories, $limit, $depth = 0, $maxDepth = 5) use (&$limitSubcategoriesAfterLoad) {
                if ($depth >= $maxDepth || empty($categories)) {
                    return;
                }

                foreach ($categories as $category) {
                    if ($category->relationLoaded('subcategories') && $category->subcategories->isNotEmpty()) {
                        // Limit to first N subcategories for display
                        $limitedSubcategories = $category->subcategories->take($limit)->values();
                        $category->setRelation('subcategories', $limitedSubcategories);

                        // Recursively limit nested subcategories
                        $limitSubcategoriesAfterLoad($category->subcategories, $limit, $depth + 1, $maxDepth);
                    }
                }
            };

            $sql = Category::withCount(['subcategories' => function ($q) {
                $q->where('status', 1);
            }])->with('translations')->where(['status' => 1])->orderBy('sequence', 'ASC')
                ->with(['subcategories' => function ($query) use (&$loadSubcategoriesRecursively) {
                    $loadSubcategoriesRecursively($query, 0);
                }]);

            $parentCategory = null;

            // Return all categories regardless of listing parameter
            if (! empty($request->category_id)) {
                $sql = $sql->where('parent_category_id', $request->category_id);
                $parentCategory = Category::find($request->category_id);
            } elseif (! empty($request->slug)) {
                $parentCategory = Category::where('slug', $request->slug)->firstOrFail();
                $sql = $sql->where('parent_category_id', $parentCategory->id);
            } else {
                $sql = $sql->whereNull('parent_category_id');
            }

            $sql = $sql->paginate();

            // Limit subcategories to 15 per level (for performance)
            // Note: getAllItemsCountAttribute counts from ALL descendants, so count is accurate
            $limitSubcategoriesAfterLoad($sql->items(), $subcategoryLimit);

            // Calculate all_items_count for each category (includes ALL descendants, not just loaded ones)
            $sql->map(function ($category) {
                $category->all_items_count = $category->all_items_count;

                return $category;
            });

            ResponseService::successResponse(null, $sql, ['self_category' => $parentCategory ?? null]);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getCategories');
            ResponseService::errorResponse();
        }
    }

    public function getParentCategoryTree(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_category_id' => 'nullable|integer',
            'tree' => 'nullable|boolean',
            'slug' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $sql = Category::with('translations')->when($request->child_category_id, function ($sql) use ($request) {
                $sql->where('categories.id', $request->child_category_id);
            })
                ->when($request->slug, function ($sql) use ($request) {
                    $sql->where('slug', $request->slug);
                })
                ->firstOrFail()
                ->ancestorsAndSelf()->breadthFirst()->get();
            if ($request->tree) {
                $sql = $sql->toTree();
            }
            ResponseService::successResponse(null, $sql);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getCategories');
            ResponseService::errorResponse();
        }
    }

    public function getNotificationList(Request $request)
    {
        try {
            $user = Auth::user();
            $authId = $user->id;
            $userCreatedAt = $user->created_at; // Get user's registration date
            $id = $request->id;

            // 1. Build Base Query
            $query = Notifications::with(['item.area', 'item.translations'])
                ->where(function ($q) use ($authId, $userCreatedAt) {
                    // Specific notifications for this user
                    $q->whereRaw('FIND_IN_SET(?, user_id)', [$authId])
                        // Global notifications only if they were sent AFTER the user registered
                        ->orWhere(function ($sq) use ($userCreatedAt) {
                            $sq->where('send_to', 'all')
                                ->where('created_at', '>=', $userCreatedAt);
                        });
                });

            // 2. Filter by ID if passed, otherwise Paginate
            if (! empty($id)) {
                $notifications = $query->where('id', $id)->first();
                if (! $notifications) {
                    return ResponseService::successResponse(__('Notification not found'), null);
                }
                $notificationCollection = collect([$notifications]);
            } else {
                $notifications = $query->orderBy('id', 'DESC')->paginate();
                $notificationCollection = $notifications->getCollection();
            }

            $currentLanguage = app()->getLocale();
            $currentLangId = Language::where('code', $currentLanguage)->value('id');

            // 3. Process Logic
            foreach ($notificationCollection as $notification) {
                $item = $notification->item;
                if ($item) {
                    $city = City::with(['translations', 'state', 'country'])
                        ->where('name', $item->city)
                        ->whereHas('state', fn($q) => $q->where('name', $item->state))
                        ->first();

                    $translatedArea = $item->area->translated_name ?? '';
                    $translatedCity = $city?->translated_name ?? $item->city;
                    $translatedState = $city?->state?->translated_name ?? $item->state;
                    $translatedCountry = $city?->country?->translated_name ?? $item->country;

                    $item->translated_address = (! empty($translatedArea) ? $translatedArea . ', ' : '') .
                        $translatedCity . ', ' . $translatedState . ', ' . $translatedCountry;

                    if ($currentLanguage && $item->relationLoaded('translations')) {
                        $translation = $item->translations->firstWhere('language_id', $currentLangId);
                        if ($translation) {
                            $item->name = $translation->name;
                            $item->description = $translation->description;
                            $item->address = $translation->address;
                        }
                    }

                    $item->translated_area = $translatedArea;
                    $item->translated_city = $translatedCity;
                    $item->translated_state = $translatedState;
                    $item->translated_country = $translatedCountry;
                }
            }

            return ResponseService::successResponse(__('Notification fetched successfully'), $notifications);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getNotificationList');

            return ResponseService::errorResponse();
        }
    }

    public function getLanguages(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'language_code' => 'required',
                'type' => 'nullable|in:app,web',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $language = Language::where('code', $request->language_code)->firstOrFail();

            // Determine requested file path
            $type = $request->type ?? 'app';
            $languageCode = $request->language_code;

            if ($type === 'web') {
                $json_file_path = base_path("resources/lang/{$language->web_file}");
                $default_file_path = base_path('resources/lang/en_web.json');
            } else {
                $json_file_path = base_path("resources/lang/{$language->app_file}");
                $default_file_path = base_path('resources/lang/en_app.json');
            }

            // If requested file doesn’t exist, fallback to default English file
            if (! is_file($json_file_path)) {
                if (is_file($default_file_path)) {
                    $json_file_path = $default_file_path;
                } else {
                    ResponseService::errorResponse(__('Default language file not found'));
                }
            }

            // Read file content safely
            $json_string = file_get_contents($json_file_path);

            try {
                $json_data = json_decode($json_string, false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                ResponseService::errorResponse(__('Invalid JSON format in the language file'));
            }

            $language->file_name = $json_data;

            ResponseService::successResponse(__('Data Fetched Successfully'), $language);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getLanguages');
            ResponseService::errorResponse();
        }
    }

    public function appPaymentStatus(Request $request)
    {
        try {
            $paypalInfo = $request->all();
            if (! empty($paypalInfo) && isset($_GET['st']) && strtolower($_GET['st']) == 'completed') {
                ResponseService::successResponse(__('Your Package will be activated within 10 Minutes'), $paypalInfo['txn_id']);
            } elseif (! empty($paypalInfo) && isset($_GET['st']) && strtolower($_GET['st']) == 'authorized') {
                ResponseService::successResponse(__('Your Transaction is Completed. Ads wil be credited to your account within 30 minutes.'), $paypalInfo);
            } else {
                ResponseService::errorResponse(__('Payment Cancelled / Declined'), (isset($_GET)) ? $paypalInfo : '');
            }
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> appPaymentStatus');
            ResponseService::errorResponse();
        }
    }

    public function getPaymentSettings()
    {
        try {
            $result = PaymentConfiguration::select(['currency_code', 'payment_method', 'api_key', 'status'])->where('status', 1)->get();
            $response = [];
            foreach ($result as $payment) {
                $response[$payment->payment_method] = $payment->toArray();
            }
            $settings = Setting::whereIn('name', [
                'account_holder_name',
                'bank_name',
                'account_number',
                'ifsc_swift_code',
                'bank_transfer_status',
            ])->get();

            $bankDetails = [];
            foreach ($settings as $row) {
                $key = ($row->name === 'bank_transfer_status') ? 'status' : $row->name;
                $bankDetails[$key] = $row->value;
            }
            $response['bankTransfer'] = $bankDetails;
            ResponseService::successResponse(__('Data Fetched Successfully'), $response);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getPaymentSettings');
            ResponseService::errorResponse();
        }
    }

    public function getCustomFields(Request $request)
    {
        try {

            $filter = filter_var($request->input('filter', false), FILTER_VALIDATE_BOOLEAN);
            $categoryIds = explode(',', $request->input('category_ids'));

            // Load custom fields
            $customFields = CustomField::with('translations')
                ->whereHas('custom_field_category', function ($q) use ($categoryIds) {
                    $q->whereIn('category_id', $categoryIds);
                })
                ->where('status', 1)
                ->get();

            // Apply filtering logic
            if ($filter === true) {

                // Modify the collection with filtering
                $customFields = $customFields->filter(function ($field) use ($categoryIds) {

                    // Only filter for dropdown/checkbox/radio
                    if (! in_array($field->type, ['dropdown', 'checkbox', 'radio'])) {
                        return true; // keep text, number etc.
                    }

                    // Get used values for this field (pluck only value column)
                    $values = ItemCustomFieldValue::where('custom_field_id', $field->id)
                        ->whereHas('item', function ($q) use ($categoryIds) {
                            $q->getNonExpiredItems()
                                ->whereNull('deleted_at')
                                ->where('status', 'approved')
                                ->whereIn('category_id', $categoryIds);
                        })
                        ->pluck('value')
                        ->toArray();

                    $used = [];

                    // Decode values properly
                    foreach ($values as $raw) {
                        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

                        if (is_array($decoded)) {
                            $used = array_merge($used, $decoded);
                        } else {
                            $used[] = $decoded;
                        }
                    }

                    $used = array_unique(array_filter($used));

                    // ❌ Remove the entire field if no used values exist
                    if (empty($used)) {
                        return false;
                    }

                    // Get original main language values before filtering
                    $originalMainValues = $field->values ?? [];

                    // Filter original field values
                    $field->values = array_values(array_intersect($originalMainValues, $used));

                    // Find indices of used values in original main language array
                    $usedIndices = [];
                    foreach ($field->values as $usedValue) {
                        $index = array_search($usedValue, $originalMainValues);
                        if ($index !== false) {
                            $usedIndices[] = $index;
                        }
                    }

                    // Filter translations by same indices to maintain alignment
                    foreach ($field->translations as $t) {
                        $translationValues = $t->value ?? [];
                        if (is_array($translationValues) && count($translationValues) > 0) {
                            $filteredTranslationValues = [];
                            foreach ($usedIndices as $idx) {
                                if (isset($translationValues[$idx])) {
                                    $filteredTranslationValues[] = $translationValues[$idx];
                                }
                            }
                            $t->value = array_values($filteredTranslationValues);
                        }
                    }

                    return true; // KEEP field
                })->values(); // re-index collection
            }

            // Load translated attributes
            $customFields->each(function ($field) {
                $field->translated_name = $field->translated_name;
                $field->translated_value = $field->translated_value;
            });

            ResponseService::successResponse(__('Data Fetched successfully'), $customFields);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getCustomFields');
            ResponseService::errorResponse();
        }
    }

    public function makeFeaturedItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::commit();
            $user = Auth::user();
            Item::where('status', 'approved')->findOrFail($request->item_id);
            $user_package = UserPurchasedPackage::onlyActive()
                ->where(['user_id' => $user->id])
                ->with('package')
                ->whereHas('package', function ($q) {
                    $q->where(['type' => 'advertisement']);
                })
                ->first();

            if (! $user_package) {
                return ResponseService::errorResponse(__('You need to purchase a Featured Ad plan first.'));
            }
            $featuredItems = FeaturedItems::where(['item_id' => $request->item_id, 'package_id' => $user_package->package_id])->first();
            if (! empty($featuredItems)) {
                ResponseService::errorResponse(__('Advertisement is already featured'));
            }

            $user_package->used_limit++;
            $user_package->save();

            FeaturedItems::create([
                'item_id' => $request->item_id,
                'package_id' => $user_package->package_id,
                'user_purchased_package_id' => $user_package->id,
                'start_date' => date('Y-m-d'),
                'end_date' => $user_package->end_date,
            ]);

            DB::commit();
            ResponseService::successResponse(__('Featured Advertisement Created Successfully'));
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, 'API Controller -> createAdvertisement');
            ResponseService::errorResponse();
        }
    }

    public function manageFavourite(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'item_id' => 'required',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $favouriteItem = Favourite::where('user_id', Auth::user()->id)->where('item_id', $request->item_id)->first();
            if (empty($favouriteItem)) {
                $favouriteItem = new Favourite;
                $favouriteItem->user_id = Auth::user()->id;
                $favouriteItem->item_id = $request->item_id;
                $favouriteItem->save();
                ResponseService::successResponse(__('Advertisement added to Favourite'));
            } else {
                $favouriteItem->delete();
                ResponseService::successResponse(__('Advertisement remove from Favourite'));
            }
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> manageFavourite');
            ResponseService::errorResponse();
        }
    }

    public function getFavouriteItem(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'nullable|integer',
                'limit' => 'nullable|integer',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $favouriteItemIDS = Favourite::where('user_id', Auth::user()->id)->select('item_id')->pluck('item_id');
            $items = Item::whereIn('id', $favouriteItemIDS)
                ->with('user:id,name,email,mobile,profile,country_code', 'category:id,name,image,is_job_category', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field')->where('status', 'approved')->onlyNonBlockedUsers()->getNonExpiredItems()->paginate();

            ResponseService::successResponse(__('Data Fetched Successfully'), new ItemCollection($items));
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getFavouriteItem');
            ResponseService::errorResponse();
        }
    }

    public function getSlider(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country' => 'nullable|string',
            'state' => 'nullable|string',
            'city' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {

            // Fetch IDs (single responsibility)
            $countryId = $request->country
                ? Country::where('name', $request->country)->value('id')
                : null;

            $stateId = $request->state
                ? State::where('name', $request->state)->value('id')
                : null;

            $cityId = $request->city
                ? City::where('name', $request->city)->value('id')
                : null;

            $rows = Slider::with([
                'model' => function (MorphTo $morphTo) {
                    $morphTo->constrain([
                        Category::class => fn($q) => $q->withCount('subcategories'),
                    ]);
                },
            ])->where(function ($query) {
                $query->whereNull('model_type')
                    ->orWhereHasMorph(
                        'model',
                        [Category::class, Item::class],
                        fn($q) => $q->whereNotNull('id')
                    );
            })
                ->where(function ($q) use ($countryId, $stateId, $cityId) {
                    if ($countryId || $stateId || $cityId) {
                        $q->where(function ($sub) use ($countryId, $stateId, $cityId) {
                            if ($countryId) {
                                $sub->orWhere('country_id', $countryId);
                            }
                            if ($stateId) {
                                $sub->orWhere('state_id', $stateId);
                            }
                            if ($cityId) {
                                $sub->orWhere('city_id', $cityId);
                            }
                        })
                            ->orWhere(function ($global) {
                                $global->whereNull('country_id')
                                    ->whereNull('state_id')
                                    ->whereNull('city_id');
                            });
                    } else {
                        $q->whereNull('country_id')
                            ->whereNull('state_id')
                            ->whereNull('city_id');
                    }
                })->get();

            ResponseService::successResponse(null, $rows);
        } catch (\Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getSlider');
            ResponseService::errorResponse();
        }
    }

    public function getReportReasons(Request $request)
    {
        try {
            $report_reason = new ReportReason;
            if (! empty($request->id)) {
                $id = $request->id;
                $report_reason->where('id', '=', $id);
            }
            $result = $report_reason->paginate();
            $total = $report_reason->count();
            ResponseService::successResponse(__('Data Fetched Successfully'), $result, ['total' => $total]);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getReportReasons');
            ResponseService::errorResponse();
        }
    }

    public function addReports(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'item_id' => 'required',
                'report_reason_id' => 'required_without:other_message',
                'other_message' => 'required_without:report_reason_id',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $user = Auth::user();
            $report_count = UserReports::where('item_id', $request->item_id)->where('user_id', $user->id)->first();
            if ($report_count) {
                ResponseService::errorResponse(__('Already Reported'));
            }
            UserReports::create([
                ...$request->all(),
                'user_id' => $user->id,
                'other_message' => $request->other_message ?? '',
            ]);
            ResponseService::successResponse(__('Report Submitted Successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> addReports');
            ResponseService::errorResponse();
        }
    }

    public function setItemTotalClick(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'item_id' => 'required',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            Item::findOrFail($request->item_id)->increment('clicks');
            ResponseService::successResponse(null, 'Update Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> setItemTotalClick');
            ResponseService::errorResponse();
        }
    }

    public function getFeaturedSection(Request $request)
    {
        try {
            $featureSection = FeatureSection::with('translations')->orderBy('sequence', 'ASC');

            if (isset($request->slug)) {
                $featureSection->where('slug', $request->slug);
            }
            $featureSection = $featureSection->get();
            $tempRow = [];
            $rows = [];

            // Helper function to build base query
            // $buildBaseQuery = function () {
            //     return Item::where('status', 'approved')
            //         ->with('user:id,name,email,mobile,profile,is_verified,show_personal_details,country_code',
            //             'category:id,name,image,is_job_category,price_optional',
            //             'gallery_images:id,image,item_id',
            //             'featured_items',
            //             'favourites',
            //             'item_custom_field_values.custom_field.translations',
            //             'job_applications',
            //             'translations',
            //             'countryRelation:id,name',
            //             'countryRelation.translations:id,country_id,language_id,name',
            //             'countryRelation.currency:id,country_id,iso_code,symbol,symbol_position'
            //         )
            //         ->has('user')
            //         ->getNonExpiredItems();
            // };

            $buildBaseQuery = function () {
                return Item::query()
                    ->where('status', 'approved')
                    ->has('user')
                    ->with([
                        'user:id,name,mobile,profile,is_verified,show_personal_details,country_code',
                        'category:id,name,image,is_job_category,price_optional',
                        'gallery_images:id,image,item_id',
                        'featured_items',
                        'favourites',
                        'item_custom_field_values.custom_field.translations',
                        'job_applications',
                        'translations',
                        'countryRelation:id,name',
                        'countryRelation.translations:id,country_id,language_id,name',
                        'countryRelation.currency:id,country_id,iso_code,symbol,symbol_position',
                    ])
                    ->getNonExpiredItems();
            };

            // Helper function to apply auth filters
            $applyAuthFilters = function ($query) {
                if (Auth::check()) {
                    $query->with(['item_offers' => function ($q) {
                        $q->where('buyer_id', Auth::user()->id);
                    }, 'user_reports' => function ($q) {
                        $q->where('user_id', Auth::user()->id);
                    }]);
                }

                return $query;
            };

            $locationMessage = null;
            $baseItems = $buildBaseQuery();
            $locationResult = HelperService::applyLocationFilters($baseItems, $request, $applyAuthFilters);

            $filteredBaseQuery = clone $locationResult['query'];
            $sectionLocationMessage = $locationResult['message'];

            foreach ($featureSection as $row) {
                // Build base query with all eager loading
                // $baseItems = $buildBaseQuery();

                // Apply location filters using shared function
                // $locationResult = HelperService::applyLocationFilters($baseItems, $request, $applyAuthFilters);
                // $baseItems = $locationResult['query'];
                // $sectionLocationMessage = $locationResult['message'];
                $baseItems = clone $filteredBaseQuery;

                // Apply filter criteria
                $items = match ($row->filter) {
                    // 'price_criteria' => $baseItems->whereBetween('price', [$row->min_price, $row->max_price]),
                    'price_criteria' => $baseItems->where(function ($query) use ($row) {
                        $query->whereBetween('price', [$row->min_price, $row->max_price])
                            ->orWhere(function ($q) use ($row) {
                                $q->whereBetween('min_salary', [$row->min_price, $row->max_price])
                                    ->whereBetween('max_salary', [$row->min_price, $row->max_price]);
                            });
                    }),
                    'most_viewed' => $baseItems->orderBy('clicks', 'DESC'),
                    'category_criteria' => (static function () use ($row, $baseItems) {
                        $category = Category::whereIn('id', explode(',', $row->value))->with('children')->get();
                        $categoryIDS = HelperService::findAllCategoryIds($category);

                        return $baseItems->whereIn('category_id', $categoryIDS)->orderBy('id', 'DESC');
                    })(),
                    'most_liked' => $baseItems->withCount('favourites')->orderBy('favourites_count', 'DESC'),
                    'featured_ads' => $baseItems->has('featured_items')->orderBy('id', 'DESC'),
                };

                // Add auth-specific relationships
                if (Auth::check()) {
                    $items->with(['item_offers' => function ($q) {
                        $q->where('buyer_id', Auth::user()->id);
                    }, 'user_reports' => function ($q) {
                        $q->where('user_id', Auth::user()->id);
                    }]);
                }

                // Limit results early and get items
                $items = $items->limit(5)->get();

                // $items = $items->with('user:id,name,email,mobile,profile,is_verified,show_personal_details,country_code',
                //     'category:id,name,image,is_job_category,price_optional',
                //     'gallery_images:id,image,item_id',
                //     'featured_items',
                //     'favourites',
                //     'item_custom_field_values.custom_field.translations',
                //     'job_applications',
                //     'translations',
                //     'countryRelation:id,name',
                //     'countryRelation.translations:id,country_id,language_id,name',
                //     'countryRelation.currency:id,country_id,iso_code,symbol,symbol_position')->limit(5)->get();

                $tempRow[$row->id] = $row;
                $tempRow[$row->id]['total_data'] = count($items);
                if (count($items) > 0) {
                    $tempRow[$row->id]['section_data'] = new ItemCollection($items);
                } else {
                    $tempRow[$row->id]['section_data'] = [];
                }

                // Track location message for response (use first non-empty one)
                if (! empty($sectionLocationMessage) && empty($locationMessage)) {
                    $locationMessage = $sectionLocationMessage;
                }

                $rows[] = $tempRow[$row->id];
            }

            // Use location message if available, otherwise use default success message
            // dd([
            //     'queries' => \DB::getQueryLog(),
            //     'memory' => memory_get_peak_usage(true) / 1024 / 1024 .' MB',
            // ]);
            $responseMessage = ! empty($locationMessage) ? $locationMessage : __('Data Fetched Successfully');
            ResponseService::successResponse($responseMessage, $rows);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getFeaturedSection');
            ResponseService::errorResponse();
        }
    }

    public function getPaymentIntent(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'package_id' => 'required',
            'payment_method' => 'required|in:Stripe,Razorpay,Paystack,PhonePe,FlutterWave,bankTransfer,PayPal',
            'platform_type' => 'required_if:payment_method,==,Paystack|string',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {

            DB::beginTransaction();

            if ($request->payment_method !== 'bankTransfer') {
                $paymentConfigurations = PaymentConfiguration::where(['status' => 1, 'payment_method' => $request->payment_method])->first();
                if (empty($paymentConfigurations)) {
                    ResponseService::errorResponse(__('Payment is not Enabled'));
                }
            } else {
                $bankTransferEnabled = Setting::where('name', 'bank_transfer_status')->value('value');
                if ($bankTransferEnabled != 1) {
                    ResponseService::errorResponse(__('Bank Transfer is not enabled.'));
                }
            }

            $package = Package::whereNot('final_price', 0)->findOrFail($request->package_id);

            $purchasedPackage = UserPurchasedPackage::onlyActive()->where(['user_id' => Auth::user()->id, 'package_id' => $request->package_id])->first();
            if (! empty($purchasedPackage)) {
                ResponseService::errorResponse(__('You already have purchased this package'));
            }
            if ($request->payment_method === 'bankTransfer') {
                $existingTransaction = PaymentTransaction::where('user_id', Auth::user()->id)
                    ->where('package_id', $request->package_id)
                    ->where('payment_gateway', $request->payment_method)
                    ->whereIn('payment_status', ['pending', 'under review'])
                    ->exists();

                $methodName = $paymentMethodNames[$request->payment_method] ?? ucfirst($request->payment_method);

                if ($existingTransaction) {
                    return ResponseService::errorResponse("A $methodName transaction for this package already exists.");
                }
            }
            $orderId = ($request->payment_method === 'bankTransfer') ? uniqid() . '-' . 'p' . '-' . $package->id : null;

            // Add Payment Data to Payment Transactions Table
            $paymentTransactionData = PaymentTransaction::create([
                'user_id' => Auth::user()->id,
                'package_id' => $request->package_id,
                'amount' => $package->final_price,
                'payment_gateway' => ucfirst($request->payment_method),
                'payment_status' => 'Pending',
                'order_id' => $orderId,
            ]);

            if ($request->payment_method === 'bankTransfer') {
                DB::commit();
                ResponseService::successResponse(__('Bank transfer initiated. Please complete the transfer and update the transaction.'), [
                    'payment_transaction_id' => $paymentTransactionData->id,
                    'payment_transaction' => $paymentTransactionData,
                ]);
            }

            $paymentIntent = PaymentService::create($request->payment_method)->createAndFormatPaymentIntent(round($package->final_price, 2), [
                'payment_transaction_id' => $paymentTransactionData->id,
                'package_id' => $package->id,
                'user_id' => Auth::user()->id,
                'email' => Auth::user()->email,
                'platform_type' => $request->platform_type,
            ]);
            $paymentTransactionData->update(['order_id' => $paymentIntent['id']]);

            $paymentTransactionData = PaymentTransaction::findOrFail($paymentTransactionData->id);
            // Custom Array to Show as response
            $paymentGatewayDetails = [
                ...$paymentIntent,
                'payment_transaction_id' => $paymentTransactionData->id,
            ];

            DB::commit();
            ResponseService::successResponse('', ['payment_intent' => $paymentGatewayDetails, 'payment_transaction' => $paymentTransactionData]);
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getPaymentTransactions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latest_only' => 'nullable|boolean',
            'page' => 'nullable',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $paymentTransactions = PaymentTransaction::where('user_id', Auth::user()->id)->orderBy('id', 'DESC');
            if ($request->latest_only) {
                $paymentTransactions->where('created_at', '>', Carbon::now()->subMinutes(30)->toDateTimeString());
            }
            $paymentTransactions = $paymentTransactions->paginate();

            $formatter = app(CurrencyFormatterService::class);

            $paymentTransactions->getCollection()->transform(function ($data) use ($formatter) {
                if ($data->payment_status == 'pending') {
                    try {
                        $paymentIntent = PaymentService::create($data->payment_gateway)->retrievePaymentIntent($data->order_id);
                        // dd($paymentIntent);
                    } catch (Throwable) {
                        //                        PaymentTransaction::find($data->id)->update(['payment_status' => "failed"]);
                    }

                    if (! empty($paymentIntent) && $paymentIntent['status'] != 'pending') {
                        PaymentTransaction::find($data->id)->update(['payment_status' => $paymentIntent['status'] ?? 'failed']);
                    }
                }
                $data->formatted_amount = $formatter->formatPrice($data->amount);
                $data->payment_reciept = $data->payment_reciept;

                return $data;
            });

            ResponseService::successResponse(__('Payment Transactions Fetched'), $paymentTransactions);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function createItemOffer(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer',
            'amount' => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $item = Item::approved()->notOwner()->findOrFail($request->item_id);
            $itemOffer = ItemOffer::updateOrCreate([
                'item_id' => $request->item_id,
                'buyer_id' => Auth::user()->id,
                'seller_id' => $item->user_id,
            ], ['amount' => $request->amount]);

            $itemOffer = $itemOffer->load('seller:id,name,profile', 'buyer:id,name,profile', 'item:id,name,description,price,image');

            $fcmMsg = [
                'user_id' => $itemOffer->buyer->id,
                'user_name' => $itemOffer->buyer->name,
                'user_profile' => $itemOffer->buyer->profile,
                'user_type' => 'Buyer',
                'item_id' => $itemOffer->item->id,
                'item_name' => $itemOffer->item->name,
                'item_image' => $itemOffer->item->image,
                'item_price' => $itemOffer->item->price,
                'item_offer_id' => $itemOffer->id,
                'item_offer_amount' => $itemOffer->amount,
                // 'type'              => $notificationPayload['message_type'],
                // 'message_type_temp' => $notificationPayload['message_type']
            ];
            /* message_type is reserved keyword in FCM so removed here */
            unset($fcmMsg['message_type']);
            if ($request->has('amount') && $request->amount != 0) {
                $user_token = UserFcmToken::where('user_id', $item->user->id)->pluck('fcm_token')->toArray();
                $message = 'new offer is created by buyer';
                NotificationService::sendFcmNotification($user_token, 'New Offer', $message, 'offer', $fcmMsg);
            }

            ResponseService::successResponse(__('Advertisement Offer Created Successfully'), $itemOffer);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> createItemOffer');
            ResponseService::errorResponse();
        }
    }

    public function getChatList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:seller,buyer',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        try {
            $authId = Auth::user()->id;

            // 1. Get Blocked Users Lists
            $authUserBlockList = BlockUser::where('user_id', $authId)->pluck('blocked_user_id');
            $otherUserBlockList = BlockUser::where('blocked_user_id', $authId)->pluck('user_id');

            // 2. Prepare the Base Query
            $query = ItemOffer::with([
                'seller:id,name,profile',
                'buyer:id,name,profile',
                'item' => function ($q) {
                    $q->with(['currency:id,iso_code,symbol,symbol_position', 'category:id,name,image,is_job_category,price_optional']);
                },
                'chat' => function ($q) {
                    $q->latest('updated_at')->select('updated_at', 'item_offer_id', 'is_read', 'sender_id');
                },
                'item.review' => function ($q) use ($authId) {
                    $q->where('buyer_id', $authId);
                }
            ])
                ->whereHas('buyer', function ($query) {
                    $query->whereNull('deleted_at');
                })
                ->withCount([
                    'chat as unread_chat_count' => function ($query) use ($authId) {
                        $query->where('is_read', 0)
                            ->where('sender_id', '!=', $authId);
                    },
                ]);

            // Filter by type
            if ($request->type == 'seller') {
                $query->where('seller_id', $authId);
            } else {
                $query->where('buyer_id', $authId);
            }

            // 3. CALCULATE GLOBAL UNREAD COUNT (For all pages)
            // We clone the query to get the total sum without pagination limits
            $totalUnreadChatCount = (int) (clone $query)->get()->sum('unread_chat_count');

            // 4. APPLY STABLE ORDERING AND PAGINATE
            $itemOffer = $query->orderByDesc('unread_chat_count')
                ->orderByDesc(function ($q) {
                    $q->select('updated_at')
                        ->from('chats')
                        ->whereColumn('item_offer_id', 'item_offers.id')
                        ->orderByDesc('updated_at')
                        ->limit(1);
                })
                ->orderBy('id', 'DESC') // Tie-breaker ensures Page 1 items don't show on Page 2
                ->paginate();

            // 5. DATA FORMATTING (Single Transform)
            $formatter = app(currencyFormatterService::class);

            $itemOffer->getCollection()->transform(function ($offer) use ($request, $authId, $authUserBlockList, $otherUserBlockList, $formatter) {

                // Block logic
                if ($request->type === 'seller') {
                    $userBlocked = $authUserBlockList->contains($offer->buyer_id) || $otherUserBlockList->contains($offer->seller_id);
                } else {
                    $userBlocked = $authUserBlockList->contains($offer->seller_id) || $otherUserBlockList->contains($offer->buyer_id);
                }
                $offer->user_blocked = $userBlocked;

                // Chat timing logic
                $latestChat = $offer->chat->first();
                $offer->last_message_time = $latestChat ? $latestChat->updated_at : $offer->updated_at;

                // Item and Currency Formatting
                if ($offer->item) {
                    $item = $offer->item;
                    $item->is_purchased = ($item->sold_to == $authId) ? 1 : 0;

                    // Handle Review Relation (Convert collection to single object)
                    $item->setRelation('review', optional($item->review)->first());
                    $currency = $item?->currency ?? null;
                    $item->formatted_price = $formatter->formatPrice($item->price, $currency);
                    $item->formatted_min_salary = $formatter->formatPrice($item->min_salary, $currency);
                    $item->formatted_max_salary = $formatter->formatPrice($item->max_salary, $currency);
                    $item->formatted_salary_range = $formatter->formatSalaryRange($item->min_salary, $item->max_salary, $currency);
                }

                // Offer amount formatting
                $offerCurrency = $offer->item ? ($offer->item->currency ?? null) : null;
                $offer->formatted_amount = $formatter->formatPrice($offer->amount, $offerCurrency);

                return $offer;
            });

            return ResponseService::successResponse(
                __('Chat List Fetched Successfully'),
                $itemOffer,
                ['total_unread_chat_count' => $totalUnreadChatCount]
            );
        } catch (\Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getChatList');

            return ResponseService::errorResponse('Something went wrong');
        }
    }

    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_offer_id' => 'required|integer',
            'message' => (! $request->file('file') && ! $request->file('audio')) ? 'required' : 'nullable',
            'file' => 'nullable|mimes:jpg,jpeg,png|max:7168',
            'audio' => 'nullable|mimetypes:audio/mpeg,video/webm,audio/ogg,video/mp4,audio/x-wav,text/plain|max:7168',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $user = Auth::user();
            // List of users that Auth user has blocked
            $authUserBlockList = BlockUser::where('user_id', $user->id)->get();

            // List of Other users that have blocked the Auth user
            $otherUserBlockList = BlockUser::where('blocked_user_id', $user->id)->get();

            $itemOffer = ItemOffer::with('item')->findOrFail($request->item_offer_id);
            if ($itemOffer->seller_id == $user->id) {
                // If Auth user is seller then check if buyer has blocked the user
                $blockStatus = $authUserBlockList->filter(function ($data) use ($itemOffer) {
                    return $data->user_id == $itemOffer->seller_id && $data->blocked_user_id == $itemOffer->buyer_id;
                });
                if (count($blockStatus) !== 0) {
                    ResponseService::errorResponse(__('You Cannot send message because You have blocked this user'));
                }

                $blockStatus = $otherUserBlockList->filter(function ($data) use ($itemOffer) {
                    return $data->user_id == $itemOffer->buyer_id && $data->blocked_user_id == $itemOffer->seller_id;
                });
                if (count($blockStatus) !== 0) {
                    ResponseService::errorResponse(__('You Cannot send message because other user has blocked you.'));
                }
            } else {
                // If Auth user is seller then check if buyer has blocked the user
                $blockStatus = $authUserBlockList->filter(function ($data) use ($itemOffer) {
                    return $data->user_id == $itemOffer->buyer_id && $data->blocked_user_id == $itemOffer->seller_id;
                });
                if (count($blockStatus) !== 0) {
                    ResponseService::errorResponse(__('You Cannot send message because You have blocked this user'));
                }

                $blockStatus = $otherUserBlockList->filter(function ($data) use ($itemOffer) {
                    return $data->user_id == $itemOffer->seller_id && $data->blocked_user_id == $itemOffer->buyer_id;
                });
                if (count($blockStatus) !== 0) {
                    ResponseService::errorResponse(__('You Cannot send message because other user has blocked you.'));
                }
            }
            $chat = Chat::create([
                'sender_id' => Auth::user()->id,
                'item_offer_id' => $request->item_offer_id,
                'message' => $request->message,
                'file' => $request->hasFile('file') ? FileService::compressAndUpload($request->file('file'), 'chat') : '',
                'audio' => $request->hasFile('audio') ? FileService::compressAndUpload($request->file('audio'), 'chat') : '',
                'is_read' => 0,
            ]);

            if ($itemOffer->seller_id == $user->id) {
                $receiver_id = $itemOffer->buyer_id;
                $userType = 'Seller';
            } else {
                $receiver_id = $itemOffer->seller_id;
                $userType = 'Buyer';
            }
            $notificationPayload = $chat->toArray();

            $unreadMessagesCount = Chat::where('item_offer_id', $itemOffer->id)
                ->where('is_read', 0)
                ->count();

            $fcmMsg = [
                ...$notificationPayload,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_profile' => $user->profile,
                'user_type' => $userType,
                'item_id' => $itemOffer->item->id,
                'item_name' => $itemOffer->item->name,
                'item_image' => $itemOffer->item->image,
                'item_price' => $itemOffer->item->price,
                'item_offer_id' => $itemOffer->id,
                'item_offer_amount' => $itemOffer->amount,
                'type' => $notificationPayload['message_type'],
                'message_type_temp' => $notificationPayload['message_type'],
                'unread_count' => $unreadMessagesCount,
            ];
            /* message_type is reserved keyword in FCM so removed here */
            unset($fcmMsg['message_type']);
            $displayMessage = $request->message;
            if (empty($displayMessage)) {
                if ($request->hasFile('file')) {
                    $mime = $request->file('file')->getMimeType();

                    if (str_contains($mime, 'image')) {
                        $displayMessage = '📷 Sent you an image';
                    } elseif (str_contains($mime, 'pdf')) {
                        $displayMessage = '📄 Sent you a PDF file';
                    } elseif (str_contains($mime, 'word')) {
                        $displayMessage = '📘 Sent you a document';
                    } elseif (str_contains($mime, 'text')) {
                        $displayMessage = '📄 Sent you a text file';
                    } else {
                        $displayMessage = '📎 Sent you a file';
                    }
                } elseif ($request->hasFile('audio')) {
                    $displayMessage = '🎤 Sent you an audio message';
                } else {
                    $displayMessage = '💬 Sent you a message';
                }
            }

            $receiverFCMTokens = UserFcmToken::where('user_id', $receiver_id)->pluck('fcm_token')->toArray();
            DB::commit();

            $notification = NotificationService::sendFcmNotification($receiverFCMTokens, 'Message', $displayMessage, 'chat', $fcmMsg);

            ResponseService::successResponse(__('Message Fetched Successfully'), $chat, ['debug' => $notification]);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, 'API Controller -> sendMessage');
            ResponseService::errorResponse();
        }
    }

    public function getChatMessages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_offer_id' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $itemOffer = ItemOffer::owner()->findOrFail($request->item_offer_id);
            $chat = Chat::where('item_offer_id', $itemOffer->id)->orderBy('created_at', 'DESC')->paginate();
            $authUserId = auth::user()->id;
            Chat::where('item_offer_id', $itemOffer->id)
                ->where('sender_id', '!=', $authUserId)
                ->whereIn('id', $chat->pluck('id'))
                ->update(['is_read' => '1']);
            ResponseService::successResponse(__('Messages Fetched Successfully'), $chat);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getChatMessages');
            ResponseService::errorResponse();
        }
    }

    public function deleteUser()
    {
        try {
            User::findOrFail(Auth::user()->id)->forceDelete();
            ResponseService::successResponse(__('User Deleted Successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> deleteUser');
            ResponseService::errorResponse();
        }
    }

    public function inAppPurchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'purchase_token' => 'required',
            'payment_method' => 'required|in:google,apple',
            'package_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $package = Package::findOrFail($request->package_id);
            $purchasedPackage = UserPurchasedPackage::where(['user_id' => Auth::user()->id, 'package_id' => $request->package_id])->first();
            if (! empty($purchasedPackage)) {
                ResponseService::errorResponse(__('You already have purchased this package'));
            }

            PaymentTransaction::create([
                'user_id' => Auth::user()->id,
                'amount' => $package->final_price,
                'payment_gateway' => $request->payment_method,
                'order_id' => $request->purchase_token,
                'payment_status' => 'success',
            ]);

            UserPurchasedPackage::create([
                'user_id' => Auth::user()->id,
                'package_id' => $request->package_id,
                'start_date' => Carbon::now(),
                'total_limit' => $package->item_limit == 'unlimited' ? null : $package->item_limit,
                'end_date' => $package->duration == 'unlimited' ? null : Carbon::now()->addDays($package->duration),
            ]);
            ResponseService::successResponse(__('Package Purchased Successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> inAppPurchase');
            ResponseService::errorResponse();
        }
    }

    public function blockUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'blocked_user_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            BlockUser::create([
                'user_id' => Auth::user()->id,
                'blocked_user_id' => $request->blocked_user_id,
            ]);
            ResponseService::successResponse(__('User Blocked Successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> blockUser');
            ResponseService::errorResponse();
        }
    }

    public function unblockUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'blocked_user_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            BlockUser::where([
                'user_id' => Auth::user()->id,
                'blocked_user_id' => $request->blocked_user_id,
            ])->delete();
            ResponseService::successResponse(__('User Unblocked Successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> unblockUser');
            ResponseService::errorResponse();
        }
    }

    public function getBlockedUsers()
    {
        try {
            $blockedUsers = BlockUser::where('user_id', Auth::user()->id)->pluck('blocked_user_id');
            $users = User::whereIn('id', $blockedUsers)->select(['id', 'name', 'profile'])->get();
            ResponseService::successResponse(__('User Unblocked Successfully'), $users);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> unblockUser');
            ResponseService::errorResponse();
        }
    }

    public function getTips()
    {
        try {
            $tips = Tip::select(['id', 'description'])->orderBy('sequence', 'ASC')->with('translations')->get();
            ResponseService::successResponse(__('Tips Fetched Successfully'), $tips);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getTips');
            ResponseService::errorResponse();
        }
    }

    public function getBlog(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category_id' => 'nullable|integer|exists:categories,id',
                'blog_id' => 'nullable|integer|exists:blogs,id',
                'sort_by' => 'nullable|in:new-to-old,old-to-new,popular',
                'views' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            if ($request->views == 1) {
                if (! empty($request->id)) {
                    Blog::where('blogs.id', $request->id)->increment('views');
                } elseif (! empty($request->slug)) {
                    Blog::where('slug', $request->slug)->increment('views');
                } else {
                    return ResponseService::errorResponse(__('ID or Slug is required to increment views'));
                }
            }
            $blogs = Blog::with('translations')->when(! empty($request->id), static function ($q) use ($request) {
                $q->where('blogs.id', $request->id);
                Blog::where('blogs.id', $request->id);
            })
                ->when(! empty($request->slug), function ($q) use ($request) {
                    $q->where('slug', $request->slug);
                    Blog::where('slug', $request->slug);
                })
                ->when(! empty($request->sort_by), function ($q) use ($request) {
                    if ($request->sort_by === 'new-to-old') {
                        $q->orderByDesc('created_at');
                    } elseif ($request->sort_by === 'old-to-new') {
                        $q->orderBy('created_at');
                    } elseif ($request->sort_by === 'popular') {
                        $q->orderByDesc('views');
                    }
                })
                ->when(! empty($request->tag), function ($q) use ($request) {
                    $q->where(function ($query) use ($request) {
                        $query->where('tags', 'like', '%' . $request->tag . '%')
                            ->orWhereHas('translations', function ($translationQuery) use ($request) {
                                $translationQuery->where('tags', 'like', '%' . $request->tag . '%');
                            });
                    });
                })
                ->paginate();

            $otherBlogs = [];
            if (! empty($request->id) || ! empty($request->slug)) {
                $otherBlogs = Blog::with('translations')
                    ->when(! empty($request->id), function ($q) use ($request) {
                        $q->where('blogs.id', '!=', $request->id);
                    })
                    ->when(! empty($request->slug), function ($q) use ($request) {
                        $q->where('slug', '!=', $request->slug);
                    })
                    ->orderByDesc('id')
                    ->limit(3)
                    ->get();
            }

            ResponseService::successResponse(__('Blogs fetched successfully'), $blogs, ['other_blogs' => $otherBlogs]);
        } catch (Throwable $th) {
            // Log and handle exceptions
            ResponseService::logErrorResponse($th, 'API Controller -> getBlog');
            ResponseService::errorResponse(__('Failed to fetch blogs'));
        }
    }

    public function getCountries(Request $request)
    {
        try {
            $searchQuery = $request->search ?? '';
            $countries = Country::withCount('states')
                ->where(function ($query) use ($searchQuery) {
                    $query->where('name', 'LIKE', "%{$searchQuery}%")
                        ->orWhereHas('nameTranslations', function ($q) use ($searchQuery) {
                            $q->where('name', 'LIKE', "%{$searchQuery}%");
                        });
                })
                ->with(['nameTranslations.language:id,code'])
                ->orderBy('name', 'ASC')
                ->paginate();

            // Map translations to include `language_code`
            $countries->getCollection()->transform(function ($country) {
                if ($country->translations instanceof \Illuminate\Support\Collection) {
                    $country->translations = $country->translations->map(function ($translation) {
                        return [
                            'id' => $translation->id,
                            'country_id' => $translation->country_id,
                            'language_id' => $translation->language_id,
                            'name' => $translation->name,
                            'language_code' => optional($translation->language)->code,
                        ];
                    });
                } else {
                    // if somehow it's not a collection, fallback
                    $country->translations = [];
                }

                return $country;
            });

            ResponseService::successResponse(__('Countries Fetched Successfully'), $countries);
        } catch (Throwable $th) {
            // Log and handle any exceptions
            ResponseService::logErrorResponse($th, 'API Controller -> getCountries');
            ResponseService::errorResponse(__('Failed to fetch countries'));
        }
    }

    public function getStates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_id' => 'nullable|integer',
            'search' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $searchQuery = $request->search ?? '';
            $statesQuery = State::withCount('cities')
                ->where('name', 'LIKE', "%{$searchQuery}%")
                ->orderBy('name', 'ASC');

            if (isset($request->country_id)) {
                $statesQuery->where('country_id', $request->country_id);
            }

            $states = $statesQuery->paginate();

            ResponseService::successResponse(__('States Fetched Successfully'), $states);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller->getStates');
            ResponseService::errorResponse(__('Failed to fetch states'));
        }
    }

    public function getCities(Request $request)
    {
        try {
            // Validate
            $validator = Validator::make($request->all(), [
                'state_id' => 'nullable|integer',
                'search' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ResponseService::validationError($validator->errors()->first());
            }

            $searchQuery = $request->search ?? '';

            // Base query
            $citiesQuery = City::with('translations')
                ->withCount('areas')
                ->orderBy('cities.name', 'ASC'); // force main table for sorting

            // Search filter: main name OR translated name
            if ($searchQuery !== '') {
                $citiesQuery->where(function ($q) use ($searchQuery) {
                    $q->where('cities.name', 'LIKE', "%{$searchQuery}%")
                        ->orWhereHas('translations', function ($t) use ($searchQuery) {
                            $t->where('name', 'LIKE', "%{$searchQuery}%");
                        });
                });
            }

            // State filter
            if ($request->filled('state_id')) {
                $citiesQuery->where('cities.state_id', $request->state_id);
            }

            // Pagination
            $cities = $citiesQuery->paginate();

            return ResponseService::successResponse(__('Cities Fetched Successfully'), $cities);
        } catch (\Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller->getCities');

            return ResponseService::errorResponse(__('Failed to fetch cities'));
        }
    }

    public function getAreas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'city_id' => 'nullable|integer',
            'search' => 'nullable',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $searchQuery = $request->search ?? '';
            $data = Area::with('translations')->search($searchQuery)->orderBy('name', 'ASC');
            if (isset($request->city_id)) {
                $data->where('city_id', $request->city_id);
            }

            $data = $data->paginate();
            ResponseService::successResponse(__('Area fetched Successfully'), $data);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getAreas');
            ResponseService::errorResponse();
        }
    }

    public function getFaqs()
    {
        try {
            $faqs = Faq::with('translations')->get();
            ResponseService::successResponse(__('FAQ Data fetched Successfully'), $faqs);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getFaqs');
            ResponseService::errorResponse(__('Failed to fetch Faqs'));
        }
    }

    public function getAllBlogTags()
    {
        try {
            $languageCode = request()->header('Content-Language') ?? app()->getLocale();

            $language = Language::select(['id', 'code', 'name'])
                ->where('code', $languageCode)
                ->first();

            if (! $language) {
                return ResponseService::errorResponse('Invalid language code');
            }

            $tagsMap = [];

            Blog::with(['translations' => function ($q) use ($language) {
                $q->where('language_id', $language->id);
            }])->chunk(100, function ($blogs) use (&$tagsMap) {
                foreach ($blogs as $blog) {
                    $defaultTagsRaw = $blog->tags;
                    $defaultTags = [];
                    if (! empty($defaultTagsRaw)) {
                        if (is_string($defaultTagsRaw)) {
                            $decoded = json_decode($defaultTagsRaw, true);
                            if (json_last_error() === JSON_ERROR_NONE && ! empty($decoded)) {
                                $defaultTags = is_array($decoded) ? $decoded : [$decoded];
                            } else {
                                $defaultTags = array_map('trim', explode(',', $defaultTagsRaw));
                            }
                        } elseif (is_array($defaultTagsRaw)) {
                            $defaultTags = $defaultTagsRaw;
                        }
                    }
                    $translatedTagsRaw = $blog->translations->first()?->tags;
                    $translatedTags = [];
                    if (! empty($translatedTagsRaw)) {
                        if (is_string($translatedTagsRaw)) {
                            $decoded = json_decode($translatedTagsRaw, true);
                            if (json_last_error() === JSON_ERROR_NONE && ! empty($decoded)) {
                                $translatedTags = is_array($decoded) ? $decoded : [$decoded];
                            } else {
                                $translatedTags = array_map('trim', explode(',', $translatedTagsRaw));
                            }
                        } elseif (is_array($translatedTagsRaw)) {
                            $translatedTags = $translatedTagsRaw;
                        }
                    }
                    foreach ($defaultTags as $index => $defaultTag) {
                        $translated = $translatedTags[$index] ?? $defaultTag;
                        $tagsMap[$defaultTag] = $translated;
                    }
                }
            });
            $result = [];
            foreach ($tagsMap as $defaultTag => $translatedTag) {
                $result[] = [
                    'label' => $translatedTag,
                    'value' => $defaultTag,
                ];
            }

            ResponseService::successResponse('Blog Tags Retrieved Successfully', array_values($result));
        } catch (\Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getAllBlogTags');

            return ResponseService::errorResponse('Failed to fetch Tags');
        }
    }

    public function storeContactUs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'subject' => 'required',
            'message' => 'required',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            ContactUs::create($request->all());
            ResponseService::successResponse(__('Contact Us Stored Successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> storeContactUs');
            ResponseService::errorResponse();
        }
    }

    public function addItemReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'review' => 'nullable|string',
            'ratings' => 'required|numeric|between:0,5',
            'item_id' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $item = Item::with('user')->notOwner()->findOrFail($request->item_id);
            if ($item->sold_to !== Auth::id()) {
                ResponseService::errorResponse(__('You can only review items that you have purchased.'));
            }
            if ($item->status !== 'sold out') {
                ResponseService::errorResponse(__("The item must be marked as 'sold out' before you can review it."));
            }
            $existingReview = SellerRating::where('item_id', $request->item_id)->where('buyer_id', Auth::id())->first();
            if ($existingReview) {
                ResponseService::errorResponse(__('You have already reviewed this item.'));
            }
            $review = SellerRating::create([
                'item_id' => $request->item_id,
                'buyer_id' => Auth::user()->id,
                'seller_id' => $item->user_id,
                'ratings' => $request->ratings,
                'review' => $request->review ?? '',
            ]);
            $user_token = UserFcmToken::where('user_id', $item->user_id)->pluck('fcm_token')->toArray();
            if (! empty($user_token)) {
                NotificationService::sendFcmNotification(
                    $user_token,
                    'New Review',
                    'A new review has been added to your advertisement: ' . $item->name,
                    'item-review',
                    ['item_id' => $item->id]
                );
            }

            ResponseService::successResponse(__('Your review has been submitted successfully.'), $review);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> storeContactUs');
            ResponseService::errorResponse();
        }
    }

    public function getSeller(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        try {
            // Fetch seller by ID
            $seller = User::findOrFail($request->id);

            // Fetch seller ratings
            $ratings = SellerRating::where('seller_id', $seller->id)->with('buyer:id,name,profile')->paginate(10);
            $averageRating = $ratings->avg('ratings');

            // Response structure
            $response = [
                'seller' => [
                    ...$seller->toArray(),
                    'average_rating' => $averageRating,
                ],
                'ratings' => $ratings,
            ];

            // Send success response
            ResponseService::successResponse(__('Seller Details Fetched Successfully'), $response);
        } catch (Throwable $th) {
            // Log and handle error response
            ResponseService::logErrorResponse($th, 'API Controller -> getSeller');
            ResponseService::errorResponse();
        }
    }

    public function renewItem(Request $request)
    {
        try {
            $free_ad_listing = Setting::where('name', 'free_ad_listing')->value('value') ?? 0;

            // Validation rules
            $rules = [
                'item_id' => 'nullable|exists:items,id',
                'item_ids' => 'nullable|string', // accept comma-separated string
            ];

            if ($free_ad_listing == 0) {
                $rules['package_id'] = 'required|exists:packages,id';
            } else {
                $rules['package_id'] = 'nullable|exists:packages,id';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return ResponseService::validationError($validator->errors()->first());
            }

            // Normalize input to array
            $itemIds = [];

            if ($request->filled('item_id')) {
                $itemIds[] = $request->item_id;
            }

            if ($request->filled('item_ids')) {
                // Convert comma-separated string into array
                $ids = explode(',', $request->item_ids);
                $ids = array_map('trim', $ids);       // remove spaces
                $ids = array_filter($ids, 'strlen');  // remove empty values
                $itemIds = array_merge($itemIds, $ids);
            }

            if (empty($itemIds)) {
                return ResponseService::validationError(__('Please provide item_id or item_ids'));
            }

            $user = Auth::user();
            $package = null;
            $userPackage = null;

            // Fetch package if provided
            if ($request->filled('package_id')) {
                $package = Package::where('id', $request->package_id)->firstOrFail();

                $userPackage = UserPurchasedPackage::onlyActive()
                    ->where([
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                    ])->first();

                if (! $userPackage) {
                    return ResponseService::errorResponse(__('You have not purchased this package'));
                }
            }

            $currentDate = Carbon::now();
            $results = [];

            foreach ($itemIds as $itemId) {
                $item = Item::findOrFail($itemId);
                $rawStatus = $item->getAttributes()['status'];

                if (Carbon::parse($item->expiry_date)->gt($currentDate)) {
                    $results[$itemId] = [
                        'status' => 'failed',
                        'message' => __('Advertisement has not expired yet, so it cannot be renewed'),
                    ];

                    continue;
                }
                if ($package) {
                    // Calculate expiry date based on package listing duration
                    $expiryDate = HelperService::calculateItemExpiryDate($package, $userPackage);
                    $item->expiry_date = $expiryDate;

                    $userPackage->used_limit++;
                    $userPackage->save();
                } else {
                    // No package - use standard 30 days
                    $item->expiry_date = $currentDate->copy()->addDays(30);
                }

                $item->status = $rawStatus;
                $item->save();

                $results[$itemId] = [
                    'status' => 'success',
                    'item' => $item,
                ];
            }

            // Return single item response if only one item was renewed
            if (count($itemIds) === 1) {
                $itemId = $itemIds[0];
                if ($results[$itemId]['status'] === 'success') {
                    return ResponseService::successResponse(
                        __('Advertisement renewed successfully'),
                        $results[$itemId]['item']
                    );
                } else {
                    return ResponseService::errorResponse($results[$itemId]['message']);
                }
            }

            // Return multiple items response
            return ResponseService::successResponse(__('Items processed successfully'), $results);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> renewItem');

            return ResponseService::errorResponse();
        }
    }

    public function getMyReview(Request $request)
    {
        try {
            $ratings = SellerRating::where('seller_id', Auth::user()->id)->with('seller:id,name,profile', 'buyer:id,name,profile', 'item:id,name,price,image,description')->paginate(10);
            $averageRating = $ratings->avg('ratings');
            $response = [
                'average_rating' => $averageRating,
                'ratings' => $ratings,
            ];

            ResponseService::successResponse(__('Seller Details Fetched Successfully'), $response);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getSeller');
            ResponseService::errorResponse();
        }
    }

    public function addReviewReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'report_reason' => 'required|string',
            'seller_review_id' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $ratings = SellerRating::where('seller_id', Auth::user()->id)->findOrFail($request->seller_review_id);
            $ratings->update([
                'report_status' => 'reported',
                'report_reason' => $request->report_reason,
            ]);

            ResponseService::successResponse(__('Your report has been submitted successfully.'), $ratings);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> addReviewReport');
            ResponseService::errorResponse();
        }
    }

    public function getVerificationFields()
    {
        try {
            $fields = VerificationField::all();
            ResponseService::successResponse(__('Verification Field Fetched Successfully'), $fields);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, 'API Controller -> addVerificationFieldValues');
            ResponseService::errorResponse();
        }
    }

    public function sendVerificationRequest(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'verification_field' => 'sometimes|array',
                'verification_field.*' => 'sometimes',
                'verification_field_files' => 'nullable|array',
                'verification_field_files.*' => 'nullable|mimes:jpeg,png,jpg,pdf,doc|max:7168',
                'verification_field_translations' => 'nullable|json',

            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            DB::beginTransaction();

            $user = Auth::user();
            $verificationRequest = VerificationRequest::updateOrCreate([
                'user_id' => $user->id,
            ], ['status' => 'pending']);

            $user = auth()->user();
            if ($request->verification_field) {
                $itemCustomFieldValues = [];
                foreach ($request->verification_field as $id => $value) {
                    $itemCustomFieldValues[] = [
                        'user_id' => $user->id,
                        'verification_field_id' => $id,
                        'verification_request_id' => $verificationRequest->id,
                        'value' => $value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (count($itemCustomFieldValues) > 0) {
                    VerificationFieldValue::upsert($itemCustomFieldValues, ['user_id', 'verification_fields_id'], ['value', 'updated_at']);
                }
            }

            if ($request->verification_field_files) {
                $itemCustomFieldValues = [];
                foreach ($request->verification_field_files as $fieldId => $file) {
                    $itemCustomFieldValues[] = [
                        'user_id' => $user->id,
                        'verification_field_id' => $fieldId,
                        'verification_request_id' => $verificationRequest->id,
                        'value' => ! empty($file) ? FileService::upload($file, 'verification_field_files') : '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (count($itemCustomFieldValues) > 0) {
                    VerificationFieldValue::upsert($itemCustomFieldValues, ['user_id', 'verification_field_id'], ['value', 'updated_at']);
                }
            }
            if ($request->has('verification_field_translations')) {
                $fieldTranslations = json_decode($request->input('verification_field_translations'), true, 512, JSON_THROW_ON_ERROR);
                $translatedEntries = [];

                foreach ($fieldTranslations as $languageId => $fieldsById) {
                    foreach ($fieldsById as $fieldId => $translatedValue) {
                        $translatedEntries[] = [
                            'user_id' => $user->id,
                            'verification_field_id' => $fieldId,
                            'verification_request_id' => $verificationRequest->id,
                            'language_id' => $languageId,
                            'value' => is_array($translatedValue) ? implode(',', $translatedValue) : $translatedValue,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if (! empty($translatedEntries)) {
                    // upsert to avoid duplicates — if necessary
                    VerificationFieldValue::upsert(
                        $translatedEntries,
                        ['user_id', 'verification_field_id'],
                        ['value', 'updated_at', 'language_id']
                    );
                }
            }

            DB::commit();

            ResponseService::successResponse(__('Verification request submitted successfully.'));
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> SendVerificationRequest');
            ResponseService::errorResponse();
        }
    }

    public function getVerificationRequest(Request $request)
    {
        try {
            $verificationRequest = VerificationRequest::with([
                'verification_field_values.verification_field.translations',
            ])->owner()->first();

            if (empty($verificationRequest)) {
                ResponseService::errorResponse('No Request found');
            }

            $response = $verificationRequest->toArray();
            $response['verification_fields'] = [];

            // Get current language for translation
            $contentLangCode = $request->header('Content-Language') ?? app()->getLocale();
            $currentLanguage = Language::where('code', $contentLangCode)->first();
            $currentLangId = $currentLanguage->id ?? 1;

            foreach ($verificationRequest->verification_field_values as $verificationFieldValue) {
                if (
                    $verificationFieldValue->relationLoaded('verification_field') &&
                    ! empty($verificationFieldValue->verification_field)
                ) {

                    // if (empty($verificationFieldValue->language_id) || $verificationFieldValue->language_id = null) {
                    //     $verificationFieldValue->language_id = $currentLangId;
                    // }

                    $field = $verificationFieldValue->verification_field;
                    $tempRow = $field->toArray();

                    $rawValue = $verificationFieldValue->value;

                    // Normalize value to array
                    $normalizedValue = [];
                    if ($field->type === 'fileinput') {
                        $normalizedValue = ! empty($rawValue) ? [url(Storage::url($rawValue))] : [];
                    } elseif (is_array($rawValue)) {
                        $normalizedValue = $rawValue;
                    } elseif (is_string($rawValue)) {
                        $decoded = json_decode($rawValue, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $normalizedValue = $decoded;
                        } else {
                            $normalizedValue = [$rawValue];
                        }
                    } elseif (! empty($rawValue)) {
                        $normalizedValue = [$rawValue];
                    }

                    // Set normalized value
                    $tempRow['value'] = array_map('trim', explode(',', $normalizedValue[0]));

                    // Set verification_field_value with normalized value
                    $tempRow['verification_field_value'] = $verificationFieldValue->toArray();
                    unset($tempRow['verification_field_value']['verification_field']);
                    $tempRow['verification_field_value']['value'] = $normalizedValue;
                    $tempRow['verification_field_value']['language_id'] = $verificationFieldValue->language_id;
                    // Handle translated_selected_values
                    $selected = [];
                    $type = $field->type ?? null;
                    $allPossibleValues = $field->values ?? [];

                    // Fetch translated values (if available)
                    $translatedValues = [];
                    if (! empty($field->translations)) {
                        $translation = collect($field->translations)->firstWhere('language_id', $currentLangId);
                        $translatedValues = $translation['value'] ?? [];
                    }
                    if (empty($translatedValues)) {
                        $translatedValues = $allPossibleValues;
                    }

                    if (in_array($type, ['checkbox', 'radio', 'dropdown'])) {
                        foreach ($normalizedValue as $val) {
                            $index = array_search($val, $allPossibleValues);
                            $translatedVal = ($index !== false && isset($translatedValues[$index]))
                                ? $translatedValues[$index]
                                : $val;
                            $selected[] = $translatedVal;
                        }
                    } elseif (in_array($type, ['textbox', 'number'])) {
                        $selected = $normalizedValue;
                    }
                    $tempRow['language_id'] = $verificationFieldValue->language_id;
                    $tempRow['translated_selected_values'] = $selected;
                    $response['verification_fields'][] = $tempRow;
                }
            }

            ResponseService::successResponse(__('Verification request fetched successfully.'), $response);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> SendVerificationRequest');
            ResponseService::errorResponse();
        }
    }

    public function seoSettings(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'nullable',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $settings = new SeoSetting;
            if (! empty($request->page)) {
                $settings = $settings->where('page', $request->page);
            }

            $settings = $settings->get();
            ResponseService::successResponse(__('SEO settings fetched successfully.'), $settings);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> seoSettings');
            ResponseService::errorResponse();
        }
    }

    public function getCategories(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'language_code' => 'nullable',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $categories = Category::all();
            $languageCode = $request->get('language_code', 'en');

            $translator = new GoogleTranslate($languageCode);
            $categoriesJson = $categories->toJson();
            $translatedJson = $translator->translate($categoriesJson);
            $translatedCategories = json_decode($translatedJson, true);

            return ResponseService::successResponse(null, $translatedCategories);
            ResponseService::successResponse(null, $sql);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getCategories');
            ResponseService::errorResponse();
        }
    }

    public function bankTransferUpdate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_transection_id' => 'required|integer',
                'payment_receipt' => 'required|file|mimes:jpg,jpeg,png|max:7048',
            ]);

            if ($validator->fails()) {
                return ResponseService::validationError($validator->errors()->first());
            }
            $transaction = PaymentTransaction::where('user_id', Auth::user()->id)->findOrFail($request->payment_transection_id);

            if (! $transaction) {
                return ResponseService::errorResponse(__('Transaction not found.'));
            }
            $receiptPath = ! empty($request->file('payment_receipt'))
                ? FileService::upload($request->file('payment_receipt'), 'bank-transfer')
                : '';
            $transaction->update([
                'payment_receipt' => $receiptPath,
                'payment_status' => 'under review',
            ]);

            return ResponseService::successResponse(__('Payment transaction updated successfully.'), $transaction);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> bankTransferUpdate');

            return ResponseService::errorResponse();
        }
    }
    public function getOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->query(), [
                'country_code' => 'required|string|max:5',
                'number' => 'required|string|max:15',
            ]);

            if ($validator->fails()) {
                return ResponseService::validationError($validator->errors()->first());
            }

            // Normalize inputs
            $countryCode = ltrim(trim($request->query('country_code')), '+');
            $number = preg_replace('/\D/', '', $request->query('number'));

            $toNumber = '+' . $countryCode . $number;

            $twilioSettings = Setting::whereIn('name', [
                'twilio_account_sid',
                'twilio_auth_token',
                'twilio_my_phone_number',
            ])->pluck('value', 'name');

            if ($twilioSettings->count() < 3) {
                return ResponseService::errorResponse(__('Twilio settings are missing.'));
            }

            $client = new TwilioRestClient(
                $twilioSettings['twilio_account_sid'],
                $twilioSettings['twilio_auth_token']
            );

            try {
                $client->lookups->v1->phoneNumbers($toNumber)->fetch();
            } catch (\Throwable $e) {
                return ResponseService::errorResponse(__('Invalid phone number.'));
            }

            $otp = rand(100000, 999999);
            $expireAt = now()->addMinutes(10);

            NumberOtp::updateOrCreate(
                [
                    'number' => $number,
                ],
                [
                    'otp' => $otp,
                    'expire_at' => $expireAt,
                ]
            );

            // Send OTP
            $client->messages->create($toNumber, [
                'from' => $twilioSettings['twilio_my_phone_number'],
                'body' => "Your OTP is: $otp. It expires in 10 minutes.",
            ]);

            return ResponseService::successResponse(__('OTP sent successfully.'));
        } catch (\Throwable $th) {
            ResponseService::logErrorResponse($th, 'OTP Controller -> getOtp');

            return ResponseService::errorResponse();
        }
    }

    public function verifyOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'number' => 'required|string',
                'country_code' => 'required|string',
                'otp' => 'required|numeric|digits:6',
                'password' => 'nullable|string|min:6',
            ]);

            if ($validator->fails()) {
                return ResponseService::validationError($validator->errors()->first());
            }

            $number = $request->query('number');
            $countryCode = $request->query('country_code');

            // Format full phone number
            // $requestNumber = $countryCode.$number;

            // $trimmedNumber = ltrim($requestNumber, '+');
            // $toNumber = '+'.$trimmedNumber;

            $otpRecord = NumberOtp::where('number', $number)->first();
            if (! $otpRecord) {
                return ResponseService::errorResponse(__('OTP not found.'));
            }
            if (now()->isAfter($otpRecord->expire_at)) {
                return ResponseService::validationError(__('OTP has expired.'));
            }

            if ($otpRecord->attempts >= 3) {
                $otpRecord->delete();

                return ResponseService::validationError(__('OTP expired after 3 failed attempts.'));
            }

            if ($otpRecord->otp != $request->otp) {
                $otpRecord->increment('attempts');

                return ResponseService::validationError(__('Invalid OTP.'));
            }
            $otpRecord->delete();

            $user = User::where('mobile', $request->number)->where('type', 'phone')->first();

            if (! $user) {
                $user = User::create([
                    'mobile' => $request->number,
                    'type' => 'phone',
                    'country_code' => $countryCode,
                    'password' => ! empty($request->password) ? Hash::make($request->password) : null,
                ]);
                $user->assignRole('User');
            }else{
                if (! empty($countryCode)) {
                    $user->country_code = $countryCode;
                }
                $user->save();
            }

            Auth::login($user);
            $auth = User::find(Auth::id());

            $token = $auth->createToken($auth->name ?? '')->plainTextToken;

            return ResponseService::successResponse(__('User logged-in successfully'), $auth, ['token' => $token]);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'OTP Controller -> verifyOtp');

            return ResponseService::errorResponse();
        }
    }

    public function userExists(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|string',
            'country_code' => 'required|string',
            'forgot_password' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        try {
            $mobile = ltrim($request->mobile, '+');
            $countryCode = ltrim($request->country_code, '+');
            $mobileWithCode = $countryCode . $mobile;
            $userExists = User::where('type', 'phone')
                    ->where('mobile', $mobile)
                    ->withTrashed()
                    ->first();
                
                if (! $userExists) {
                    return ResponseService::errorResponse(
                        __('User does not exist'),
                        ['user_exists' => false]
                    );
                }

                // ❌ user found but deactivated
                if ($userExists->deleted_at) {
                    return ResponseService::errorResponse(
                        __('User is deactivated. Please contact the administrator.'),
                        ['user_exists' => false]
                    );
                }
            if ($userExists) {
                return ResponseService::successResponse(
                    __('User already exists'),
                    ['user_exists' => true]
                );
            }

            return ResponseService::errorResponse(
                __('User does not exist'),
                ['user_exists' => false]
            );
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> userExists');
            return ResponseService::errorResponse();
        }
    }
    public function applyJob(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:7168',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        try {
            $userId = Auth::id();
            $post = Item::approved()->notOwner()->findOrFail($request->item_id);
            $alreadyApplied = JobApplication::where('item_id', $request->item_id)
                ->where('user_id', $userId)
                ->exists();

            if ($alreadyApplied) {
                return ResponseService::validationError(__('You have already applied for this job.'));
            }
            $resumePath = null;
            if ($request->hasFile('resume')) {
                $resumePath = FileService::upload($request->resume, 'job_resume');
            }

            $application = JobApplication::create([
                'item_id' => $post->id,
                'user_id' => Auth::user()->id,
                'recruiter_id' => $post->user_id,
                'full_name' => $request->full_name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'resume' => $resumePath,
            ]);

            $user_token = UserFcmToken::where('user_id', $post->user_id)->pluck('fcm_token')->toArray();
            if (! empty($user_token)) {
                NotificationService::sendFcmNotification(
                    $user_token,
                    'New Job Application',
                    $request->full_name . ' applied for your job post: ' . $post->name,
                    'job-application',
                    ['item_id' => $post->id]
                );
            }

            return ResponseService::successResponse(__('Application submitted successfully.'), $application);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> applyJob');

            return ResponseService::errorResponse();
        }
    }

    public function recruiterApplications(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'nullable|integer',
            'page' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }
        try {
            $user = Auth::user();

            $applications = JobApplication::where('recruiter_id', $user->id)
                ->with('user:id,name,email', 'item:id,name');
            if (! empty($request->item_id)) {
                $applications->where('item_id', $request->item_id);
            }

            $applications = $applications->latest()->paginate();

            return ResponseService::successResponse(__('Recruiter applications fetched'), $applications);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> recruiterApplications');

            return ResponseService::errorResponse();
        }
    }

    public function myJobApplications(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'nullable|integer',
            'page' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }
        try {
            $user = Auth::user();

            $applications = JobApplication::where('user_id', $user->id);

            if (! empty($request->item_id)) {
                $applications->where('item_id', $request->item_id);
            }

            $applications = $applications->with([
                'item:id,name,user_id',
                'recruiter:id,name,email',
            ])
                ->latest()
                ->paginate();

            return ResponseService::successResponse(__('Your job applications fetched'), $applications);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> myJobApplications');

            return ResponseService::errorResponse();
        }
    }

    public function updateJobStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'job_id' => 'required|exists:job_applications,id',
            'status' => 'required|in:accepted,rejected',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        try {
            $user = Auth::user();
            $application = JobApplication::with('item')->findOrFail($request->job_id);

            if ($application->recruiter_id !== $user->id) {
                return ResponseService::errorResponse(__('Unauthorized to update this job status.'), 403);
            }

            $application->update(['status' => $request->status]);

            // Optional: Notify the applicant
            $user_token = UserFcmToken::where('user_id', $application->user_id)->pluck('fcm_token')->toArray();
            if (! empty($user_token)) {
                NotificationService::sendFcmNotification(
                    $user_token,
                    'Application ' . ucfirst($request->status),
                    'Your application for job post has been ' . $request->status,
                    'application-status',
                    ['job_id' => $application->id]
                );
            }

            return ResponseService::successResponse(__('Application status updated.'), $application);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> updateJobStatus');

            return ResponseService::errorResponse();
        }
    }

    public function getLocationFromCoordinates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'lang' => 'nullable|string',
            'search' => 'nullable|string',
            'place_id' => 'nullable|string',
            'session_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        try {
            $lat = $request->lat;
            $lng = $request->lng;
            $lang = $request->lang ?? 'en';
            $search = $request->search;
            $placeId = $request->place_id;
            $mapProvider = Setting::where('name', 'map_provider')->value('value') ?? 'free_api';

            // Determine current language ID
            $contentLangCode = $request->header('Content-Language') ?? app()->getLocale();
            $currentLanguage = Language::where('code', $contentLangCode)->first();
            $currentLangId = $currentLanguage->id ?? 1;

            /**
             * 🔍 Handle search query
             */
            if ($search) {
                if ($mapProvider === 'google_places') {
                    $apiKey = Setting::where('name', 'place_api_key')->value('value');
                    if (! $apiKey) {
                        return ResponseService::errorResponse(__('Google Maps API key not set'));
                    }

                    $response = Http::get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
                        'key' => $apiKey,
                        'input' => $search,
                        'language' => $lang,
                        'sessiontoken' => $request->session_id, // ✅ added
                    ]);

                    return $response->successful()
                        ? ResponseService::successResponse(__('Location fetched from Google API'), $response->json())
                        : ResponseService::errorResponse(__('Failed to fetch from Google Maps API'));
                } else {
                    // Search Areas with translations
                    $areas = Area::with([
                        'translations' => fn($q) => $q->where('language_id', $currentLangId),
                        'city.translations' => fn($q) => $q->where('language_id', $currentLangId),
                        'city.state.translations' => fn($q) => $q->where('language_id', $currentLangId),
                        'city.state.country.nametranslations' => fn($q) => $q->where('language_id', $currentLangId),
                    ])
                        ->where('name', 'like', "%{$search}%")
                        ->limit(10)
                        ->get();

                    if ($areas->isNotEmpty()) {
                        return ResponseService::successResponse(__('Matching areas found'), $areas->map(function ($area) {
                            return [
                                'area_id' => $area->id,
                                'area' => $area->name,
                                'area_translation' => optional($area->translations->first())->name ?? $area->name,
                                'city_id' => optional($area->city)->id,
                                'city' => optional($area->city)->name,
                                'city_translation' => optional($area->city->translations->first())->name ?? optional($area->city)->name,
                                'state' => optional($area->city->state)->name,
                                'state_translation' => optional($area->city->state->translations->first())->name ?? optional($area->city->state)->name,
                                'country' => optional($area->city->state->country)->name,
                                'country_translation' => optional($area->city->state->country->nametranslations->first())->name ?? optional($area->city->state->country)->name,
                                'latitude' => $area->latitude,
                                'longitude' => $area->longitude,
                            ];
                        }));
                    }

                    // Search Cities with translations
                    $cities = City::with([
                        'translations' => fn($q) => $q->where('language_id', $currentLangId),
                        'state.translations' => fn($q) => $q->where('language_id', $currentLangId),
                        'state.country.nametranslations' => fn($q) => $q->where('language_id', $currentLangId),
                    ])
                        ->where('name', 'like', "%{$search}%")
                        ->orWhereHas('state', fn($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('state.country', fn($q) => $q->where('name', 'like', "%{$search}%"))
                        ->limit(10)
                        ->get();

                    if ($cities->isEmpty()) {
                        return ResponseService::errorResponse(__('No matching location found'));
                    }

                    return ResponseService::successResponse(__('Matching cities found'), $cities->map(function ($city) {
                        return [
                            'city_id' => $city->id,
                            'city' => $city->name,
                            'city_translation' => optional($city->translations->first())->name ?? $city->name,
                            'state' => optional($city->state)->name,
                            'state_translation' => optional($city->state->translations->first())->name ?? optional($city->state)->name,
                            'country' => optional($city->state->country)->name,
                            'country_translation' => optional($city->state->country->nametranslations->first())->name ?? optional($city->state->country)->name,
                            'latitude' => $city->latitude,
                            'longitude' => $city->longitude,
                        ];
                    }));
                }
            }

            /**
             * 📍 Get location by coordinates
             */
            if (! empty($lat) && ! empty($lng)) {
                if ($mapProvider === 'google_places') {
                    $apiKey = Setting::where('name', 'place_api_key')->value('value');
                    if (! $apiKey) {
                        return ResponseService::errorResponse(__('Google Maps API key not set'));
                    }

                    $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                        'latlng' => "{$lat},{$lng}",
                        'key' => $apiKey,
                        'language' => $lang,
                        'sessiontoken' => $request->session_id, // ✅ added
                    ]);

                    return $response->successful()
                        ? ResponseService::successResponse(__('Location fetched from Google API'), $response->json())
                        : ResponseService::errorResponse(__('Failed to fetch from Google Maps API'));
                } else {

                    $closestCity = City::with([
                        'translations' => fn($q) => $q->where('language_id', $currentLangId),
                        'state.translations' => fn($q) => $q->where('language_id', $currentLangId),
                        'state.country.nametranslations' => fn($q) => $q->where('language_id', $currentLangId),
                    ])
                        ->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->selectRaw('
                        id, name, latitude, longitude, state_id,
                        (6371 * acos(cos(radians(?))
                            * cos(radians(latitude))
                            * cos(radians(longitude) - radians(?))
                            + sin(radians(?))
                            * sin(radians(latitude)))) AS distance
                    ', [$lat, $lng, $lat])
                        ->orderBy('distance', 'asc')
                        ->first();

                    if (! $closestCity) {
                        return ResponseService::errorResponse(__('No nearby city found'));
                    }

                    $closestArea = Area::with([
                        'translations' => fn($q) => $q->where('language_id', $currentLangId),
                    ])
                        ->where('city_id', $closestCity->id)
                        ->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->selectRaw('
                        id, name, latitude, longitude, city_id,
                        (6371 * acos(cos(radians(?))
                            * cos(radians(latitude))
                            * cos(radians(longitude) - radians(?))
                            + sin(radians(?))
                            * sin(radians(latitude)))) AS distance
                    ', [$lat, $lng, $lat])
                        ->orderBy('distance', 'asc')
                        ->first();

                    return ResponseService::successResponse(__('Location fetched from local database'), [
                        'city_id' => $closestCity->id,
                        'city' => $closestCity->name,
                        'city_translation' => optional($closestCity->translations->first())->name ?? $closestCity->name,
                        'state' => optional($closestCity->state)->name,
                        'state_translation' => optional($closestCity->state->translations->first())->name ?? optional($closestCity->state)->name,
                        'country' => optional($closestCity->state->country)->name,
                        'country_translation' => optional($closestCity->state->country->nametranslations->first())->name ?? optional($closestCity->state->country)->name,
                        'area_id' => optional($closestArea)->id,
                        'area' => optional($closestArea)->name,
                        'area_translation' => optional($closestArea?->translations?->first())->name ?? $closestArea?->name,
                        'latitude' => $closestCity->latitude,
                        'longitude' => $closestCity->longitude,
                    ]);
                }
            }

            /**
             * 🏷️ Handle place_id
             */
            if ($placeId) {
                if ($mapProvider === 'google_places') {
                    $apiKey = Setting::where('name', 'place_api_key')->value('value');
                    if (! $apiKey) {
                        return ResponseService::errorResponse(__('Google Maps API key not set'));
                    }
                    $sessionParam = $request->session_id ? "&sessiontoken={$request->session_id}" : '';
                    $url = "https://maps.googleapis.com/maps/api/geocode/json?place_id={$placeId}&key={$apiKey}&language={$lang}{$sessionParam}";

                    // $url = "https://maps.googleapis.com/maps/api/geocode/json?place_id={$placeId}&key={$apiKey}&language={$lang}";
                    $response = Http::get($url);

                    return $response->successful()
                        ? ResponseService::successResponse(__('Location fetched from place_id'), $response->json())
                        : ResponseService::errorResponse(__('Failed to fetch from Google Maps API using place_id'));
                } else {
                    return ResponseService::errorResponse(__('place_id is only supported with Google Maps provider'));
                }
            }
        } catch (\Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getLocationFromCoordinates');

            return ResponseService::errorResponse(__('Failed to fetch location'));
        }
    }

    public function subscribeFCMTopic(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'topic' => 'required|string',
        ]);

        $serverKey = env('FIREBASE_SERVER_KEY'); // legacy server key
        $url = "https://iid.googleapis.com/iid/v1/{$request->fcm_token}/rel/topics/{$request->topic}";

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'key=' . $serverKey,
            'access_token_auth' => true,
            'Content-Type' => 'application/json',
        ])->post($url);

        if ($response->successful()) {
            return response()->json(['error' => false, 'message' => 'Subscribed successfully']);
        }

        return response()->json(['error' => true, 'details' => $response->body()], $response->status());
    }

    public function getItemSlugs(Request $request)
    {
        try {
            $items = Item::without('translations')
                ->select('id', 'slug', 'updated_at')
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->getNonExpiredItems()
                ->get()
                ->each->setAppends([]);

            if ($items->isEmpty()) {
                return ResponseService::errorResponse(__('No active items found.'));
            }

            return ResponseService::successResponse(__('Active item slugs fetched successfully.'), $items);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getItemSlugs');

            return ResponseService::errorResponse();
        }
    }

    public function getCategoriesSlug(Request $request)
    {
        try {
            $categories = Category::without('translations')
                ->select('id', 'slug', 'updated_at')
                ->where('status', 1)
                ->get()
                ->each->setAppends([]);

            if ($categories->isEmpty()) {
                return ResponseService::errorResponse(__('No active Categories found.'));
            }

            return ResponseService::successResponse(__('Active Categories slugs fetched successfully.'), $categories);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getCategoriesSlug');
            ResponseService::errorResponse();
        }
    }

    public function getBlogsSlug(Request $request)
    {
        try {
            $blogs = Blog::without('translations')
                ->select('id', 'slug', 'updated_at')
                ->get()
                ->each->setAppends([]);

            if ($blogs->isEmpty()) {
                return ResponseService::errorResponse(__('No active Blogs found.'));
            }

            return ResponseService::successResponse(__('Active Blogs slugs fetched successfully.'), $blogs);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getCategoriesSlug');
            ResponseService::errorResponse();
        }
    }

    public function getFeatureSectionSlug(Request $request)
    {
        try {
            $FeatureSection = FeatureSection::without('translations')
                ->select('id', 'slug', 'updated_at')
                ->get()
                ->each->setAppends([]);

            if ($FeatureSection->isEmpty()) {
                return ResponseService::errorResponse(__('No active Feature Sections found.'));
            }

            return ResponseService::successResponse(__('Active Feature Sections slugs fetched successfully.'), $FeatureSection);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getCategoriesSlug');
            ResponseService::errorResponse();
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = Auth::user();
            $validator = Validator::make($request->all(), [
                'fcm_token' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ResponseService::validationError($validator->errors()->first());
            }
            if ($request->fcm_token) {
                UserFcmToken::where('user_id', $user->id)
                    ->where('fcm_token', $request->fcm_token)
                    ->delete();
            }

            return ResponseService::successResponse(__('User logged out successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> Logout');

            return ResponseService::errorResponse();
        }
    }

    public function getSellerSlug(Request $request)
    {
        try {
            $sellers = user::select('id', 'updated_at')
                ->whereNull('deleted_at')
                ->get();

            if ($sellers->isEmpty()) {
                return ResponseService::errorResponse(__('No active seller found.'));
            }

            return ResponseService::successResponse(__('Active Seller fetched successfully.'), $sellers);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getCategoriesSlug');
            ResponseService::errorResponse();
        }
    }
}
