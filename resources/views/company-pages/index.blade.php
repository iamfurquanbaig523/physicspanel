@extends('layouts.main')
@section('title')
    {{ __('Company Pages') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row align-items-center">
            <div class="col-12 col-md-6"><h4 class="mb-0">@yield('title')</h4></div>
            <div class="col-12 col-md-6 d-flex justify-content-end">
                @can('company-page-create')
                    <a class="btn btn-primary" href="{{ route('company-pages.create') }}">+ {{ __('Add Page') }}</a>
                @endcan
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-body">
                <table class="table table-borderless table-striped" id="table_list" data-toggle="table"
                       data-url="{{ route('company-pages.show', 0) }}" data-side-pagination="server"
                       data-pagination="true" data-search="true" data-search-align="right"
                       data-show-columns="true" data-show-refresh="true" data-sort-name="id"
                       data-sort-order="desc" data-query-params="queryParams" data-mobile-responsive="true"
                       data-escape="true">
                    <thead>
                    <tr>
                        <th data-field="id" data-sortable="true">{{ __('ID') }}</th>
                        <th data-field="title" data-sortable="true">{{ __('Title') }}</th>
                        <th data-field="page_key" data-sortable="true">{{ __('Page Key') }}</th>
                        <th data-field="slug" data-sortable="true">{{ __('Slug') }}</th>
                        <th data-field="status" data-sortable="true">{{ __('Status') }}</th>
                        <th data-field="updated_at" data-sortable="true">{{ __('Updated') }}</th>
                        <th data-field="operate" data-escape="false">{{ __('Action') }}</th>
                    </tr>
                    </thead>
                </table>
            </div>
        </div>
    </section>
@endsection
