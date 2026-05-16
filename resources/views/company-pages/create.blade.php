@extends('layouts.main')
@section('title')
    {{ __('Create Company Page') }}
@endsection

@section('page-title')
    <div class="page-title"><h4>@yield('title')</h4></div>
@endsection

@section('content')
    <section class="section">
        <div class="buttons"><a class="btn btn-primary" href="{{ route('company-pages.index') }}">< {{ __('Back to Pages') }}</a></div>
        <form action="{{ route('company-pages.store') }}" method="POST">
            @csrf
            @include('company-pages.form')
            <div class="text-end"><button class="btn btn-primary">{{ __('Save and Back') }}</button></div>
        </form>
    </section>
@endsection
