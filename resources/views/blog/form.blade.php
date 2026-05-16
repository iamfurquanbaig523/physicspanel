@php
    $blog = $blog ?? null;
    $categories = $categories ?? collect();
    $translations = $translations ?? collect();
    $attributePresets = $attributePresets ?? [];
    $rawAttributes = old('content_attributes', $blog?->content_attributes ?? []);

    if (is_string($rawAttributes)) {
        $decodedAttributes = json_decode($rawAttributes, true);

        if (is_string($decodedAttributes)) {
            $decodedAttributes = json_decode($decodedAttributes, true);
        }

        $rawAttributes = is_array($decodedAttributes) ? $decodedAttributes : [];
    }

    $attributesJson = json_encode(
        array_values(is_array($rawAttributes) ? $rawAttributes : []),
        JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT
    );

    $rawAttributePresets = old('attribute_presets', $attributePresets);

    if (is_string($rawAttributePresets)) {
        $decodedPresets = json_decode($rawAttributePresets, true);

        if (is_string($decodedPresets)) {
            $decodedPresets = json_decode($decodedPresets, true);
        }

        $rawAttributePresets = is_array($decodedPresets) ? $decodedPresets : [];
    }

    $attributePresetsJson = json_encode(
        array_values(is_array($rawAttributePresets) ? $rawAttributePresets : []),
        JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT
    );

    $faqItems = old('faqs');

    if ($faqItems === null && $blog?->relationLoaded('faqs')) {
        $faqItems = $blog->faqs->map(fn ($faq) => [
            'question' => $faq->question,
            'answer' => $faq->answer,
            'sort_order' => $faq->sort_order,
            'is_visible' => $faq->is_visible ? 1 : 0,
            'include_in_schema' => $faq->include_in_schema ? 1 : 0,
            'schema_question' => $faq->schema_question,
            'schema_answer' => $faq->schema_answer,
        ])->values()->all();
    }

    $faqItems = array_values(is_array($faqItems) ? $faqItems : []);
@endphp

<div class="card">
    <div class="card-header">{{ __('Article Settings') }}</div>
    <div class="card-body mt-3">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('Slug') }}</label>
                    <input type="text" name="slug" class="form-control" value="{{ old('slug', $blog?->slug) }}" placeholder="auto-generated from title">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('Category / Frontend Series') }}</label>
                    <select name="category_id" class="form-control select2 w-100" data-placeholder="{{ __('Select Category') }}">
                        <option value="">{{ __('No category') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $blog?->category_id) == $category->id)>
                                {{ $category->series_title ?: $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>{{ __('Series Order') }}</label>
                    <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', $blog?->sort_order ?? 0) }}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('Primary Author') }}</label>
                    <select name="author_id" class="form-control">
                        <option value="">{{ __('Select Author') }}</option>
                        @foreach($authors as $author)
                            <option value="{{ $author->id }}" @selected(old('author_id', $blog?->author_id) == $author->id)>{{ $author->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('Additional Authors') }}</label>
                    <select name="additional_authors[]" class="form-control select2 w-100" multiple="multiple" data-placeholder="{{ __('Select Authors') }}">
                        @foreach($authors as $author)
                            <option value="{{ $author->id }}" @selected(in_array($author->id, old('additional_authors', $blog?->additionalAuthors->pluck('id')->toArray() ?? [])))>{{ $author->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('Reviewers') }}</label>
                    <select name="reviewers[]" class="form-control select2 w-100" multiple="multiple" data-placeholder="{{ __('Select Reviewers') }}">
                        @foreach($authors as $author)
                            <option value="{{ $author->id }}" @selected(in_array($author->id, old('reviewers', $blog?->reviewers->pluck('id')->toArray() ?? [])))>{{ $author->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>{{ __('Editors') }}</label>
                    <select name="editors[]" class="form-control select2 w-100" multiple="multiple" data-placeholder="{{ __('Select Editors') }}">
                        @foreach($authors as $author)
                            <option value="{{ $author->id }}" @selected(in_array($author->id, old('editors', $blog?->editors->pluck('id')->toArray() ?? [])))>{{ $author->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>{{ __('Read Time') }}</label>
                    <input type="text" name="read_time" class="form-control" value="{{ old('read_time', $blog?->read_time) }}" placeholder="8 min">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>{{ __('Accent Color') }}</label>
                    <input type="text" name="accent_color" class="form-control" value="{{ old('accent_color', $blog?->accent_color ?? '#B8FF35') }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>{{ __('Status') }}</label>
                    <select name="status" class="form-control">
                        <option value="published" @selected(old('status', $blog?->status ?? 'published') === 'published')>{{ __('Published') }}</option>
                        <option value="draft" @selected(old('status', $blog?->status) === 'draft')>{{ __('Draft') }}</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>{{ __('Published At') }}</label>
                    <input type="datetime-local" name="published_at" class="form-control" value="{{ old('published_at', $blog?->published_at?->format('Y-m-d\TH:i')) }}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{ __('Image') }}</label>
                    @if($blog?->image)
                        <img src="{{ $blog->image }}" alt="{{ $blog->title }}" style="max-height: 80px;" class="d-block mb-2">
                    @endif
                    <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                </div>
            </div>
            <div class="col-md-6 d-flex align-items-center">
                <input type="hidden" name="is_featured" value="0">
                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="is_featured" @checked(old('is_featured', $blog?->is_featured ?? false))>
                    <label class="form-check-label" for="is_featured">{{ __('Feature on homepage') }}</label>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>{{ __('Excerpt') }}</label>
                    <textarea name="excerpt" class="form-control" rows="3">{{ old('excerpt', $blog?->excerpt) }}</textarea>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>{{ __('Article Attributes') }}</label>
                    <input type="hidden" name="content_attributes" id="content-attributes-input" value='{!! $attributesJson !!}'>
                    <input type="hidden" name="content_attributes_touched" id="content-attributes-touched" value="0">
                    <div class="mb-2">
                        <small class="text-muted d-block mb-1">{{ __('Selected attributes') }}</small>
                        <div class="d-flex flex-wrap gap-2" id="selected-attributes"></div>
                        <small class="text-muted d-none" id="no-selected-attributes">{{ __('No attributes selected yet.') }}</small>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="attribute-presets"></div>
                    <input type="hidden" name="attribute_presets" id="attribute-presets-input" value='{!! $attributePresetsJson !!}'>
                    <input type="hidden" name="attribute_presets_touched" id="attribute-presets-touched" value="0">
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <small class="text-muted">{{ __('Manage always-shown attribute presets') }}</small>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-3" id="preset-manager-list"></div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <select class="form-control form-control-sm" id="preset-action" style="max-width: 180px;">
                                <option value="">{{ __('Actions') }}</option>
                                <option value="delete">{{ __('Delete attributes') }}</option>
                            </select>
                            <div class="d-flex flex-wrap align-items-center gap-2 d-none" id="preset-delete-controls">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="select-all-presets">{{ __('Select All') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-selected-presets">{{ __('Clear Selection') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="delete-selected-presets">{{ __('Delete Selected') }}</button>
                                <small class="text-muted" id="preset-selection-count">{{ __('0 selected') }}</small>
                            </div>
                        </div>
                        <div class="row align-items-end">
                            <div class="col-md-5">
                                <input type="text" id="preset-attribute-label" class="form-control" placeholder="{{ __('Preset label') }}">
                            </div>
                            <div class="col-md-2">
                                <input type="color" id="preset-attribute-color" class="form-control form-control-color" value="#B8FF35">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-primary w-100" id="add-preset-attribute">{{ __('Add Preset') }}</button>
                            </div>
                        </div>
                    </div>
                    <div class="row align-items-end">
                        <div class="col-md-5">
                            <input type="text" id="custom-attribute-label" class="form-control" placeholder="{{ __('Custom attribute or paper name') }}">
                        </div>
                        <div class="col-md-2">
                            <input type="color" id="custom-attribute-color" class="form-control form-control-color" value="#B8FF35">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-secondary w-100" id="add-custom-attribute">{{ __('Add') }}</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{ __('Meta Title') }}</label>
                    <input type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $blog?->meta_title) }}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>{{ __('Meta Description') }}</label>
                    <textarea name="meta_description" class="form-control" rows="3">{{ old('meta_description', $blog?->meta_description) }}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">{{ __('Article Content') }}</div>
    <div class="card-body mt-3">
        <ul class="nav nav-tabs" id="languageTabs" role="tablist">
            @foreach($languages as $lang)
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" href="#lang-{{ $lang->id }}">
                        {{ $lang->name }}
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="tab-content mt-3">
            @foreach($languages as $lang)
                @php
                    $isEnglish = $lang->id == 1;
                    $trans = $translations[$lang->id] ?? null;
                    $selectedTags = old("tags.$lang->id", $isEnglish ? ($blog?->tags ?? []) : ($trans?->tags ?? []));
                @endphp
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="lang-{{ $lang->id }}">
                    <input type="hidden" name="languages[]" value="{{ $lang->id }}">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>{{ __('Title') }} ({{ $lang->name }})</label>
                                <input type="text" name="title[{{ $lang->id }}]" class="form-control" value="{{ old("title.$lang->id", $isEnglish ? ($blog?->title ?? '') : ($trans?->title ?? '')) }}" @if($isEnglish) required @endif>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>{{ __('Tags') }} ({{ $lang->name }})</label>
                                <select name="tags[{{ $lang->id }}][]" data-tags="true" data-placeholder="{{ __('Tags') }}" data-allow-clear="true" class="select2 col-12 w-100" multiple="multiple">
                                    @foreach($selectedTags as $tag)
                                        <option selected value="{{ $tag }}">{{ $tag }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>{{ __('Description') }} ({{ $lang->name }})</label>
                                <textarea name="blog_description[{{ $lang->id }}]" class="tinymce_editor form-control" rows="8">{{ old("blog_description.$lang->id", $isEnglish ? ($blog?->description ?? '') : ($trans?->description ?? '')) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>{{ __('Article FAQs & Schema') }}</span>
        <button type="button" class="btn btn-sm btn-primary" id="add-faq-row">{{ __('Add FAQ') }}</button>
    </div>
    <div class="card-body mt-3">
        <input type="hidden" name="faqs_touched" id="faqs-touched" value="0">
        <p class="text-muted small mb-3">
            {{ __('Add article-specific FAQs here. Visible FAQs can render on the article page, and schema-enabled FAQs are included in FAQPage structured data.') }}
        </p>
        <div id="faq-list" class="d-flex flex-column gap-3">
            @foreach($faqItems as $index => $faq)
                <div class="border rounded p-3 faq-row">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <strong>{{ __('FAQ') }} <span class="faq-number">{{ $index + 1 }}</span></strong>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-faq-row">{{ __('Remove') }}</button>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>{{ __('Question') }}</label>
                                <input type="text" class="form-control faq-field" data-name="question" name="faqs[{{ $index }}][question]" value="{{ $faq['question'] ?? '' }}" placeholder="{{ __('Question shown on article') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>{{ __('Sort Order') }}</label>
                                <input type="number" class="form-control faq-field" data-name="sort_order" name="faqs[{{ $index }}][sort_order]" min="0" value="{{ $faq['sort_order'] ?? $index + 1 }}">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>{{ __('Answer') }}</label>
                                <textarea class="form-control faq-field" data-name="answer" name="faqs[{{ $index }}][answer]" rows="3" placeholder="{{ __('Answer shown on article') }}">{{ $faq['answer'] ?? '' }}</textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <input type="hidden" class="faq-field" data-name="is_visible" name="faqs[{{ $index }}][is_visible]" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input faq-field" data-name="is_visible" type="checkbox" name="faqs[{{ $index }}][is_visible]" value="1" @checked((int) ($faq['is_visible'] ?? 1) === 1)>
                                <label class="form-check-label">{{ __('Show on article page') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <input type="hidden" class="faq-field" data-name="include_in_schema" name="faqs[{{ $index }}][include_in_schema]" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input faq-field" data-name="include_in_schema" type="checkbox" name="faqs[{{ $index }}][include_in_schema]" value="1" @checked((int) ($faq['include_in_schema'] ?? 1) === 1)>
                                <label class="form-check-label">{{ __('Include in FAQ schema') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6 mt-3">
                            <div class="form-group">
                                <label>{{ __('Schema Question Override') }}</label>
                                <input type="text" class="form-control faq-field" data-name="schema_question" name="faqs[{{ $index }}][schema_question]" value="{{ $faq['schema_question'] ?? '' }}" placeholder="{{ __('Optional') }}">
                            </div>
                        </div>
                        <div class="col-md-6 mt-3">
                            <div class="form-group">
                                <label>{{ __('Schema Answer Override') }}</label>
                                <textarea class="form-control faq-field" data-name="schema_answer" name="faqs[{{ $index }}][schema_answer]" rows="2" placeholder="{{ __('Optional') }}">{{ $faq['schema_answer'] ?? '' }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="text-muted small mt-3 {{ count($faqItems) ? 'd-none' : '' }}" id="no-faqs-message">
            {{ __('No FAQs added yet.') }}
        </p>
        <div class="d-flex justify-content-end mt-3">
            <button type="button" class="btn btn-primary" id="add-faq-row-bottom">{{ __('Add More FAQ') }}</button>
        </div>
    </div>
</div>

@section('script')
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const customTinyMceCSS = `
                body { font-family: 'DM Sans', sans-serif; background: #07070F; color: #E8E8F0; }
                .custom-block { border: 1px solid #1E1E30; background: #0F0F1A; border-radius: 12px; padding: 1.5rem; margin: 2rem 0; position: relative; font-family: 'DM Sans', sans-serif; color: #E8E8F0; }
                .custom-block p:last-child { margin-bottom: 0; }
                .custom-block::before { display: flex; align-items: center; font-family: 'DM Mono', monospace; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.1em; margin-bottom: 1rem; text-transform: uppercase; }
                .block-key-takeaway { border-color: rgba(184, 255, 53, 0.3); } .block-key-takeaway::before { content: "• KEY TAKEAWAY"; color: #B8FF35; }
                .block-quote { border: none; border-left: 3px solid #9B59B6; background: transparent; padding: 1rem 1.5rem; border-radius: 0; font-style: italic; font-size: 1.1rem; color: #E8E8F0; } .block-quote::before { content: "”"; font-family: 'Syne', sans-serif; font-size: 3rem; line-height: 0; color: #9B59B6; opacity: 0.3; position: absolute; top: 10px; left: -20px; }
                .block-suggestion { border-color: rgba(0, 255, 204, 0.3); } .block-suggestion::before { content: "✦ SUGGESTION"; color: #00FFCC; }
                .block-so-what { border-color: rgba(255, 159, 67, 0.3); } .block-so-what::before { content: "🤔 SO WHAT?"; color: #FF9F43; }
                .block-tip { border-color: rgba(241, 196, 15, 0.3); } .block-tip::before { content: "💡 TIP"; color: #F1C40F; }
                .block-definition { border-color: rgba(74, 74, 255, 0.3); } .block-definition::before { content: "📖 DEFINITION"; color: #4A4AFF; }
                .block-myth { border-color: rgba(255, 107, 107, 0.3); } .block-myth::before { content: "✖ MYTH"; color: #FF6B6B; }
                .block-problem { border-color: rgba(231, 76, 60, 0.3); } .block-problem::before { content: "⚠ PROBLEM"; color: #E74C3C; }
                .block-equation { border-color: rgba(0, 255, 136, 0.45); background: #0D0D0D; box-shadow: inset 0 0 0 1px rgba(0, 255, 136, 0.08); color: #00FF88; font-family: 'DM Mono', monospace; overflow-x: auto; }
                .block-equation::before { content: ""; display: none; }
                .block-equation, .block-equation p, .block-equation div, .block-equation span, .block-equation code, .block-equation pre { color: #00FF88 !important; font-family: 'DM Mono', monospace !important; }
                .block-equation p, .block-equation div { line-height: 1.8; }
            `;

            tinymce.init({
                selector: '.tinymce_editor',
                height: 440,
                menubar: false,
                plugins: 'lists link table code wordcount',
                toolbar: 'undo redo | styles | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist | link table | equationBlock | removeformat | code',
                formats: {
                    equationBlock: { block: 'div', classes: 'custom-block block-equation', wrapper: true }
                },
                style_formats: [
                    { title: 'Headers', items: [
                        { title: 'Heading 2', format: 'h2' },
                        { title: 'Heading 3', format: 'h3' },
                        { title: 'Heading 4', format: 'h4' }
                    ]},
                    { title: 'Custom Blocks', items: [
                        { title: 'Key Takeaway', block: 'div', classes: 'custom-block block-key-takeaway', wrapper: true },
                        { title: 'Quote', block: 'div', classes: 'custom-block block-quote', wrapper: true },
                        { title: 'Suggestion', block: 'div', classes: 'custom-block block-suggestion', wrapper: true },
                        { title: 'So what?', block: 'div', classes: 'custom-block block-so-what', wrapper: true },
                        { title: 'Tip', block: 'div', classes: 'custom-block block-tip', wrapper: true },
                        { title: 'Definition', block: 'div', classes: 'custom-block block-definition', wrapper: true },
                        { title: 'Myth', block: 'div', classes: 'custom-block block-myth', wrapper: true },
                        { title: 'Problem', block: 'div', classes: 'custom-block block-problem', wrapper: true },
                        { title: 'Equation / Formula', format: 'equationBlock' }
                    ]}
                ],
                content_style: customTinyMceCSS,
                setup: function (editor) {
                    editor.ui.registry.addButton('equationBlock', {
                        text: 'Equation',
                        tooltip: 'Equation / Formula',
                        onAction: function () {
                            editor.formatter.toggle('equationBlock');
                            editor.save();
                        }
                    });

                    editor.on("change keyup", function () {
                        editor.save();
                    });
                }
            });

            const attributesInput = document.getElementById('content-attributes-input');
            const attributesTouched = document.getElementById('content-attributes-touched');
            const presetsInput = document.getElementById('attribute-presets-input');
            const presetsTouched = document.getElementById('attribute-presets-touched');
            const presetsWrap = document.getElementById('attribute-presets');
            const presetManagerList = document.getElementById('preset-manager-list');
            const presetAttributeLabel = document.getElementById('preset-attribute-label');
            const presetAttributeColor = document.getElementById('preset-attribute-color');
            const addPresetAttribute = document.getElementById('add-preset-attribute');
            const selectedWrap = document.getElementById('selected-attributes');
            const noSelectedAttributes = document.getElementById('no-selected-attributes');
            const presetAction = document.getElementById('preset-action');
            const presetDeleteControls = document.getElementById('preset-delete-controls');
            const selectAllPresets = document.getElementById('select-all-presets');
            const clearSelectedPresets = document.getElementById('clear-selected-presets');
            const deleteSelectedPresets = document.getElementById('delete-selected-presets');
            const presetSelectionCount = document.getElementById('preset-selection-count');
            const customLabel = document.getElementById('custom-attribute-label');
            const customColor = document.getElementById('custom-attribute-color');
            const addCustom = document.getElementById('add-custom-attribute');
            function parseStoredAttributes(value) {
                try {
                    let parsed = JSON.parse(value || '[]');

                    if (typeof parsed === 'string') {
                        parsed = JSON.parse(parsed || '[]');
                    }

                    return Array.isArray(parsed) ? parsed : null;
                } catch (e) {
                    return null;
                }
            }

            const parsedAttributes = parseStoredAttributes(attributesInput.value);
            let selectedAttributes = parsedAttributes || [];
            let presets = parseStoredAttributes(presetsInput.value) || [];
            let selectedPresetKeysForDelete = new Set();

            function markAttributesTouched() {
                attributesTouched.value = '1';
            }

            function markPresetsTouched() {
                presetsTouched.value = '1';
            }

            function attributeKey(label) {
                return (label || '').trim().toLowerCase();
            }

            function cleanAttributeColor(color) {
                return /^#[0-9a-fA-F]{6}$/.test(color || '') ? color : '#B8FF35';
            }

            function normalizeAttributeList(attributes) {
                const normalized = new Map();

                (Array.isArray(attributes) ? attributes : []).forEach((attribute) => {
                    if (!attribute) return;

                    const label = typeof attribute === 'string'
                        ? attribute.trim()
                        : (attribute.label || '').trim();

                    if (!label) return;

                    normalized.set(attributeKey(label), {
                        label,
                        color: cleanAttributeColor(typeof attribute === 'string' ? '#B8FF35' : attribute.color),
                    });
                });

                return Array.from(normalized.values());
            }

            function syncAttributes({ touched = false } = {}) {
                if (touched) {
                    markAttributesTouched();
                }

                selectedAttributes = normalizeAttributeList(selectedAttributes);
                attributesInput.value = JSON.stringify(selectedAttributes);
                selectedWrap.innerHTML = '';
                noSelectedAttributes.classList.toggle('d-none', selectedAttributes.length > 0);
                selectedAttributes.forEach((attribute, index) => {
                    const chip = document.createElement('button');
                    chip.type = 'button';
                    chip.className = 'btn btn-sm';
                    chip.style.border = `1px solid ${attribute.color}`;
                    chip.style.color = attribute.color;
                    chip.style.background = `${attribute.color}18`;
                    chip.textContent = `${attribute.label} \u00D7`;
                    chip.addEventListener('click', () => {
                        selectedAttributes.splice(index, 1);
                        syncAttributes({ touched: true });
                    });
                    selectedWrap.appendChild(chip);
                });
            }

            function addAttribute(label, color) {
                const cleanLabel = (label || '').trim();
                if (!cleanLabel) return;

                const key = attributeKey(cleanLabel);
                const nextAttribute = { label: cleanLabel, color: cleanAttributeColor(color) };
                const existingIndex = selectedAttributes.findIndex((attribute) => attributeKey(attribute.label) === key);

                if (existingIndex >= 0) {
                    selectedAttributes[existingIndex] = nextAttribute;
                } else {
                    selectedAttributes.push(nextAttribute);
                }

                syncAttributes({ touched: true });
            }

            function isPresetDeleteMode() {
                return presetAction.value === 'delete';
            }

            function pruneDeletedPresetSelection() {
                const availableKeys = new Set(presets.map((preset) => attributeKey(preset.label)));
                selectedPresetKeysForDelete = new Set(
                    Array.from(selectedPresetKeysForDelete).filter((key) => availableKeys.has(key))
                );
            }

            function updatePresetActionState() {
                const deleteMode = isPresetDeleteMode();
                const selectedCount = selectedPresetKeysForDelete.size;

                presetDeleteControls.classList.toggle('d-none', !deleteMode);
                presetSelectionCount.textContent = `${selectedCount} selected`;
                selectAllPresets.disabled = !deleteMode || presets.length === 0 || selectedCount === presets.length;
                clearSelectedPresets.disabled = !deleteMode || selectedCount === 0;
                deleteSelectedPresets.disabled = !deleteMode || selectedCount === 0;
            }

            function syncPresets({ touched = false } = {}) {
                if (touched) {
                    markPresetsTouched();
                }

                presets = normalizeAttributeList(presets);
                pruneDeletedPresetSelection();
                presetsInput.value = JSON.stringify(presets);
                presetsWrap.innerHTML = '';
                presetManagerList.innerHTML = '';

                presets.forEach((preset) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-sm btn-light';
                    button.style.borderColor = preset.color;
                    button.style.color = preset.color;
                    button.textContent = preset.label;
                    button.addEventListener('click', () => addAttribute(preset.label, preset.color));
                    presetsWrap.appendChild(button);

                    const key = attributeKey(preset.label);
                    const isSelectedForDelete = selectedPresetKeysForDelete.has(key);
                    const managerChip = document.createElement('button');
                    managerChip.type = 'button';
                    managerChip.className = 'btn btn-sm d-inline-flex align-items-center gap-1';
                    managerChip.style.border = `1px solid ${isSelectedForDelete ? '#dc3545' : preset.color}`;
                    managerChip.style.color = preset.color;
                    managerChip.style.background = isSelectedForDelete ? '#dc354522' : `${preset.color}18`;

                    if (isPresetDeleteMode()) {
                        const checkbox = document.createElement('span');
                        checkbox.className = 'd-inline-flex align-items-center justify-content-center';
                        checkbox.style.width = '14px';
                        checkbox.style.height = '14px';
                        checkbox.style.border = `1px solid ${isSelectedForDelete ? '#dc3545' : preset.color}`;
                        checkbox.style.borderRadius = '3px';
                        checkbox.style.fontSize = '10px';
                        checkbox.style.lineHeight = '1';
                        checkbox.textContent = isSelectedForDelete ? '\u2713' : '';
                        managerChip.appendChild(checkbox);
                    }

                    managerChip.appendChild(document.createTextNode(preset.label));
                    managerChip.addEventListener('click', () => {
                        if (!isPresetDeleteMode()) return;

                        if (selectedPresetKeysForDelete.has(key)) {
                            selectedPresetKeysForDelete.delete(key);
                        } else {
                            selectedPresetKeysForDelete.add(key);
                        }

                        syncPresets();
                    });
                    presetManagerList.appendChild(managerChip);
                });

                updatePresetActionState();
            }

            function addPreset(label, color) {
                const cleanLabel = (label || '').trim();
                if (!cleanLabel) return;

                const key = attributeKey(cleanLabel);
                const nextPreset = { label: cleanLabel, color: cleanAttributeColor(color) };
                const existingIndex = presets.findIndex((preset) => attributeKey(preset.label) === key);

                if (existingIndex >= 0) {
                    presets[existingIndex] = nextPreset;
                } else {
                    presets.push(nextPreset);
                }

                syncPresets({ touched: true });
            }

            presetAction.addEventListener('change', () => {
                if (!isPresetDeleteMode()) {
                    selectedPresetKeysForDelete.clear();
                }

                syncPresets();
            });

            selectAllPresets.addEventListener('click', () => {
                selectedPresetKeysForDelete = new Set(presets.map((preset) => attributeKey(preset.label)));
                syncPresets();
            });

            clearSelectedPresets.addEventListener('click', () => {
                selectedPresetKeysForDelete.clear();
                syncPresets();
            });

            deleteSelectedPresets.addEventListener('click', () => {
                if (selectedPresetKeysForDelete.size === 0) return;

                presets = presets.filter((preset) => !selectedPresetKeysForDelete.has(attributeKey(preset.label)));
                selectedPresetKeysForDelete.clear();
                syncPresets({ touched: true });
            });

            addCustom.addEventListener('click', () => {
                addAttribute(customLabel.value, customColor.value);
                customLabel.value = '';
            });

            addPresetAttribute.addEventListener('click', () => {
                addPreset(presetAttributeLabel.value, presetAttributeColor.value);
                presetAttributeLabel.value = '';
            });

            if (parsedAttributes !== null) {
                syncAttributes();
            }

            syncPresets();

            const faqList = document.getElementById('faq-list');
            const addFaqRow = document.getElementById('add-faq-row');
            const addFaqRowBottom = document.getElementById('add-faq-row-bottom');
            const faqsTouched = document.getElementById('faqs-touched');
            const noFaqsMessage = document.getElementById('no-faqs-message');

            function markFaqsTouched() {
                faqsTouched.value = '1';
            }

            function updateFaqIndexes() {
                const rows = faqList.querySelectorAll('.faq-row');
                noFaqsMessage.classList.toggle('d-none', rows.length > 0);

                rows.forEach((row, index) => {
                    row.querySelector('.faq-number').textContent = index + 1;
                    row.querySelectorAll('.faq-field').forEach((field) => {
                        field.name = `faqs[${index}][${field.dataset.name}]`;
                    });
                });
            }

            function bindFaqRow(row) {
                row.querySelector('.remove-faq-row').addEventListener('click', () => {
                    row.remove();
                    markFaqsTouched();
                    updateFaqIndexes();
                });

                row.querySelectorAll('.faq-field').forEach((field) => {
                    field.addEventListener('input', markFaqsTouched);
                    field.addEventListener('change', markFaqsTouched);
                });
            }

            function createFaqRow() {
                const row = document.createElement('div');
                row.className = 'border rounded p-3 faq-row';
                row.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <strong>{{ __('FAQ') }} <span class="faq-number"></span></strong>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-faq-row">{{ __('Remove') }}</button>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>{{ __('Question') }}</label>
                                <input type="text" class="form-control faq-field" data-name="question" placeholder="{{ __('Question shown on article') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>{{ __('Sort Order') }}</label>
                                <input type="number" class="form-control faq-field" data-name="sort_order" min="0" value="0">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>{{ __('Answer') }}</label>
                                <textarea class="form-control faq-field" data-name="answer" rows="3" placeholder="{{ __('Answer shown on article') }}"></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <input type="hidden" class="faq-field" data-name="is_visible" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input faq-field" data-name="is_visible" type="checkbox" value="1" checked>
                                <label class="form-check-label">{{ __('Show on article page') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <input type="hidden" class="faq-field" data-name="include_in_schema" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input faq-field" data-name="include_in_schema" type="checkbox" value="1" checked>
                                <label class="form-check-label">{{ __('Include in FAQ schema') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6 mt-3">
                            <div class="form-group">
                                <label>{{ __('Schema Question Override') }}</label>
                                <input type="text" class="form-control faq-field" data-name="schema_question" placeholder="{{ __('Optional') }}">
                            </div>
                        </div>
                        <div class="col-md-6 mt-3">
                            <div class="form-group">
                                <label>{{ __('Schema Answer Override') }}</label>
                                <textarea class="form-control faq-field" data-name="schema_answer" rows="2" placeholder="{{ __('Optional') }}"></textarea>
                            </div>
                        </div>
                    </div>
                `;

                faqList.appendChild(row);
                bindFaqRow(row);
                updateFaqIndexes();
                return row;
            }

            faqList.querySelectorAll('.faq-row').forEach(bindFaqRow);
            updateFaqIndexes();

            function addFaq() {
                const row = createFaqRow();
                markFaqsTouched();
                row.querySelector('[data-name="question"]')?.focus();
            }

            addFaqRow.addEventListener('click', addFaq);
            addFaqRowBottom.addEventListener('click', addFaq);
        });
    </script>
@endsection
