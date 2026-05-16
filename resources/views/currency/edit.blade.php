@extends('layouts.main')
@section('title')
    {{ __('Edit Currency') }}
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
            <a class="btn btn-primary" href="{{ route('currency.index') }}">
                < {{ __('Back to All Currencies') }} </a>
        </div>
        <div class="row">
            <form action="{{ route('currency.update', $currency->id) }}" method="POST" data-parsley-validate
                enctype="multipart/form-data">
                @method('PUT')
                @csrf
                <input type="hidden" name="edit_data" value={{ $currency->id }}>
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">{{ __('Edit Currency') }}</div>
                        <div class="card-body mt-2">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group mb-3 mandatory">
                                        <label for="iso_code">{{ __('ISO Code') }}</label>
                                        <input type="text" value="{{ $currency->iso_code }}" name="iso_code"
                                            id="iso_code" class="form-control" data-parsley-required="true"
                                            placeholder="e.g. USD, INR" maxlength="3" required pattern="[A-Z]{3}"
                                            oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3 mandatory">
                                        <label for="name">{{ __('Name') }}</label>
                                        <input type="text" value="{{ $currency->name }}" name="name" id="name"
                                            class="form-control" data-parsley-required="true"
                                            placeholder="Enter the Currency Name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3 mandatory">
                                        <label for="code">{{ __('Symbol') }}</label>
                                        <input type="text" value="{{ $currency->symbol }}" name="symbol" id="code"
                                            class="form-control" data-parsley-required="true" placeholder="Currency Symbol"
                                            required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-3 mandatory">
                                        <label for="country" class="form-label">{{ __('Country') }}</label>
                                        <select class="form-control select2" id="country_item" name="country_id"
                                            data-parsley-required="true" required>
                                            <option value="">--Select Country--</option>
                                            @foreach ($countries as $country)
                                                <option value="{{ $country->id }}"
                                                    {{ $currency->country_id == $country->id ? 'selected' : '' }}>
                                                    {{ $country->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="currency_symbol_position"
                                            class="form-label">{{ __('Currency Symbol Position') }}</label>
                                        <div class="mt-2 d-flex align-items-center">
                                            <div class="form-check me-3">
                                                <input type="radio" id="currency_symbol_left" name="symbol_position"
                                                    value="left" class="form-check-input"
                                                    {{ $currency->symbol_position == 'left' ? 'checked' : '' }}>
                                                <label for="currency_symbol_left"
                                                    class="form-check-label">{{ __('Left') }}</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="radio" id="currency_symbol_right" name="symbol_position"
                                                    value="right" class="form-check-input"
                                                    {{ $currency->symbol_position == 'right' ? 'checked' : '' }}>
                                                <label for="currency_symbol_right"
                                                    class="form-check-label">{{ __('Right') }}</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="decimal_places" class="form-label">{{ __('Decimal Places') }}</label>
                                        <input type="number" name="decimal_places" id="decimal_places" class="form-control"
                                            value="{{ $currency->decimal_places ?? 2 }}" min="0" max="6">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="thousand_separator"
                                            class="form-label">{{ __('Thousand Separator') }}</label>
                                        <input type="text" name="thousand_separator" id="thousand_separator"
                                            class="form-control" value="{{ $currency->thousand_separator ?? ',' }}"
                                            maxlength="1">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="decimal_separator"
                                            class="form-label">{{ __('Decimal Separator') }}</label>
                                        <input type="text" name="decimal_separator" id="decimal_separator"
                                            class="form-control" value="{{ $currency->decimal_separator ?? '.' }}"
                                            maxlength="1">
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 text-end">
                        <input type="submit" class="btn btn-primary" value="{{ __('Save and Back') }}">
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection
@section('script')
    <script>
        function afterCustomFieldUpdate() {
        setTimeout(function () {
            window.location.href = "{{ route('currency.index') }}";
        }, 1000)
     }
    </script>
@endsection

