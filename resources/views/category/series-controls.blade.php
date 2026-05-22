@php
    $category_data = $category_data ?? null;
    $articles = $articles ?? collect();
    $currentArticles = $currentArticles ?? collect();
    $isEditingCategory = ! empty($category_data?->id);

    if ($isEditingCategory && $currentArticles->isEmpty()) {
        $currentArticles = \App\Models\Blog::where('category_id', $category_data->id)
            ->orderByRaw('CASE WHEN sort_order IS NULL OR sort_order = 0 THEN 999999 ELSE sort_order END ASC')
            ->orderBy('title')
            ->get()
            ->map(fn ($article) => [
                'id' => $article->id,
                'title' => $article->title,
                'sort_order' => $article->sort_order,
            ])
            ->values();
    }

    $currentArticlesJson = $isEditingCategory ? $currentArticles->toJson() : old('articles', $currentArticles->toJson());
    $articlesTouched = $isEditingCategory ? '0' : old('articles_touched', '0');
@endphp

<div class="card mt-4">
    <div class="card-header">{{ __('Frontend Series Settings') }}</div>
    <div class="card-body mt-3">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('Frontend Title') }}</label>
                    <input type="text" name="series_title" class="form-control" value="{{ old('series_title', $category_data?->series_title) }}" placeholder="{{ __('Falls back to category name') }}">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>{{ __('Accent Color') }}</label>
                    <input type="color" name="accent_color" class="form-control form-control-color" value="{{ old('accent_color', $category_data?->accent_color ?? '#B8FF35') }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>{{ __('Header Nav Order') }}</label>
                    <input type="number" name="header_nav_order" class="form-control" min="0" value="{{ old('header_nav_order', $category_data?->header_nav_order ?? 0) }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>{{ __('Mobile Nav Order') }}</label>
                    <input type="number" name="mobile_nav_order" class="form-control" min="0" value="{{ old('mobile_nav_order', $category_data?->mobile_nav_order ?? 0) }}">
                </div>
            </div>
            <div class="col-md-6">
                <input type="hidden" name="show_in_header_nav" value="0">
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="show_in_header_nav" value="1" id="show_in_header_nav" @checked(old('show_in_header_nav', $category_data?->show_in_header_nav ?? false))>
                    <label class="form-check-label" for="show_in_header_nav">{{ __('Show in desktop header nav') }}</label>
                </div>
            </div>
            <div class="col-md-6">
                <input type="hidden" name="show_in_mobile_nav" value="0">
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="show_in_mobile_nav" value="1" id="show_in_mobile_nav" @checked(old('show_in_mobile_nav', $category_data?->show_in_mobile_nav ?? false))>
                    <label class="form-check-label" for="show_in_mobile_nav">{{ __('Show in mobile bottom nav') }}</label>
                </div>
            </div>
            <div class="col-md-6">
                <input type="hidden" name="is_coming_soon" value="0">
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="is_coming_soon" value="1" id="is_coming_soon" @checked(old('is_coming_soon', $category_data?->is_coming_soon ?? false))>
                    <label class="form-check-label" for="is_coming_soon">{{ __('Mark as Coming Soon') }}</label>
                </div>
                <small class="text-muted">{{ __('Use this for frontend series that are visible before articles are ready.') }}</small>
            </div>
            <div class="col-md-12 mt-3">
                <div class="form-group">
                    <label>{{ __('Frontend Description') }}</label>
                    <textarea name="series_description" class="form-control" rows="3" placeholder="{{ __('Falls back to category description') }}">{{ old('series_description', $category_data?->series_description) }}</textarea>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>{{ __('Icon Code') }}</label>
                    <textarea name="icon" class="form-control" rows="3" placeholder="<svg>...</svg>">{{ old('icon', $category_data?->icon) }}</textarea>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{ __('Meta Title') }}</label>
                    <input type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $category_data?->meta_title) }}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{ __('Meta Description') }}</label>
                    <textarea name="meta_description" class="form-control" rows="3">{{ old('meta_description', $category_data?->meta_description) }}</textarea>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>{{ __('Landing Page Content') }}</label>
                    <textarea name="series_content" class="form-control" rows="6">{{ old('series_content', $category_data?->series_content) }}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4" id="category-articles-app">
    <div class="card-header">{{ __('Category Articles') }}</div>
    <div class="card-body">
        <input type="hidden" name="articles" id="category-articles-input" value="{{ $currentArticlesJson }}">
        <input type="hidden" name="articles_touched" id="category-articles-touched" value="{{ $articlesTouched }}">

        <div class="row mb-3">
            <div class="col-md-7">
                <select id="category-article-select" class="form-control">
                    <option value="">{{ __('Add Article') }}</option>
                    @foreach($articles as $article)
                        <option value="{{ $article->id }}" data-title="{{ $article->title }}">{{ $article->title }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <ul class="list-group" id="category-articles-list"></ul>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('category-articles-input');
        const touchedInput = document.getElementById('category-articles-touched');
        const list = document.getElementById('category-articles-list');
        const select = document.getElementById('category-article-select');
        let items = [];

        try {
            items = JSON.parse(input.value || '[]');
        } catch (e) {
            items = [];
        }

        function save() {
            input.value = JSON.stringify(items);
        }

        function markTouched() {
            touchedInput.value = '1';
        }

        function render() {
            list.innerHTML = '';
            items.forEach((item, index) => {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center';
                li.innerHTML = `
                    <div>
                        <span class="badge bg-secondary me-2">${index + 1}</span>
                        <strong>${item.title}</strong>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-light" data-action="up" data-index="${index}">Up</button>
                        <button type="button" class="btn btn-sm btn-light" data-action="down" data-index="${index}">Down</button>
                        <button type="button" class="btn btn-sm btn-danger ms-2" data-action="remove" data-index="${index}">X</button>
                    </div>
                `;
                list.appendChild(li);
            });
            save();
        }

        select.addEventListener('change', function () {
            const id = parseInt(this.value, 10);
            if (!id || items.some((item) => parseInt(item.id, 10) === id)) {
                this.value = '';
                return;
            }
            items.push({ id, title: this.options[this.selectedIndex].dataset.title });
            this.value = '';
            markTouched();
            render();
        });

        list.addEventListener('click', function (event) {
            const button = event.target.closest('button[data-action]');
            if (!button) return;

            const index = parseInt(button.dataset.index, 10);
            const action = button.dataset.action;

            if (action === 'remove') {
                items.splice(index, 1);
                markTouched();
            }
            if (action === 'up' && index > 0) {
                [items[index - 1], items[index]] = [items[index], items[index - 1]];
                markTouched();
            }
            if (action === 'down' && index < items.length - 1) {
                [items[index + 1], items[index]] = [items[index], items[index + 1]];
                markTouched();
            }

            render();
        });

        render();
    });
</script>
