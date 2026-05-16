@extends('layouts.main')

@section('title')
    {{ __('Edit Package') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="buttons">
            <a class="btn btn-primary" href="{{ route('package.index') }}">
                < {{ __('Back to Packages') }}
            </a>
        </div>
        <form action="{{ route('package.update', $package->id) }}" class="edit-form"
            data-success-function="afterPackageUpdate" method="POST" data-parsley-validate
            enctype="multipart/form-data">
            @method('PUT')
            @csrf
            <div class="row">

                <div class="col-md-6 col-sm-12">
                    <div class="card">
                        <div class="card-header">{{ __('Edit Package') }}</div>
                        <div class="card-body mt-2">

                            <ul class="nav nav-tabs" id="langTabs" role="tablist">
                                @foreach ($languages as $key => $lang)
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link @if ($key == 0) active @endif"
                                            id="tab-{{ $lang->id }}" data-bs-toggle="tab"
                                            data-bs-target="#lang-{{ $lang->id }}" type="button" role="tab">
                                            {{ $lang->name }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="tab-content mt-3">
                                @foreach ($languages as $key => $lang)
                                    <div class="tab-pane fade @if ($key == 0) show active @endif"
                                        id="lang-{{ $lang->id }}" role="tabpanel">
                                        <input type="hidden" name="languages[]" value="{{ $lang->id }}">
                                        @if ($lang->id == 1)
                                            <div class="row">
                                                <div class="col-md-6 form-group">
                                                    <label>{{ __('Package Name') }} ({{ $lang->name }}) <span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" name="name[{{ $lang->id }}]" class="form-control"
                                                        value="{{ $translations[$lang->id]['name'] ?? '' }}"
                                                        data-parsley-required="true">
                                                </div>
                                                {{-- Row 1: IOS Product ID (Name already shown above) --}}
                                                <div class="col-md-6 form-group">
                                                    <label>{{ __('IOS Product ID') }}</label>
                                                    <input type="text" name="ios_product_id" class="form-control"
                                                        value="{{ $package->ios_product_id }}">
                                                </div>
                                            </div>
                                        @else
                                            <div class="form-group">
                                                <label>{{ __('Package Name') }} ({{ $lang->name }}) <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="name[{{ $lang->id }}]" class="form-control"
                                                    value="{{ $translations[$lang->id]['name'] ?? '' }}">
                                            </div>
                                        @endif
                                        @if ($lang->id == 1)
                                            @php
                                                $packageDurationType = ($package->duration == 'unlimited') ? 'unlimited' : 'limited';
                                                $packageDurationValue = ($package->duration == 'unlimited') ? '' : $package->duration;
                                                
                                                $adsItemLimitType = ($package->item_limit == 'unlimited') ? 'unlimited' : 'limited';
                                                $adsItemLimitValue = ($package->item_limit == 'unlimited') ? '' : $package->item_limit;
                                                
                                                $adsListingDurationType = $package->listing_duration_type ?? 'standard';
                                                $adsListingDurationDays = $package->listing_duration_days ?? null;
                                                
                                                $featuredItemLimitType = ($package->item_limit == 'unlimited') ? 'unlimited' : 'limited';
                                                $featuredItemLimitValue = ($package->item_limit == 'unlimited') ? '' : $package->item_limit;
                                                
                                                $featuredAdsDurationType = $package->listing_duration_type ?? 'standard';
                                                $featuredAdsDurationDays = $package->listing_duration_days ?? null;
                                                
                                                $keyPoints = [];
                                                if (!empty($package->key_points)) {
                                                    $keyPoints = json_decode($package->key_points, true) ?? [];
                                                }
                                            @endphp
                                            {{-- Row 2: Price and Discount Percentage --}}
                                            <div class="row">
                                                <div class="col-md-6 form-group">
                                                    <label>{{ __('Price') }} ({{ $currency_symbol }}) <span
                                                            class="text-danger">*</span></label>
                                                    <input type="number" name="price" class="form-control"
                                                        data-parsley-required="true" min="0" step="0.01"
                                                        value="{{ $package->price }}">
                                                </div>
                                                <div class="col-md-6 form-group">
                                                    <label>{{ __('Discount') }} (%) <span
                                                            class="text-danger">*</span></label>
                                                    <input type="number" name="discount_in_percentage" class="form-control"
                                                        data-parsley-required="true" min="0" max="100"
                                                        step="0.01" value="{{ $package->discount_in_percentage }}">
                                                </div>
                                            </div>

                                            {{-- Row 3: Final Price --}}
                                            <div class="row">
                                                <div class="col-md-12 form-group">
                                                    <label>{{ __('Final Price') }} ({{ $currency_symbol }}) <span
                                                            class="text-danger">*</span></label>
                                                    <input type="number" name="final_price" id="final_price" class="form-control"
                                                        data-parsley-required="true" min="0" step="0.01"
                                                        value="{{ $package->final_price }}">
                                                </div>
                                            </div>

                                            {{-- Package Duration Type --}}
                                            <div class="row">
                                                <div class="col-md-6 form-group">
                                                    <label>{{ __('Package Duration Type') }} <span
                                                            class="text-danger">*</span></label>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input package-duration-type" type="radio"
                                                            name="package_duration_type" id="package_duration_limited"
                                                            value="limited" {{ $packageDurationType == 'limited' ? 'checked' : '' }}>
                                                        <label class="form-check-label"
                                                            for="package_duration_limited">{{ __('Limited') }}</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input package-duration-type" type="radio"
                                                            name="package_duration_type" id="package_duration_unlimited"
                                                            value="unlimited" {{ $packageDurationType == 'unlimited' ? 'checked' : '' }}>
                                                        <label class="form-check-label"
                                                            for="package_duration_unlimited">{{ __('Unlimited') }}</label>
                                                    </div>
                                                    <div id="package_duration_input"
                                                        style="display: {{ $packageDurationType == 'limited' ? 'block' : 'none' }};"
                                                        class="mt-2">
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <div class="input-group-text myDivClass"
                                                                    style="height: 42px;">
                                                                    <span class="mySpanClass">{{ __('Days') }}</span>
                                                                </div>
                                                            </div>
                                                            <input type="number" name="duration" class="form-control"
                                                                min="1" placeholder="{{ __('Enter duration in days') }}"
                                                                value="{{ $packageDurationValue }}">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Package Type Radio Buttons (Read-only) --}}
                                            <div class="row form-group">
                                                <label>{{ __('Package Type') }} <span class="text-danger">*</span></label>
                                                <small class="text-muted d-block mb-2">{{ __('Package type cannot be changed after creation.') }}</small>
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input package-type-radio"
                                                            type="radio" name="type" id="type_item_listing"
                                                            value="item_listing" {{ $package->type == 'item_listing' ? 'checked' : '' }}
                                                            disabled>
                                                        <label class="form-check-label" for="type_item_listing">
                                                            {{ __('Ad Listing Package') }}
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input package-type-radio"
                                                            type="radio" name="type" id="type_advertisement"
                                                            value="advertisement" {{ $package->type == 'advertisement' ? 'checked' : '' }}
                                                            disabled>
                                                        <label class="form-check-label" for="type_advertisement">
                                                            {{ __('Featured Ads Package') }}
                                                        </label>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="type" id="package_type"
                                                    value="{{ $package->type }}">
                                            </div>

                                            {{-- Ad Listing Package Section --}}
                                            <div id="ad_listing_section"
                                                style="display: {{ $package->type == 'item_listing' ? 'block' : 'none' }};"
                                                class="border rounded p-3 mb-3">
                                                <h6 class="mb-3">{{ __('Ad Listing Package Settings') }}</h6>

                                                {{-- Item Limit --}}
                                                <div class="form-group mb-3">
                                                    <label>{{ __('Item Limit') }}</label>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input ads-item-limit-type" type="radio"
                                                            name="ads_item_limit_type" id="ads_limit_limited"
                                                            value="limited" {{ $adsItemLimitType == 'limited' ? 'checked' : '' }}>
                                                        <label class="form-check-label"
                                                            for="ads_limit_limited">{{ __('Limited') }}</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input ads-item-limit-type" type="radio"
                                                            name="ads_item_limit_type" id="ads_limit_unlimited"
                                                            value="unlimited" {{ $adsItemLimitType == 'unlimited' ? 'checked' : '' }}>
                                                        <label class="form-check-label"
                                                            for="ads_limit_unlimited">{{ __('Unlimited') }}</label>
                                                    </div>
                                                    <div id="ads_item_limit_input"
                                                        style="display: {{ $adsItemLimitType == 'limited' ? 'block' : 'none' }};"
                                                        class="mt-2">
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <div class="input-group-text myDivClass"
                                                                    style="height: 42px;">
                                                                    <span class="mySpanClass">{{ __('Number') }}</span>
                                                                </div>
                                                            </div>
                                                            <input type="number" name="ads_item_limit"
                                                                class="form-control" min="1"
                                                                placeholder="{{ __('Enter item limit') }}"
                                                                value="{{ $adsItemLimitValue }}">
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Listing Duration Type --}}
                                                <div class="form-group mb-3">
                                                    <label>{{ __('Listing Duration Type') }}</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input ads-listing-duration-type"
                                                            type="radio" name="ads_listing_duration_type"
                                                            id="ads_listing_standard" value="standard"
                                                            {{ $adsListingDurationType == 'standard' ? 'checked' : '' }}>
                                                        <label class="form-check-label"
                                                            for="ads_listing_standard">{{ __('Standard (30 days)') }}</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input ads-listing-duration-type"
                                                            type="radio" name="ads_listing_duration_type"
                                                            id="ads_listing_package" value="package"
                                                            {{ $adsListingDurationType == 'package' ? 'checked' : '' }}>
                                                        <label class="form-check-label"
                                                            for="ads_listing_package">{{ __('Package') }}</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input ads-listing-duration-type"
                                                            type="radio" name="ads_listing_duration_type"
                                                            id="ads_listing_custom" value="custom"
                                                            {{ $adsListingDurationType == 'custom' ? 'checked' : '' }}>
                                                        <label class="form-check-label"
                                                            for="ads_listing_custom">{{ __('Custom') }}</label>
                                                    </div>
                                                    <div id="ads_listing_duration_days_input"
                                                        style="display: {{ $adsListingDurationType == 'custom' ? 'block' : 'none' }};"
                                                        class="mt-2">
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <div class="input-group-text myDivClass"
                                                                    style="height: 42px;">
                                                                    <span class="mySpanClass">{{ __('Days') }}</span>
                                                                </div>
                                                            </div>
                                                            <input type="number" name="ads_listing_duration_days"
                                                                class="form-control" min="1"
                                                                placeholder="{{ __('Enter days') }}"
                                                                value="{{ $adsListingDurationDays }}">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Featured Ads Package Section --}}
                                            <div id="featured_ads_section"
                                                style="display: {{ $package->type == 'advertisement' ? 'block' : 'none' }};"
                                                class="border rounded p-3 mb-3">
                                                <h6 class="mb-3">{{ __('Featured Ads Package Settings') }}</h6>

                                                {{-- Item Limit --}}
                                                <div class="form-group mb-3">
                                                    <label>{{ __('Item Limit') }}</label>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input featured-item-limit-type"
                                                            type="radio" name="featured_item_limit_type"
                                                            id="featured_limit_limited" value="limited"
                                                            {{ $featuredItemLimitType == 'limited' ? 'checked' : '' }}>
                                                        <label class="form-check-label"
                                                            for="featured_limit_limited">{{ __('Limited') }}</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input featured-item-limit-type"
                                                            type="radio" name="featured_item_limit_type"
                                                            id="featured_limit_unlimited" value="unlimited"
                                                            {{ $featuredItemLimitType == 'unlimited' ? 'checked' : '' }}>
                                                        <label class="form-check-label"
                                                            for="featured_limit_unlimited">{{ __('Unlimited') }}</label>
                                                     </div>
                                                    <div id="featured_item_limit_input"
                                                        style="display: {{ $featuredItemLimitType == 'limited' ? 'block' : 'none' }};"
                                                        class="mt-2">
                                                        <input type="number" name="featured_item_limit"
                                                            class="form-control" min="1"
                                                            placeholder="{{ __('Enter item limit') }}"
                                                            value="{{ $featuredItemLimitValue }}">
                                                    </div>
                                                </div>

                                                {{-- Featured Ads Duration Type --}}
                                                <div class="form-group mb-3">
                                                    <label>{{ __('Featured Ads Duration Type') }}</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input featured-ads-duration-type"
                                                            type="radio" name="featured_ads_duration_type"
                                                            id="featured_ads_standard" value="standard"
                                                            {{ $featuredAdsDurationType == 'standard' ? 'checked' : '' }}>
                                                        <label class="form-check-label"
                                                            for="featured_ads_standard">{{ __('Standard (30 days)') }}</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input featured-ads-duration-type"
                                                            type="radio" name="featured_ads_duration_type"
                                                            id="featured_ads_package" value="package"
                                                            {{ $featuredAdsDurationType == 'package' ? 'checked' : '' }}>
                                                        <label class="form-check-label"
                                                            for="featured_ads_package">{{ __('Package') }}</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input featured-ads-duration-type"
                                                            type="radio" name="featured_ads_duration_type"
                                                            id="featured_ads_custom" value="custom"
                                                            {{ $featuredAdsDurationType == 'custom' ? 'checked' : '' }}>
                                                        <label class="form-check-label"
                                                            for="featured_ads_custom">{{ __('Custom') }}</label>
                                                    </div>
                                                   <div id="featured_ads_duration_days_input"
                                                        style="display: {{ $featuredAdsDurationType == 'custom' ? 'block' : 'none' }};"
                                                        class="mt-2">

                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <div class="input-group-text myDivClass" style="height: 42px;">
                                                                    <span class="mySpanClass">{{ __('Days') }}</span>
                                                                </div>
                                                            </div>

                                                            <input type="number"
                                                                name="featured_ads_duration_days"
                                                                class="form-control"
                                                                min="1"
                                                                placeholder="{{ __('Enter days') }}"
                                                                value="{{ $featuredAdsDurationDays }}">
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>

                                            {{-- Key Points --}}
                                            <div class="form-group">
                                                <label>{{ __('Key Points') }} ({{ $lang->name }})</label>
                                                <div id="key_points_container_{{ $lang->id }}">
                                                    @if (!empty($keyPoints) && count($keyPoints) > 0)
                                                        @foreach ($keyPoints as $index => $keyPoint)
                                                            <div class="form-group key-point-item">
                                                                <div class="input-group">
                                                                    <input type="text" name="key_points[{{ $lang->id }}][]"
                                                                        class="form-control"
                                                                        placeholder="{{ __('Enter key point') }}"
                                                                        value="{{ $keyPoint }}">
                                                                    <button type="button"
                                                                        class="btn btn-danger remove-key-point"
                                                                        style="{{ count($keyPoints) > 1 ? '' : 'display: none;' }}">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <div class="form-group key-point-item">
                                                            <div class="input-group">
                                                                <input type="text" name="key_points[{{ $lang->id }}][]"
                                                                    class="form-control"
                                                                    placeholder="{{ __('Enter key point') }}">
                                                                <button type="button" class="btn btn-danger remove-key-point"
                                                                    style="display: none;">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                                <button type="button" class="btn btn-sm btn-primary mt-2 add-key-point"
                                                    data-lang-id="{{ $lang->id }}">
                                                    <i class="fas fa-plus me-1"></i> {{ __('Add Key Point') }}
                                                </button>
                                            </div>

                                            {{-- Image --}}
                                            <div class="form-group">
                                                <label for="icon" class="form-label">{{ __('Icon') }}</label>
                                                <input type="file" name="icon" id="icon" class="form-control"
                                                    accept=".jpg, .jpeg, .png">
                                                {{ __('(Leave empty to keep current image)') }}
                                                <div class="field_img mt-2">
                                                    <img src="{{ empty($package->icon) ? asset('assets/img_placeholder.jpeg') : $package->icon }}"
                                                        alt="" id="blah"
                                                        class="preview-image img w-25">
                                                </div>
                                                <div class="img_error" style="color:#DC3545;"></div>
                                            </div>
                                        @else
                                            @php
                                                $translatedKeyPoints = [];
                                                if (!empty($package->translations)) {
                                                    $translation = $package->translations->where('language_id', $lang->id)->first();
                                                    if ($translation && !empty($translation->key_points)) {
                                                        $translatedKeyPoints = json_decode($translation->key_points, true) ?? [];
                                                    }
                                                }
                                            @endphp
                                            {{-- Key Points for other languages --}}
                                            <div class="form-group">
                                                <label>{{ __('Key Points') }} ({{ $lang->name }})</label>
                                                <div id="key_points_container_{{ $lang->id }}">
                                                    @if (!empty($translatedKeyPoints) && count($translatedKeyPoints) > 0)
                                                        @foreach ($translatedKeyPoints as $index => $keyPoint)
                                                            <div class="form-group key-point-item">
                                                                <div class="input-group">
                                                                    <input type="text" name="key_points[{{ $lang->id }}][]"
                                                                        class="form-control"
                                                                        placeholder="{{ __('Enter key point') }}"
                                                                        value="{{ $keyPoint }}">
                                                                    <button type="button"
                                                                        class="btn btn-danger remove-key-point"
                                                                        style="{{ count($translatedKeyPoints) > 1 ? '' : 'display: none;' }}">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <div class="form-group key-point-item">
                                                            <div class="input-group">
                                                                <input type="text" name="key_points[{{ $lang->id }}][]"
                                                                    class="form-control"
                                                                    placeholder="{{ __('Enter key point') }}">
                                                                <button type="button" class="btn btn-danger remove-key-point"
                                                                    style="display: none;">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                                <button type="button" class="btn btn-sm btn-primary mt-2 add-key-point"
                                                    data-lang-id="{{ $lang->id }}">
                                                    <i class="fas fa-plus me-1"></i> {{ __('Add Key Point') }}
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-sm-12">
                    <div class="card">
                        <div class="card-header">{{ __('Category Selection') }}</div>
                        <div class="card-body mt-2">
                            {{-- Global Package Option --}}
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_global" id="is_global" value="1" 
                                    {{ $package->is_global == 1 ? 'checked' : '' }} 
                                    {{ $package->type == 'advertisement' ? 'disabled' : '' }}>
                                <label class="form-check-label" for="is_global">
                                    <strong>{{ __('Global Package (Apply to All Categories)') }}</strong>
                                </label>
                                <small class="form-text text-muted d-block">{{ __('If checked, this package will be available for all categories.') }}</small>
                            </div>

                            <div id="category_selection" class="sub_category_lit" style="display: {{ ($package->is_global == 1 || $package->type == 'advertisement') ? 'none' : 'block' }};">
                                            @foreach ($categories as $category)
                                    <div class="category">
                                        <div class="category-header">
                                            <label>
                                                <input type="checkbox" name="selected_categories[]"
                                                    value="{{ $category->id }}" class="category-checkbox"
                                                    {{ in_array($category->id, $selected_categories) ? 'checked' : '' }}
                                                    {{ $package->type == 'advertisement' ? 'disabled' : '' }}>
                                                {{ $category->name }}
                                            </label>
                                            @if (!empty($category->subcategories))
                                                @php
                                                    $currentLang = Session::get('language');
                                                    $isRtl = false;
                                                    if (!empty($currentLang)) {
                                                        try {
                                                            $rtlRaw = method_exists($currentLang, 'getRawOriginal') ? $currentLang->getRawOriginal('rtl') : null;
                                                            if ($rtlRaw !== null) {
                                                                $isRtl = ($rtlRaw == 1 || $rtlRaw === true);
                                                            } else {
                                                                $isRtl = ($currentLang->rtl == true || $currentLang->rtl === 1);
                                                            }
                                                        } catch (\Exception $e) {
                                                            $isRtl = ($currentLang->rtl == true || $currentLang->rtl === 1);
                                                        }
                                                    }
                                                    $arrowIcon = $isRtl ? '&#xf0d9;' : '&#xf0da;';
                                                @endphp
                                                <i style="font-size:24px"
                                                    class="fas toggle-button-package {{ in_array($category->id, $selected_all_categories) ? 'open' : '' }}">
                                                    {!! $arrowIcon !!}
                                                </i>
                                            @endif
                                        </div>

                                        <div class="subcategories"
                                            style="display: {{ in_array($category->id, $selected_all_categories) ? 'block' : 'none' }};">
                                            @if (!empty($category->subcategories))
                                                @include('category.treeview', [
                                                    'categories' => $category->subcategories,
                                                    'selected_categories' => $selected_categories,
                                                    'selected_all_categories' => $selected_all_categories,
                                                ])
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-12 text-end mb-3">
                    <input type="submit" class="btn btn-primary" value="{{ __('Save and Back') }}">
                </div>
            </div>
        </form>
    </section>
@endsection

@section('js')
    <script>
        $(document).ready(function() {
            // Package type radio buttons - disabled in edit mode, but keep structure
            $('.package-type-radio').on('change', function() {
                // Prevent changes in edit mode (already disabled)
                const selectedType = $(this).val();

                if (selectedType === 'item_listing') {
                    $('#ad_listing_section').show();
                    $('#featured_ads_section').hide();
                    // Enable global/category selection for item_listing
                    // IMPORTANT: don't force uncheck "Global" in edit mode; respect stored state.
                    $('#is_global').prop('disabled', false);
                    $('.category-checkbox').prop('disabled', false);
                    // Show/hide category selection based on current global checkbox state
                    if ($('#is_global').is(':checked')) {
                        $('#category_selection').hide();
                    } else {
                        $('#category_selection').show();
                    }
                } else if (selectedType === 'advertisement') {
                    $('#ad_listing_section').hide();
                    $('#featured_ads_section').show();
                    // Disable category selection for advertisement (featured ads)
                    // Set as global package for featured ads
                    $('#is_global').prop('checked', true).prop('disabled', true);
                    $('.category-checkbox').prop('checked', false).prop('disabled', true);
                    $('#category_selection').hide();
                }
            });
            
            // Initialize on page load
            const currentPackageType = $('#package_type').val();
            if (currentPackageType) {
                $('.package-type-radio[value="' + currentPackageType + '"]').trigger('change');
            }
            // Sync category selection visibility based on is_global state after init
            $('#is_global').trigger('change');

            // Ad listing item limit toggle
            $('.ads-item-limit-type').on('change', function() {
                if ($(this).val() === 'limited') {
                    $('#ads_item_limit_input').show();
                } else {
                    $('#ads_item_limit_input').hide();
                }
            });

            // Ad listing duration type toggle
            $('.ads-listing-duration-type').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#ads_listing_duration_days_input').show();
                } else {
                    $('#ads_listing_duration_days_input').hide();
                }
            });

            // Featured item limit toggle
            $('.featured-item-limit-type').on('change', function() {
                if ($(this).val() === 'limited') {
                    $('#featured_item_limit_input').show();
                } else {
                    $('#featured_item_limit_input').hide();
                }
            });

            // Featured ads duration type toggle
            $('.featured-ads-duration-type').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#featured_ads_duration_days_input').show();
                } else {
                    $('#featured_ads_duration_days_input').hide();
                }
            });

            // Package duration type toggle
            $('.package-duration-type').on('change', function() {
                if ($(this).val() === 'limited') {
                    $('#package_duration_input').show();
                    $('#package_duration_input input[name="duration"]').attr('data-parsley-required', 'true');
                } else {
                    $('#package_duration_input').hide();
                    $('#package_duration_input input[name="duration"]').removeAttr('data-parsley-required');
                }
            });

            // Add key point
            $(document).on('click', '.add-key-point', function() {
                const langId = $(this).data('lang-id');
                const container = $('#key_points_container_' + langId);
                const newPoint = container.find('.key-point-item').first().clone();
                newPoint.find('input').val('');
                newPoint.find('.remove-key-point').show();
                container.append(newPoint);
                updateRemoveButtons();
            });

            // Remove key point
            $(document).on('click', '.remove-key-point', function() {
                const container = $(this).closest('#key_points_container_' + $(this).closest('.tab-pane')
                    .find('input[name="languages[]"]').val());
                if ($(this).closest('.key-point-item').siblings('.key-point-item').length > 0) {
                    $(this).closest('.key-point-item').remove();
                    updateRemoveButtons();
                }
            });

            function updateRemoveButtons() {
                $('.key-point-item').each(function() {
                    const container = $(this).closest('#key_points_container_' + $(this).closest(
                        '.tab-pane').find('input[name="languages[]"]').val());
                    const count = container.find('.key-point-item').length;
                    container.find('.remove-key-point').toggle(count > 1);
                });
            }

            // Global package toggle
            $('#is_global').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#category_selection').hide();
                    $('.category-checkbox').prop('checked', false);
                } else {
                    $('#category_selection').show();
                }
            });

            // Category toggle buttons - use event delegation to handle dynamically added elements
            $(document).on('click', '.toggle-button-package', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                $(this).toggleClass('open');
                $(this).closest('.category').find('.subcategories').stop(true, true).slideToggle();
                return false;
            });
            
            // Prevent category checkbox and label from triggering toggle
            $(document).on('click', '.category-checkbox', function(e) {
                e.stopPropagation();
                e.stopImmediatePropagation();
            });
            
            // Prevent label click from triggering toggle when clicking on checkbox area
            $(document).on('click', '.category-header label', function(e) {
                // Only prevent if clicking on the label text, not the checkbox itself
                if ($(e.target).is('input[type="checkbox"]')) {
                    e.stopPropagation();
                }
            });
            
            // Prevent category header from triggering toggle except when clicking the toggle button
            $(document).on('click', '.category-header', function(e) {
                // If clicking on toggle button, let it handle
                if ($(e.target).hasClass('toggle-button-package') || $(e.target).closest('.toggle-button-package').length) {
                    return;
                }
                // If clicking on checkbox or label, let it handle
                if ($(e.target).is('input[type="checkbox"]') || $(e.target).is('label') || $(e.target).closest('label').length) {
                    return;
                }
                // Otherwise, prevent any action
                e.stopPropagation();
            });

            // Image preview functionality
            $('#icon').on('change', function() {
                const [file] = this.files;
                if (file) {
                    $('#blah').attr('src', URL.createObjectURL(file));
                }
            });

            // Initialize category selection state based on current package type
            if (currentPackageType === 'advertisement') {
                // Disable all category checkboxes for featured ads
                $('.category-checkbox').prop('disabled', true);
            }

            // Auto-calculate final price based on price and discount
            function calculateFinalPrice() {
                const price = parseFloat($('input[name="price"]').val()) || 0;
                const discount = parseFloat($('input[name="discount_in_percentage"]').val()) || 0;
                
                if (price > 0 && discount >= 0 && discount <= 100) {
                    const discountAmount = (price * discount) / 100;
                    const finalPrice = price - discountAmount;
                    $('#final_price').val(finalPrice.toFixed(2));
                }
            }

            $('input[name="price"], input[name="discount_in_percentage"]').on('input', calculateFinalPrice);
        });

        function afterPackageUpdate() {
            setTimeout(function() {
                window.location.href = "{{ route('package.index') }}";
            }, 1000)
        }
    </script>
@endsection

