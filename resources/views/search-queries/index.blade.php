@extends('layouts.main')
@section('title')
    {{ __('Search Queries') }}
@endsection

@section('page-title')
    <div class="page-title"><h4>@yield('title')</h4></div>
@endsection

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-body">
                <table class="table table-borderless table-striped" id="table_list" data-toggle="table"
                       data-url="{{ route('search-queries.show', 0) }}" data-side-pagination="server"
                       data-pagination="true" data-search="true" data-search-align="right"
                       data-show-columns="true" data-show-refresh="true" data-sort-name="id"
                       data-sort-order="desc" data-query-params="queryParams" data-mobile-responsive="true"
                       data-escape="true" data-show-export="true"
                       data-export-options='{"fileName": "search-queries","ignoreColumn": ["operate"]}'
                       data-export-types="['csv','excel','json']">
                    <thead>
                    <tr>
                        <th data-field="id" data-sortable="true">{{ __('ID') }}</th>
                        <th data-field="query" data-sortable="true">{{ __('Query') }}</th>
                        <th data-field="page" data-sortable="true">{{ __('Page') }}</th>
                        <th data-field="source" data-sortable="true">{{ __('Source') }}</th>
                        <th data-field="results_count" data-sortable="true">{{ __('Results') }}</th>
                        <th data-field="created_at" data-sortable="true">{{ __('Created') }}</th>
                        <th data-field="operate" data-escape="false">{{ __('Action') }}</th>
                    </tr>
                    </thead>
                </table>
            </div>
        </div>
    </section>
@endsection
