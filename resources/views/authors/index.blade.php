@extends('layouts.main')
@section('title')
    {{ __('Authors') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row align-items-center">
            <div class="col-12 col-md-6"><h4 class="mb-0">@yield('title')</h4></div>
            <div class="col-12 col-md-6 d-flex justify-content-end">
                @can('author-create')
                    <a class="btn btn-primary" href="{{ route('authors.create') }}">+ {{ __('Add Author') }}</a>
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
                       data-url="{{ route('authors.show', 0) }}" data-side-pagination="server"
                       data-pagination="true" data-search="true" data-search-align="right"
                       data-show-columns="true" data-show-refresh="true" data-sort-name="id"
                       data-sort-order="desc" data-query-params="queryParams" data-mobile-responsive="true"
                       data-escape="true">
                    <thead>
                    <tr>
                        <th data-field="id" data-sortable="true">{{ __('ID') }}</th>
                        <th data-field="name" data-sortable="true">{{ __('Name') }}</th>
                        <th data-field="slug" data-sortable="true">{{ __('Slug') }}</th>
                        <th data-field="role" data-sortable="true">{{ __('Role') }}</th>
                        <th data-field="email">{{ __('Email') }}</th>
                        <th data-field="blogs_count">{{ __('Articles') }}</th>
                        <th data-field="status" data-sortable="true">{{ __('Status') }}</th>
                        <th data-field="created_at" data-sortable="true">{{ __('Created') }}</th>
                        <th data-field="operate" data-escape="false">{{ __('Action') }}</th>
                    </tr>
                    </thead>
                </table>
            </div>
        </div>
    </section>
@endsection
