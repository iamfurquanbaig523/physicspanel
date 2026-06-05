@extends('layouts.main')

@section('title')
    {{ __('Settings') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first"></div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="row">
            <div class="col-xxl-3 col-xl-4 col-lg-6 col-md-12 mb-3">
                <a href="{{ route('settings.system') }}" class="card setting_active_tab h-100" style="text-decoration: none;">
                    <div class="content d-flex h-100">
                        <div class="row mx-2">
                            <div class="provider_a test">
                                <i class="fas fa-cogs text-dark icon_font_size"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="title">{{ __('Brand Settings') }}</h5>
                        <div>{{ __('Manage name, domain, email, logo, and core site settings') }} <i class="fas fa-arrow-right mt-2 arrow_icon"></i></div>
                    </div>
                </a>
            </div>

            <div class="col-xxl-3 col-xl-4 col-lg-6 col-md-12 mb-3">
                <a href="{{ route('settings.web-settings') }}" class="card setting_active_tab h-100" style="text-decoration: none;">
                    <div class="content d-flex h-100">
                        <div class="row mx-2">
                            <div class="provider_a test">
                                <i class="fas fa-palette text-dark icon_font_size"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="title">{{ __('Web Branding') }}</h5>
                        <div>{{ __('Manage header, footer, social links, and web assets') }} <i class="fas fa-arrow-right mt-2 arrow_icon"></i></div>
                    </div>
                </a>
            </div>

            <div class="col-xxl-3 col-xl-4 col-lg-6 col-md-12 mb-3">
                <a href="{{ route('settings.seo-settings.index') }}" class="card setting_active_tab h-100" style="text-decoration: none;">
                    <div class="content d-flex h-100">
                        <div class="row mx-2">
                            <div class="provider_a test">
                                <i class="fab fa-searchengin text-dark icon_font_size"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="title">{{ __('SEO Settings') }}</h5>
                        <div>{{ __('Manage metadata for the Physics Fundamentals site') }} <i class="fas fa-arrow-right mt-2 arrow_icon"></i></div>
                    </div>
                </a>
            </div>

            <div class="col-xxl-3 col-xl-4 col-lg-6 col-md-12 mb-3">
                <a href="{{ route('settings.web-settings') }}#search-console-analytics" class="card setting_active_tab h-100" style="text-decoration: none;">
                    <div class="content d-flex h-100">
                        <div class="row mx-2">
                            <div class="provider_a test">
                                <i class="fab fa-google text-dark icon_font_size"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="title">{{ __('Search Console & Analytics') }}</h5>
                        <div>{{ __('Manage Google verification and Tag Manager from the panel') }} <i class="fas fa-arrow-right mt-2 arrow_icon"></i></div>
                    </div>
                </a>
            </div>

            <div class="col-xxl-3 col-xl-4 col-lg-6 col-md-12 mb-3">
                <a href="{{ route('company-pages.index') }}" class="card setting_active_tab h-100" style="text-decoration: none;">
                    <div class="content d-flex h-100">
                        <div class="row mx-2">
                            <div class="provider_a test">
                                <i class="fas fa-file-alt text-dark icon_font_size"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="title">{{ __('Company Pages') }}</h5>
                        <div>{{ __('Manage about, privacy, terms, contact, and other static pages') }} <i class="fas fa-arrow-right mt-2 arrow_icon"></i></div>
                    </div>
                </a>
            </div>

            @hasrole('Super Admin')
                <div class="col-xxl-3 col-xl-4 col-lg-6 col-md-12 mb-3">
                    <a href="{{ route('settings.error-logs.index') }}" class="card setting_active_tab h-100" style="text-decoration: none;">
                        <div class="content d-flex h-100">
                            <div class="row mx-2">
                                <div class="provider_a test">
                                    <i class="fa fa-file-alt text-dark icon_font_size"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="title">{{ __('Log Viewer') }}</h5>
                            <div>{{ __('Inspect panel errors') }} <i class="fas fa-arrow-right mt-2 arrow_icon"></i></div>
                        </div>
                    </a>
                </div>
            @endhasrole
        </div>
    </section>
@endsection
