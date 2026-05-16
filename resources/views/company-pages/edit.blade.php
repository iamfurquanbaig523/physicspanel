@extends('layouts.main')
@section('title')
    {{ __('Edit Company Page') }}
@endsection

@section('page-title')
    <div class="page-title"><h4>@yield('title')</h4></div>
@endsection

@section('content')
    <section class="section">
        <div class="buttons"><a class="btn btn-primary" href="{{ route('company-pages.index') }}">< {{ __('Back to Pages') }}</a></div>
        <form action="{{ route('company-pages.update', $companyPage->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('company-pages.form')
            <div class="text-end"><button class="btn btn-primary">{{ __('Save and Back') }}</button></div>
        </form>
    </section>
@endsection
