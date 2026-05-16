@extends('layouts.main')
@section('title')
    {{ __('Create Author') }}
@endsection

@section('page-title')
    <div class="page-title"><h4>@yield('title')</h4></div>
@endsection

@section('content')
    <section class="section">
        <div class="buttons"><a class="btn btn-primary" href="{{ route('authors.index') }}">< {{ __('Back to Authors') }}</a></div>
        <form action="{{ route('authors.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('authors.form')
            <div class="text-end"><button class="btn btn-primary">{{ __('Save and Back') }}</button></div>
        </form>
    </section>
@endsection
