@php
    $companyPage = $companyPage ?? null;
@endphp

<div class="card">
    <div class="card-header">{{ __('Page Details') }}</div>
    <div class="card-body mt-3">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('Page Key') }}</label>
                    <input type="text" name="page_key" class="form-control" value="{{ old('page_key', $companyPage?->page_key) }}" placeholder="about-us" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('Title') }}</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $companyPage?->title) }}" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('Slug') }}</label>
                    <input type="text" name="slug" class="form-control" value="{{ old('slug', $companyPage?->slug) }}" placeholder="auto-generated from title">
                </div>
            </div>
            <div class="col-md-8">
                <div class="form-group">
                    <label>{{ __('Excerpt') }}</label>
                    <textarea name="excerpt" class="form-control" rows="3">{{ old('excerpt', $companyPage?->excerpt) }}</textarea>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('Published At') }}</label>
                    <input type="datetime-local" name="published_at" class="form-control" value="{{ old('published_at', $companyPage?->published_at?->format('Y-m-d\TH:i')) }}">
                </div>
                <input type="hidden" name="status" value="0">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" name="status" value="1" id="status" @checked(old('status', $companyPage?->status ?? true))>
                    <label class="form-check-label" for="status">{{ __('Published') }}</label>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>{{ __('Content') }}</label>
                    <textarea name="content" class="tinymce_editor form-control" rows="10">{{ old('content', $companyPage?->content) }}</textarea>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{ __('Meta Title') }}</label>
                    <input type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $companyPage?->meta_title) }}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{ __('Meta Description') }}</label>
                    <textarea name="meta_description" class="form-control" rows="3">{{ old('meta_description', $companyPage?->meta_description) }}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>

@section('script')
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            tinymce.init({
                selector: '.tinymce_editor',
                height: 440,
                menubar: false,
                plugins: 'lists link table code wordcount',
                toolbar: 'undo redo | formatselect | bold italic | bullist numlist | link table | removeformat | code',
                setup: function (editor) {
                    editor.on("change keyup", function () {
                        editor.save();
                    });
                }
            });
        });
    </script>
@endsection
