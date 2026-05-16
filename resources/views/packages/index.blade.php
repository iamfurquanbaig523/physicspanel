@extends('layouts.main')

@section('title')
    {{__("Subscription Packages")}}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row d-flex align-items-center">
            <div class="col-12 col-md-6">
                <h4 class="mb-0">@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 text-end">
                @can('advertisement-listing-package-create')
                    <a class="btn btn-primary me-2" href="{{ route('package.create')}}">{{ __('Create Subscription Package') }}</a>
                @endcan
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <div id="filters">
                            <div class="row">
                                <div class="col-12 col-md-6">
                                    <label for="type">{{ __("Package Type") }}</label>
                                    <select name="type" class="form-control bootstrap-table-filter-control-type" aria-label="type" id="type">
                                        <option value="">{{ __("All") }}</option>
                                        <option value="item_listing">{{ __("Item Listing (Ads)") }}</option>
                                        <option value="advertisement">{{ __("Advertisement (Featured Ads)") }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <table class="stable-borderless table-striped" aria-describedby="mydesc" id="table_list"
                               data-toggle="table" data-url="{{ route('package.show', 1) }}" data-click-to-select="true"
                               data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                               data-search="true" data-search-align="right" data-toolbar="#filters" data-show-columns="true"
                               data-show-refresh="true" data-fixed-columns="true" data-fixed-number="1" data-fixed-right-number="1"
                               data-trim-on-search="false" data-responsive="true" data-sort-name="id" data-sort-order="desc"
                               data-pagination-successively-size="3"
                               data-escape="true"
                               data-show-export="true" data-export-options='{"fileName": "package-list","ignoreColumn": ["operate"]}' data-export-types="['pdf','json', 'xml', 'csv', 'txt', 'sql', 'doc', 'excel']"
                               data-mobile-responsive="true" data-filter-control="true" data-filter-control-container="#filters">
                            <thead class="thead-dark">
                            <tr>
                                <th scope="col" data-field="id" data-align="center" data-sortable="true">{{ __('ID') }}</th>
                                <th scope="col" data-field="icon" data-align="center" data-formatter="imageFormatter">{{ __('Image') }}</th>
                                <th scope="col" data-field="name" data-align="center" data-escape="true">{{ __('Name') }}</th>
                                <th scope="col" data-field="type" data-align="center" data-filter-name="type" data-filter-control="select" data-filter-data="" data-formatter="packageTypeFormatter">{{ __('Type') }}</th>
                                <th scope="col" data-field="category_names" data-align="center" data-formatter="categoryNamesFormatter">{{ __('Categories') }}</th>
                                <th scope="col" data-field="price" data-align="center" data-sortable="true">{{ __('Price') }}</th>
                                <th scope="col" data-field="discount_in_percentage" data-align="center" data-sortable="true">{{ __('Discount (%)') }}</th>
                                <th scope="col" data-field="final_price" data-align="center" data-sortable="true">{{ __('Final Price') }}</th>
                                <th scope="col" data-field="duration" data-align="center" data-sortable="true">{{ __('Package Duration') }}</th>
                                @canany(['advertisement-listing-package-update','advertisement-listing-package-delete'])
                                    <th scope="col" data-field="operate" data-escape="false" data-sortable="false">{{ __('Action') }}</th>
                                @endcanany
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection

