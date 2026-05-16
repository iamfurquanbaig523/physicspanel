@extends('layouts.main')
@section('title')
    {{ __('Edit Blog') }}
@endsection

@section('page-title')
    <div class="page-title"><h4>@yield('title')</h4></div>
@endsection

@section('content')
    <section class="section">
        <div class="buttons"><a class="btn btn-primary" href="{{ route('blog.index') }}">< {{ __('Back to Blogs') }}</a></div>
        <form action="{{ route('blog.update', $blog->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('blog.form')
            <div class="text-end"><button class="btn btn-primary">{{ __('Save and Back') }}</button></div>
        </form>
    </section>
@endsection
