<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Blog;
use App\Models\BlogAttributePreset;
use App\Models\BlogFaq;
use App\Models\BlogTranslation;
use App\Models\Category;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ArticleShareLinkService;
use App\Services\PublicContentCacheService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class BlogController extends Controller
{
    private string $uploadFolder;

    public function __construct()
    {
        $this->uploadFolder = 'blog';
    }

    public function index()
    {
        ResponseService::noAnyPermissionThenRedirect(['blog-list', 'blog-create', 'blog-delete', 'blog-update']);
        $languages = CachingService::getLanguages()->values();

        return view('blog.index', compact('languages'));
    }

    public function create()
    {
        ResponseService::noPermissionThenRedirect('blog-create');
        $authors = Author::where('status', true)->orderBy('name')->get();
        $categories = Category::where('status', true)->orderBy('sequence')->orderBy('name')->get();
        $languages = CachingService::getLanguages()->values();
        $attributePresets = $this->attributePresets();

        return view('blog.create', compact('authors', 'categories', 'languages', 'attributePresets'));
    }

    public function store(Request $request)
    {
        ResponseService::noPermissionThenSendJson('blog-create');

        $request->validate($this->rules());

        try {
            $description = $request->input('blog_description.1') ?? '';
            $status = $request->input('status', 'published');
            $category = $request->filled('category_id') ? Category::find($request->input('category_id')) : null;
            $data = [
                'category_id' => $category?->id,
                'sort_order' => (int) $request->input('sort_order', 0),
                'author_id' => $request->input('author_id'),
                'title' => $request->input('title.1'),
                'slug' => HelperService::generateUniqueSlug(new Blog(), $request->input('slug') ?: $request->input('title.1')),
                'description' => $description,
                'excerpt' => $request->input('excerpt') ?: Str::limit(strip_tags($description), 180),
                'tags' => implode(',', $request->input('tags.1') ?? []),
                'category' => $category?->name,
                'read_time' => $request->input('read_time') ?: $this->estimateReadTime($description),
                'accent_color' => $request->input('accent_color') ?: '#B8FF35',
                'content_attributes' => $this->normalizeAttributes($request->input('content_attributes')),
                'is_featured' => $request->boolean('is_featured'),
                'status' => $status,
                'published_at' => $request->input('published_at') ?: ($status === 'published' ? now() : null),
                'updated_on' => $request->input('updated_on') ?: null,
                'updated_by_author_id' => $request->input('updated_by_author_id') ?: null,
                'meta_title' => $request->input('meta_title'),
                'meta_description' => $request->input('meta_description'),
            ];

            if ($request->hasFile('image')) {
                $uploadedImage = FileService::compressAndUpload($request->file('image'), $this->uploadFolder);

                if (! $uploadedImage) {
                    return ResponseService::errorRedirectResponse('Image upload failed. Please try again.');
                }

                $data['image'] = $uploadedImage;
            }

            $blog = Blog::create($data);
            $this->syncAttributePresets($request);
            $this->syncContributors($blog, $request);
            $this->syncArticleFaqs($blog, $request);
            $this->saveTranslations($request, $blog);
            app(ArticleShareLinkService::class)->syncForBlog($blog);
            PublicContentCacheService::invalidate();

            return redirect(route('blog.index'))->with('success', trans('Blog Added Successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th, 'BlogController->store');

            return ResponseService::errorRedirectResponse('Something Went Wrong');
        }
    }

    public function show(Request $request)
    {
        ResponseService::noPermissionThenSendJson('blog-list');

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');

        $allowedSorts = ['id', 'title', 'slug', 'category', 'sort_order', 'status', 'is_featured', 'published_at', 'created_at', 'updated_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }

        $sql = Blog::with(['author:id,name', 'seriesCategory:id,name,series_title'])->search($request->search);

        $total = $sql->count();
        $result = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $rows = [];
        $no = $offset + 1;
        foreach ($result as $row) {
            $operate = '';
            if (Auth::user()->can('blog-update')) {
                $operate .= BootstrapTableService::editButton(route('blog.edit', $row->id));
            }
            if (Auth::user()->can('blog-delete')) {
                $operate .= BootstrapTableService::deleteButton(route('blog.destroy', $row->id));
            }

            $rows[] = [
                'no' => $no++,
                'id' => $row->id,
                'title' => $row->title,
                'slug' => $row->slug,
                'author' => $row->author?->name ?? '-',
                'category' => $row->seriesCategory?->series_title ?: ($row->seriesCategory?->name ?? $row->category ?? '-'),
                'sort_order' => $row->sort_order,
                'description' => Str::limit(strip_tags($row->description), 160),
                'image' => $row->image,
                'tags' => implode(', ', $row->tags ?? []),
                'read_time' => $row->read_time,
                'status' => ucfirst($row->status ?? 'draft'),
                'is_featured' => $row->is_featured ? 'Yes' : 'No',
                'published_at' => $row->published_at ? Carbon::parse($row->published_at)->format('d-m-Y H:i') : '-',
                'created_at' => Carbon::parse($row->created_at)->format('d-m-Y H:i'),
                'operate' => $operate,
            ];
        }

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    public function edit($id)
    {
        ResponseService::noPermissionThenRedirect('blog-update');

        $blog = Blog::with(['translations', 'seriesCategory', 'additionalAuthors', 'reviewers', 'editors', 'faqs', 'updatedByAuthor'])->findOrFail($id);
        $authors = Author::where('status', true)->orderBy('name')->get();
        $categories = Category::where('status', true)->orderBy('sequence')->orderBy('name')->get();
        $languages = CachingService::getLanguages()->values();
        $translations = $blog->translations->keyBy('language_id');
        $attributePresets = $this->attributePresets();

        return view('blog.edit', compact('blog', 'authors', 'categories', 'languages', 'translations', 'attributePresets'));
    }

    public function update(Request $request, $id)
    {
        ResponseService::noPermissionThenSendJson('blog-update');

        $request->validate($this->rules($id));

        try {
            $blog = Blog::findOrFail($id);
            $description = $request->input('blog_description.1') ?? '';
            $status = $request->input('status', 'published');
            $category = $request->filled('category_id') ? Category::find($request->input('category_id')) : null;

            $data = [
                'category_id' => $category?->id,
                'sort_order' => (int) $request->input('sort_order', 0),
                'author_id' => $request->input('author_id'),
                'title' => $request->input('title.1'),
                'slug' => HelperService::generateUniqueSlug(new Blog(), $request->input('slug') ?: $request->input('title.1'), $blog->id),
                'description' => $description,
                'excerpt' => $request->input('excerpt') ?: Str::limit(strip_tags($description), 180),
                'tags' => implode(',', $request->input('tags.1') ?? []),
                'category' => $category?->name,
                'read_time' => $request->input('read_time') ?: $this->estimateReadTime($description),
                'accent_color' => $request->input('accent_color') ?: '#B8FF35',
                'content_attributes' => $this->normalizeAttributes(
                    $request->input('content_attributes'),
                    $blog->content_attributes ?? [],
                    $request->boolean('content_attributes_touched')
                ),
                'status' => $status,
                'published_at' => $request->input('published_at') ?: ($status === 'published' ? ($blog->published_at ?: now()) : null),
                'updated_on' => $request->input('updated_on') ?: null,
                'updated_by_author_id' => $request->input('updated_by_author_id') ?: null,
                'meta_title' => $request->input('meta_title'),
                'meta_description' => $request->input('meta_description'),
            ];

            if ($request->hasFile('image')) {
                $uploadedImage = FileService::compressAndReplace($request->file('image'), $this->uploadFolder, $blog->getRawOriginal('image'));

                if (! $uploadedImage) {
                    return ResponseService::errorRedirectResponse('Image upload failed. Please try again.');
                }

                $data['image'] = $uploadedImage;
            }

            if ($request->has('is_featured')) {
                $data['is_featured'] = $request->boolean('is_featured');
            }

            $blog->update($data);
            $this->syncAttributePresets($request);
            $this->syncContributors($blog, $request);
            $this->syncArticleFaqs($blog, $request);
            $this->saveTranslations($request, $blog);
            app(ArticleShareLinkService::class)->syncForBlog($blog);
            PublicContentCacheService::invalidate();

            return redirect(route('blog.index'))->with('success', trans('Blog Updated Successfully'));
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th, 'BlogController->update');

            return ResponseService::errorRedirectResponse('Something Went Wrong');
        }
    }

    public function destroy($id)
    {
        ResponseService::noPermissionThenSendJson('blog-delete');

        try {
            $blog = Blog::findOrFail($id);
            FileService::delete($blog->getRawOriginal('image'));
            $blog->translations()->delete();
            $blog->delete();
            PublicContentCacheService::invalidate();
            ResponseService::successResponse('Blog delete successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th);
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function uploadEditorImage(Request $request)
    {
        ResponseService::noAnyPermissionThenSendJson(['blog-create', 'blog-update']);

        $request->validate([
            'image' => ['required', 'mimes:jpg,jpeg,png,webp', 'max:7168'],
        ]);

        $path = FileService::compressAndUpload($request->file('image'), 'blog/editor');

        if (! $path) {
            return response()->json(['message' => 'Image upload failed'], 422);
        }

        $url = url(Storage::url($path));

        return response()->json([
            'location' => $url,
            'url' => $url,
        ]);
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'title.1' => ['required', 'string', 'max:512'],
            'slug' => ['nullable', 'string', 'max:512'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'author_id' => ['nullable', 'exists:authors,id'],
            'updated_on' => ['nullable', 'date'],
            'updated_by_author_id' => ['nullable', 'exists:authors,id'],
            'image' => ['nullable', 'mimes:jpg,jpeg,png,webp', 'max:7168'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'content_attributes' => ['nullable'],
            'content_attributes_touched' => ['nullable', 'boolean'],
            'attribute_presets' => ['nullable'],
            'attribute_presets_touched' => ['nullable', 'boolean'],
            'contributors_touched' => ['nullable', 'boolean'],
            'faqs_touched' => ['nullable', 'boolean'],
            'faqs' => ['nullable', 'array'],
            'faqs.*.question' => ['nullable', 'string', 'max:512'],
            'faqs.*.answer' => ['nullable', 'string'],
            'faqs.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'faqs.*.is_visible' => ['nullable', 'boolean'],
            'faqs.*.include_in_schema' => ['nullable', 'boolean'],
            'faqs.*.schema_question' => ['nullable', 'string', 'max:512'],
            'faqs.*.schema_answer' => ['nullable', 'string'],
            'additional_authors' => ['nullable', 'array'],
            'additional_authors.*' => ['exists:authors,id'],
            'reviewers' => ['nullable', 'array'],
            'reviewers.*' => ['exists:authors,id'],
            'editors' => ['nullable', 'array'],
            'editors.*' => ['exists:authors,id'],
        ];
    }

    private function syncContributors(Blog $blog, Request $request): void
    {
        $contributorFieldsPresent = $request->has('additional_authors')
            || $request->has('reviewers')
            || $request->has('editors')
            || $request->boolean('contributors_touched');

        if (! $contributorFieldsPresent) {
            return;
        }

        $rows = [];
        foreach ($request->input('additional_authors', []) as $authorId) {
            $rows[] = ['author_id' => (int) $authorId, 'contribution_type' => 'author'];
        }
        foreach ($request->input('reviewers', []) as $reviewerId) {
            $rows[] = ['author_id' => (int) $reviewerId, 'contribution_type' => 'reviewer'];
        }
        foreach ($request->input('editors', []) as $editorId) {
            $rows[] = ['author_id' => (int) $editorId, 'contribution_type' => 'editor'];
        }

        DB::table('blog_contributors')->where('blog_id', $blog->id)->delete();

        foreach (collect($rows)->unique(fn ($row) => $row['author_id'].'-'.$row['contribution_type']) as $row) {
            DB::table('blog_contributors')->insert([
                'blog_id' => $blog->id,
                'author_id' => $row['author_id'],
                'contribution_type' => $row['contribution_type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function saveTranslations(Request $request, Blog $blog): void
    {
        foreach ($request->input('languages', []) as $langId) {
            if ((int) $langId === 1) {
                continue;
            }

            $translatedTitle = $request->input("title.$langId");
            $translatedDesc = $request->input("blog_description.$langId");
            $translatedTags = $request->input("tags.$langId", []);

            if ($translatedTitle || $translatedDesc || ! empty($translatedTags)) {
                BlogTranslation::updateOrCreate(
                    ['blog_id' => $blog->id, 'language_id' => $langId],
                    [
                        'title' => $translatedTitle,
                        'description' => $translatedDesc,
                        'tags' => implode(',', $translatedTags),
                    ]
                );
            }
        }
    }

    private function attributePresets(): array
    {
        return BlogAttributePreset::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['label', 'color'])
            ->map(fn (BlogAttributePreset $preset) => [
                'label' => $preset->label,
                'color' => $this->validHexColor($preset->color) ? $preset->color : '#B8FF35',
            ])
            ->values()
            ->all();
    }

    private function syncAttributePresets(Request $request): void
    {
        if (! $request->boolean('attribute_presets_touched') || ! $request->has('attribute_presets')) {
            return;
        }

        $presets = $this->normalizeAttributes($request->input('attribute_presets'));

        BlogAttributePreset::query()->delete();

        foreach ($presets as $index => $preset) {
            BlogAttributePreset::create([
                'label' => $preset['label'],
                'color' => $preset['color'],
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }
    }

    private function syncArticleFaqs(Blog $blog, Request $request): void
    {
        $hasFaqContent = collect($request->input('faqs', []))
            ->contains(function ($faq) {
                $question = trim((string) ($faq['question'] ?? ''));
                $answer = trim((string) ($faq['answer'] ?? ''));
                return $question !== '' || $answer !== '';
            });

        if (! $hasFaqContent && ! $request->boolean('faqs_touched')) {
            return;
        }

        $rows = collect($request->input('faqs', []))
            ->map(function ($faq, $index) {
                $question = trim((string) ($faq['question'] ?? ''));
                $answer = $this->normalizeFaqAnswer($faq['answer'] ?? '');

                if ($question === '' || $answer === '') {
                    return null;
                }

                return [
                    'question' => $question,
                    'answer' => $answer,
                    'sort_order' => (int) ($faq['sort_order'] ?? $index + 1),
                    'is_visible' => filter_var($faq['is_visible'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'include_in_schema' => filter_var($faq['include_in_schema'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'schema_question' => trim((string) ($faq['schema_question'] ?? '')) ?: null,
                    'schema_answer' => trim((string) ($faq['schema_answer'] ?? '')) ?: null,
                    'options' => null,
                ];
            })
            ->filter()
            ->sortBy('sort_order')
            ->values();

        $blog->faqs()->delete();

        $rows->each(function (array $row) use ($blog) {
            $blog->faqs()->create($row);
        });
    }

    private function normalizeFaqAnswer(?string $answer): string
    {
        $answer = trim((string) $answer);

        if ($answer === '') {
            return '';
        }

        if (preg_match('/<(p|div|ul|ol|li|blockquote|table|h[1-6]|br)\b/i', $answer)) {
            return $answer;
        }

        $paragraphs = preg_split('/\R{2,}/u', str_replace(["\r\n", "\r"], "\n", $answer)) ?: [];

        return collect($paragraphs)
            ->map(function (string $paragraph) {
                $paragraph = trim($paragraph);

                if ($paragraph === '') {
                    return null;
                }

                $escaped = htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                return '<p>'.nl2br($escaped, false).'</p>';
            })
            ->filter()
            ->implode('');
    }

    private function estimateReadTime(?string $html): string
    {
        $words = str_word_count(strip_tags($html ?? ''));
        $minutes = max(1, (int) ceil($words / 220));

        return $minutes.' min';
    }

    private function normalizeAttributes($attributes, ?array $fallback = null, bool $touched = true): array
    {
        if ($attributes === null) {
            return $fallback ?? [];
        }

        if (is_string($attributes)) {
            $attributes = trim($attributes);

            if ($attributes === '') {
                return $fallback ?? [];
            }

            $decoded = json_decode($attributes, true);

            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            if (! is_array($decoded)) {
                return $fallback ?? [];
            }

            $attributes = $decoded;
        }

        if (! is_array($attributes)) {
            return $fallback ?? [];
        }

        $normalized = collect($attributes)
            ->map(function ($attribute) {
                if (is_string($attribute)) {
                    $attribute = ['label' => $attribute, 'color' => '#B8FF35'];
                }

                if (! is_array($attribute)) {
                    return null;
                }

                $label = trim((string) ($attribute['label'] ?? ''));
                if ($label === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'color' => $this->validHexColor($attribute['color'] ?? '')
                        ? $attribute['color']
                        : '#B8FF35',
                ];
            })
            ->filter()
            ->reduce(function (array $carry, array $attribute) {
                $carry[strtolower($attribute['label'])] = $attribute;

                return $carry;
            }, []);

        $normalized = array_values($normalized);

        if (! $touched && empty($normalized) && ! empty($fallback)) {
            return $fallback;
        }

        return $normalized;
    }

    private function validHexColor(?string $color): bool
    {
        return is_string($color) && (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $color);
    }
}
