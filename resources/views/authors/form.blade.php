@php
    $author = $author ?? null;
    $social = old('social_links', $author?->social_links ?? []);
@endphp

<div class="card">
    <div class="card-header">{{ __('Author Details') }}</div>
    <div class="card-body mt-3">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{ __('Name') }}</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $author?->name) }}" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{ __('Slug') }}</label>
                    <input type="text" name="slug" class="form-control" value="{{ old('slug', $author?->slug) }}" placeholder="auto-generated from name">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{ __('Role') }}</label>
                    <input type="text" name="role" class="form-control" value="{{ old('role', $author?->role) }}" placeholder="Editorial Team">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{ __('Email') }}</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $author?->email) }}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{ __('Website URL') }}</label>
                    <input type="url" name="website_url" class="form-control" value="{{ old('website_url', $author?->website_url) }}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{ __('Avatar') }}</label>
                    @if($author?->avatar_url)
                        <img src="{{ $author->avatar_url }}" alt="{{ $author->name }}" style="max-height: 70px;" class="d-block mb-2">
                    @endif
                    <input type="file" name="avatar" class="form-control" accept=".jpg,.jpeg,.png,.webp,.avif">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('LinkedIn URL') }}</label>
                    <input type="url" name="linkedin_url" class="form-control" value="{{ old('linkedin_url', $social['linkedin'] ?? '') }}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('X URL') }}</label>
                    <input type="url" name="x_url" class="form-control" value="{{ old('x_url', $social['x'] ?? '') }}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('GitHub URL') }}</label>
                    <input type="url" name="github_url" class="form-control" value="{{ old('github_url', $social['github'] ?? '') }}">
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>{{ __('Bio') }}</label>
                    <textarea name="bio" class="form-control" rows="5">{{ old('bio', $author?->bio) }}</textarea>
                </div>
            </div>
            <div class="col-md-12">
                <input type="hidden" name="status" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="status" value="1" id="status" @checked(old('status', $author?->status ?? true))>
                    <label class="form-check-label" for="status">{{ __('Active') }}</label>
                </div>
            </div>
        </div>
    </div>
</div>
