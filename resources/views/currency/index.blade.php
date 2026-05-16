@extends('layouts.main')

@section('title')
    {{ __('Currency') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 d-flex justify-content-end">
                <a class="btn btn-primary" href="{{ route('currency.create') }}">{{ __('Create Currency') }}</a>
            {{-- <div class="col-12 col-md-6 d-flex justify-content-end">
                <a class="btn btn-primary me-2" href="{{ route('currency.create') }}">{{ __('Create Currency') }}</a>
            </div> --}}
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        {{-- <div class="buttons d-flex justify-content-end">

        </div> --}}

        {{-- {{ print_r($currencies) }} --}}
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <table class="table-borderless table-striped" aria-describedby="mydesc" id="table_list"
                            data-toggle="table" data-url="{{ route('currency.show', 1) }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                            data-search="true" data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                            data-fixed-number="1" data-fixed-right-number="1" data-trim-on-search="false"
                            data-responsive="true" data-sort-name="id" data-sort-order="desc"
                            data-pagination-successively-size="3" data-table="currencies" data-status-column="deleted_at"
                            data-escape="true" data-show-export="true"
                            data-export-options='{"fileName": "currency-list","ignoreColumn": ["operate"]}'
                            data-export-types="['pdf','json', 'xml', 'csv', 'txt', 'sql', 'doc', 'excel']"
                            data-mobile-responsive="true" data-filter-control="true"
                            data-filter-control-container="#filters" data-toolbar="#filters">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="iso_code" data-sortable="true">{{ __('ISO Code') }}</th>
                                    <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>
                                    <th scope="col" data-field="symbol" data-sortable="true">{{ __('Symbol') }}</th>
                                    <th scope="col" data-field="symbol_position" data-sortable="true">
                                        {{ __('Symbol Position') }}</th>
                                    <th scope="col" data-field="country.name" data-sort-name="country_name"
                                        data-sortable="true">{{ __('Country') }}
                                    </th>
                                    @can(['blog-update', 'blog-delete'])
                                        <th scope="col" data-escape="false" data-field="operate">{{ __('Action') }}</th>
                                    @endcan
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
