@extends('layouts.main')

@section('title')
    {{ __('Brand Settings') }}
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
        <form class="create-form-without-reset" action="{{ route('settings.store') }}" method="post"
              enctype="multipart/form-data" data-success-function="successFunction" data-parsley-validate>
            @csrf

            <div class="row">
                <div class="col-lg-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="divider pt-3">
                                <h6 class="divider-text">{{ __('Website Identity') }}</h6>
                            </div>

                            <div class="form-group mandatory">
                                <label for="company_name" class="form-label">{{ __('Brand Name') }}</label>
                                <input id="company_name" name="company_name" type="text" class="form-control"
                                       value="{{ $settings['company_name'] ?? 'Search Engine Basics' }}" required>
                            </div>

                            <div class="form-group mandatory">
                                <label for="website_url" class="form-label">{{ __('Website URL') }}</label>
                                <input id="website_url" name="website_url" type="url" class="form-control"
                                       value="{{ $settings['website_url'] ?? 'https://searchenginebasics.io' }}" required>
                            </div>

                            <div class="form-group mandatory">
                                <label for="company_email" class="form-label">{{ __('Contact Email') }}</label>
                                <input id="company_email" name="company_email" type="email" class="form-control"
                                       value="{{ $settings['company_email'] ?? 'hello@searchenginebasics.io' }}" required>
                            </div>

                            <div class="form-group">
                                <label for="mail_from_address" class="form-label">{{ __('Mail From Address') }}</label>
                                <input id="mail_from_address" name="mail_from_address" type="email" class="form-control"
                                       value="{{ $settings['mail_from_address'] ?? 'hello@searchenginebasics.io' }}">
                            </div>

                            <div class="form-group">
                                <label for="company_tel1" class="form-label">{{ __('Contact Number') }}</label>
                                <input id="company_tel1" name="company_tel1" type="text" class="form-control"
                                       value="{{ $settings['company_tel1'] ?? '' }}" maxlength="24">
                            </div>

                            <div class="form-group">
                                <label for="company_address" class="form-label">{{ __('Address') }}</label>
                                <textarea id="company_address" name="company_address" class="form-control"
                                          rows="4">{{ $settings['company_address'] ?? '' }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="divider pt-3">
                                <h6 class="divider-text">{{ __('Panel Preferences') }}</h6>
                            </div>

                            <div class="form-group">
                                <label for="default_language" class="form-label">{{ __('Default Language') }}</label>
                                <select name="default_language" id="default_language" class="form-select form-control-sm">
                                    @foreach ($languages as $row)
                                        <option value="{{ $row->code }}"
                                            {{ ($settings['default_language'] ?? 'en') == $row->code ? 'selected' : '' }}>
                                            {{ $row->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">{{ __('Maintenance Mode') }}</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="maintenance_mode" id="maintenance_mode"
                                           class="checkbox-toggle-switch-input"
                                           value="{{ $settings['maintenance_mode'] ?? 0 }}">
                                    <input class="form-check-input checkbox-toggle-switch" type="checkbox" role="switch"
                                           {{ ($settings['maintenance_mode'] ?? 0) == '1' ? 'checked' : '' }}
                                           id="switch_maintenance_mode">
                                    <label class="form-check-label" for="switch_maintenance_mode"></label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="deep_link_scheme" class="form-label">{{ __('Site Scheme') }}</label>
                                <input id="deep_link_scheme" name="deep_link_scheme" type="text" class="form-control"
                                       pattern="^[a-z][a-z0-9]*$"
                                       value="{{ $settings['deep_link_scheme'] ?? 'searchenginebasics' }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="divider pt-3">
                                <h6 class="divider-text">{{ __('Brand Assets') }}</h6>
                            </div>

                            <div class="row">
                                <div class="form-group col-md-4 col-sm-12">
                                    <label class="form-label">{{ __('Favicon') }}</label>
                                    <input class="filepond" type="file" name="favicon_icon" id="favicon_icon">
                                    <img src="{{ $settings['favicon_icon'] ?? '' }}"
                                         data-custom-image="{{ asset('assets/images/logo/favicon.png') }}"
                                         class="mt-2 favicon_icon" alt="image" style="height: 54px;width: 54px;">
                                </div>

                                <div class="form-group col-md-4 col-sm-12">
                                    <label class="form-label">{{ __('Panel Logo') }}</label>
                                    <input class="filepond" type="file" name="company_logo" id="company_logo">
                                    <img src="{{ $settings['company_logo'] ?? '' }}"
                                         data-custom-image="{{ asset('assets/images/logo/logo.png') }}"
                                         class="mt-2 company_logo" alt="image" style="height: 54px;width: auto;max-width: 160px;">
                                </div>

                                <div class="form-group col-md-4 col-sm-12">
                                    <label class="form-label">{{ __('Login Image') }}</label>
                                    <input class="filepond" type="file" name="login_image" id="login_image">
                                    <img src="{{ $settings['login_image'] ?? '' }}"
                                         data-custom-image="{{ asset('assets/images/bg/login.jpg') }}"
                                         class="mt-2 login_image" alt="image" style="height: 54px;width: auto;max-width: 160px;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 d-flex justify-content-end">
                <button type="submit" value="btnAdd" class="btn btn-primary me-1 mb-3">{{ __('Save') }}</button>
            </div>
        </form>
    </section>
@endsection

@section('js')
    <script>
        function successFunction() {
            window.location.reload();
        }
    </script>
@endsection
