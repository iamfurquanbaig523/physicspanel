@extends('layouts.main')

@section('title')
    {{ __('Default Currency Settings') }}
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
        <form class="create-form-without-reset" action="{{ route('settings.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="row d-flex mb-3">
                <div class="col-md-6 mt-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="divider pt-3">
                                <h6 class="divider-text">{{ __('Default Currency Settings') }}</h6>
                            </div>

                            <div class="form-group row">
                                <div class="col-sm-12 mt-2">
                                    <label for="currency_iso_code" class="col-sm-12 form-check-label mt-2">{{ __('Default Currency ISO Code') }}</label>
                                    @if(count($currencies) > 0)
                                        <select name="currency_iso_code" id="currency_iso_code" class="form-select form-control-sm">
                                            @foreach($currencies as $currency)
                                                <option value="{{ $currency->iso_code }}" {{ (isset($settings['currency_iso_code']) && $settings['currency_iso_code'] == $currency->iso_code) ? 'selected' : '' }}>
                                                    {{ $currency->iso_code }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" name="currency_iso_code" id="currency_iso_code" class="form-control" 
                                            placeholder="e.g. USD" maxlength="3" required 
                                            value="{{ $settings['currency_iso_code'] ?? '' }}"
                                            oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
                                    @endif
                                </div>

                                <div class="col-sm-12 mt-2">
                                    <label for="currency_symbol" class="col-sm-12 form-check-label  mt-2">{{ __('Currency Symbol') }}</label>
                                    <input id="currency_symbol" name="currency_symbol" type="text" class="form-control" placeholder="{{ __('Currency Symbol') }}" value="{{ $settings['currency_symbol'] ?? '' }}">
                                </div>

                                <div class="col-sm-12 mt-3">
                                    <label for="currency_symbol_position" class="col-sm-12 form-check-label  mt-2">{{ __('Currency Symbol Position') }}</label>
                                    <div class="mt-2 d-flex align-items-center">
                                        <div class="form-check me-3">
                                            <input
                                                type="radio"
                                                id="currency_symbol_left"
                                                name="currency_symbol_position"
                                                value="left"
                                                class="form-check-input"
                                                {{ (isset($settings['currency_symbol_position']) && $settings['currency_symbol_position'] === 'left') ? 'checked' : '' }}
                                            >
                                            <label for="currency_symbol_left" class="form-check-label">{{ __('Left') }}</label>
                                        </div>
                                        <div class="form-check">
                                            <input
                                                type="radio"
                                                id="currency_symbol_right"
                                                name="currency_symbol_position"
                                                value="right"
                                                class="form-check-input"
                                                {{ (isset($settings['currency_symbol_position']) && $settings['currency_symbol_position'] === 'right') ? 'checked' : '' }}
                                            >
                                            <label for="currency_symbol_right" class="form-check-label">{{ __('Right') }}</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-6 mt-3">
                                    <label for="decimal_places" class="col-sm-12 form-check-label  mt-2">{{ __('Decimal Places') }}</label>
                                    <input id="decimal_places" name="decimal_places" type="number" class="form-control" value="{{ $settings['decimal_places'] ?? 2 }}" min="0" max="6">
                                </div>

                                <div class="col-sm-6 mt-3">
                                    <label for="thousand_separator" class="col-sm-12 form-check-label  mt-2">{{ __('Thousand Separator') }}</label>
                                    <input id="thousand_separator" name="thousand_separator" type="text" class="form-control" value="{{ $settings['thousand_separator'] ?? ',' }}" maxlength="1">
                                </div>

                                <div class="col-sm-6 mt-3">
                                    <label for="decimal_separator" class="col-sm-12 form-check-label  mt-2">{{ __('Decimal Separator') }}</label>
                                    <input id="decimal_separator" name="decimal_separator" type="text" class="form-control" value="{{ $settings['decimal_separator'] ?? '.' }}" maxlength="1">
                                </div>

                            </div>

                            <div class="col-12 d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary me-1 mb-3">{{ __('Save') }}</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </form>
    </section>
@endsection
