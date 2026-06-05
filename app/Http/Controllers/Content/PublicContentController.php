<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Blog;
use App\Models\Category;
use App\Models\CompanyPage;
use App\Models\ContactUs;

use App\Models\NewsletterSubscriber;
use App\Models\SearchQuery;
use App\Models\Setting;
use App\Services\ArticleShareLinkService;
use App\Services\PublicContentCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class PublicContentController extends Controller
{
    private const SHARE_PLATFORMS = ['facebook', 'instagram', 'tiktok', 'whatsapp', 'link'];

    public function settings()
    {
        return $this->cachedJson('settings', function () {
            $settings = $this->settingValues([
                'website_url',
                'company_name',
                'company_email',
                'google_site_verification',
                'gtm_container_id',
                'default_share_thumbnail',
            ]);
            $siteUrl = rtrim($settings['website_url'] ?: 'https://physicsfundamental.org', '/');
            $domain = parse_url($siteUrl, PHP_URL_HOST) ?: 'physicsfundamental.org';

            return [
                'error' => false,
                'data' => [
                    'brand_name' => $settings['company_name'] ?: 'Physics Fundamentals',
                    'domain' => preg_replace('/^www\./', '', $domain),
                    'site_url' => $siteUrl,
                    'contact_email' => $settings['company_email'] ?: 'hello@physicsfundamental.org',
                    'google_site_verification' => $settings['google_site_verification'] ?: config('services.google.site_verification'),
                    'gtm_container_id' => $settings['gtm_container_id'] ?: config('services.google.gtm_container_id'),
                    'default_thumbnail' => $settings['default_share_thumbnail'],
                ],
            ];
        });
    }

    public function homeMainArticle()
    {
        return $this->cachedJson('home-main-article', fn () => [
            'error' => false,
            'data' => [
                'home_main_article_markdown' => $this->settingValue('home_main_article_markdown'),
            ],
        ]);
    }

    public function blogs(Request $request)
    {
        return $this->cachedJson($request->fullUrl(), function () use ($request) {
            $query = Blog::with(['seriesCategory:id,name,slug,series_title,accent_color', 'author:id,name,slug,role,bio,avatar,status', 'updatedByAuthor:id,name,slug,role,bio,avatar,status', 'additionalAuthors', 'reviewers', 'editors', 'shareLinks'])
                ->where('status', 'published')
                ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
                ->when($request->filled('category'), function ($q) use ($request) {
                    $category = $request->input('category');
                    $q->whereHas('seriesCategory', function ($categoryQuery) use ($category) {
                        $categoryQuery->where('slug', $category)->orWhere('name', $category);
                    });
                })
                ->when($request->filled('tag'), function ($q) use ($request) {
                    $tag = $request->input('tag');
                    $q->where(function ($tagQuery) use ($tag) {
                        $tagQuery->where('tags', 'like', "%{$tag}%")
                            ->orWhere('category', 'like', "%{$tag}%")
                            ->orWhereHas('seriesCategory', function ($categoryQuery) use ($tag) {
                                $categoryQuery->where('name', 'like', "%{$tag}%")
                                    ->orWhere('series_title', 'like', "%{$tag}%");
                            });
                        });
                })
                ->when($request->filled('search'), function ($q) use ($request) {
                    $search = $request->input('search');
                    $q->where(function ($inner) use ($search) {
                        $inner->where('title', 'like', "%{$search}%")
                            ->orWhere('excerpt', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('tags', 'like', "%{$search}%");
                    });
                })
                ->orderByDesc('published_at')
                ->orderByDesc('id');

            $perPage = min((int) $request->input('per_page', 12), 50);
            $blogs = $query->paginate($perPage);
            $blogs->getCollection()->transform(fn (Blog $blog) => $this->blogPayload($blog));

            return [
                'error' => false,
                'data' => $blogs,
            ];
        });
    }

    public function blog(string $slug)
    {
        return $this->cachedJson('blog:'.$slug, function () use ($slug) {
            $blog = Blog::with(['seriesCategory', 'author:id,name,slug,role,bio,avatar,status', 'updatedByAuthor:id,name,slug,role,bio,avatar,status', 'additionalAuthors', 'reviewers', 'editors', 'faqs', 'shareLinks'])
                ->where('slug', $slug)
                ->where('status', 'published')
                ->firstOrFail();

            $seriesArticles = $blog->seriesCategory
                ? $this->articlesForCategory($blog->seriesCategory, $blog->id)
                : collect();

            return [
                'error' => false,
                'data' => $this->blogPayload($blog, true),
                'related' => $seriesArticles,
                'seriesArticles' => $seriesArticles,
            ];
        });
    }

    public function authors()
    {
        return $this->cachedJson('authors', function () {
            $authors = Author::withCount(['blogs' => fn ($q) => $q->where('status', 'published')])
                ->where('status', true)
                ->orderBy('name')
                ->get();

            return [
                'error' => false,
                'data' => $authors,
            ];
        });
    }

    public function author(string $slug)
    {
        return $this->cachedJson('author:'.$slug, function () use ($slug) {
            $author = Author::with([
                'blogs' => fn ($q) => $this->publishedArticleQuery($q)->with('shareLinks'),
                'contributedBlogs' => fn ($q) => $this->publishedArticleQuery($q)->with('shareLinks'),
            ])
                ->where('slug', $slug)
                ->where('status', true)
                ->firstOrFail();

            $articles = $author->blogs
                ->merge($author->contributedBlogs)
                ->unique('id')
                ->sortBy('sort_order')
                ->values()
                ->map(fn (Blog $blog) => $this->blogPayload($blog));
            $authorData = $author->toArray();
            unset($authorData['blogs'], $authorData['contributed_blogs']);

            return [
                'error' => false,
                'data' => array_merge($authorData, [
                    'articles' => $articles,
                ]),
            ];
        });
    }

    public function companyPage(string $slug)
    {
        return $this->cachedJson('company-page:'.$slug, function () use ($slug) {
            $page = CompanyPage::where(function ($q) use ($slug) {
                $q->where('slug', $slug)->orWhere('page_key', $slug);
            })
                ->where('status', true)
                ->firstOrFail();

            return [
                'error' => false,
                'data' => $page,
            ];
        });
    }

    public function storeContact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        ContactUs::create($validator->validated());

        return response()->json(['error' => false, 'message' => 'Contact request stored successfully']);
    }

    public function storeNewsletter(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        NewsletterSubscriber::updateOrCreate(
            ['email' => $request->input('email')],
            [
                'name' => $request->input('name'),
                'source' => $request->input('source', 'website'),
                'status' => 'subscribed',
                'subscribed_at' => now(),
            ]
        );

        return response()->json(['error' => false, 'message' => 'Newsletter subscriber stored successfully']);
    }

    public function storeSearchQuery(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => ['required', 'string', 'max:255'],
            'page' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'results_count' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        SearchQuery::create([
            'query' => $request->input('query'),
            'page' => $request->input('page'),
            'source' => $request->input('source', 'website'),
            'results_count' => $request->input('results_count', 0),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['error' => false, 'message' => 'Search query stored successfully']);
    }

    private function blogPayload(Blog $blog, bool $includeContent = false): array
    {
        $description = $blog->description ?? '';
        $category = $blog->seriesCategory;
        $categoryTitle = $category?->series_title ?: $category?->name;
        $payload = [
            'id' => $blog->id,
            'title' => $blog->title,
            'slug' => $blog->slug,
            'href' => '/'.$blog->slug,
            'excerpt' => $blog->excerpt ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($description))), 320),
            'tag' => $categoryTitle ?: ($blog->category ?: ($blog->tags[0] ?? 'SEO')),
            'categoryId' => $blog->category_id,
            'categorySlug' => $category?->slug,
            'categoryTitle' => $categoryTitle,
            'tags' => $blog->tags,
            'readTime' => $blog->read_time ?: $this->estimateReadTime($description),
            'date' => optional($blog->published_at ?? $blog->created_at)->format('M j, Y'),
            'updatedOn' => $blog->updated_on ? $blog->updated_on->format('M j, Y') : null,
            'accent' => $blog->accent_color ?: '#B8FF35',
            'sort_order' => (int) ($blog->sort_order ?? 0),
            'attributes' => $this->normalizeAttributes($blog->content_attributes),
            'previewHeadings' => $this->extractHeadings($description),
            'image' => $blog->image,
            'isFeatured' => (bool) $blog->is_featured,
            'metaTitle' => $blog->meta_title,
            'metaDescription' => $blog->meta_description,
            'author' => $blog->author,
            'updatedBy' => $blog->updatedByAuthor,
            'additionalAuthors' => $blog->additionalAuthors,
            'reviewers' => $blog->reviewers,
            'editors' => $blog->editors,
            'shareLinks' => $this->shareLinksForBlog($blog),
        ];

        if ($includeContent) {
            $payload['content'] = $description;
            $payload['faqs'] = $blog->relationLoaded('faqs')
                ? $blog->faqs
                    ->filter(fn ($faq) => $faq->is_visible)
                    ->map(fn ($faq) => $this->faqPayload($faq))
                    ->values()
                : [];
        }

        return $payload;
    }

    private function estimateReadTime(?string $html): string
    {
        $words = str_word_count(strip_tags($html ?? ''));
        $minutes = max(1, (int) ceil($words / 220));

        return $minutes.' min';
    }

    public function categories()
    {
        return $this->cachedJson('categories', function () {
            $categories = Category::with(['blogs' => fn ($q) => $this->publishedArticleQuery($q)])
                ->where('status', true)
                ->orderBy('sequence')
                ->orderBy('header_nav_order')
                ->orderBy('id')
                ->get();

            return [
                'error' => false,
                'data' => $categories->map(fn (Category $category) => $this->categoryPayload($category)),
            ];
        });
    }

    public function category($slug)
    {
        return $this->cachedJson('category:'.$slug, function () use ($slug) {
            $category = Category::with(['blogs' => fn ($q) => $this->publishedArticleQuery($q)])
                ->where('slug', $slug)
                ->where('status', true)
                ->first();

            if (! $category) {
                return ['error' => true, 'message' => 'Category not found', '_status' => 404];
            }

            return [
                'error' => false,
                'data' => $this->categoryPayload($category),
            ];
        });
    }

    public function articles(Request $request)
    {
        return $this->cachedJson($request->fullUrl(), function () use ($request) {
            $query = \App\Models\Blog::with(['author:id,name,slug,role,bio,avatar,status', 'shareLinks'])
                ->where('status', 'published')
                ->orderByDesc('id');

            $perPage = min((int) $request->input('per_page', 50), 100);
            $articles = $query->paginate($perPage);
            $articles->getCollection()->transform(fn (\App\Models\Blog $blog) => $this->blogPayload($blog));

            return [
                'error' => false,
                'data' => $articles,
            ];
        });
    }

    public function search(Request $request)
    {
        return $this->cachedJson($request->fullUrl(), function () use ($request) {
            $query = Blog::with(['seriesCategory:id,name,slug,series_title,accent_color', 'author:id,name,slug,role,bio,avatar,status', 'shareLinks'])
                ->where('status', 'published');

            if ($request->filled('q')) {
                $search = $request->input('q');
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('tags', 'like', "%{$search}%");
                });
            }

            $perPage = min((int) $request->input('per_page', 20), 50);
            $results = $query->orderByDesc('published_at')->paginate($perPage);
            $results->getCollection()->transform(fn (Blog $blog) => $this->blogPayload($blog));

            return [
                'error' => false,
                'data' => $results,
            ];
        });
    }

    private function publishedArticleQuery($query)
    {
        return $query->where('status', 'published')
            ->orderByRaw('CASE WHEN sort_order IS NULL OR sort_order = 0 THEN 999999 ELSE sort_order END ASC')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    private function categoryPayload(Category $category): array
    {
        $articles = $category->relationLoaded('blogs')
            ? $category->blogs
            : $this->publishedArticleQuery($category->blogs())->get();

        return [
            'id' => $category->id,
            'title' => $category->series_title ?: $category->name,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->series_description ?: $category->description,
            'content' => $category->series_content,
            'image' => $category->image,
            'icon' => $category->icon,
            'accent' => $category->accent_color ?: '#B8FF35',
            'show_in_nav' => (bool) $category->show_in_header_nav,
            'nav_order' => (int) $category->header_nav_order,
            'show_in_header_nav' => (bool) $category->show_in_header_nav,
            'header_nav_order' => (int) $category->header_nav_order,
            'show_in_mobile_nav' => (bool) $category->show_in_mobile_nav,
            'mobile_nav_order' => (int) $category->mobile_nav_order,
            'isComingSoon' => (bool) $category->is_coming_soon,
            'is_coming_soon' => (bool) $category->is_coming_soon,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
            'articles' => $articles->map(fn (Blog $article) => $this->articleSummaryPayload($article))->values(),
        ];
    }

    private function articleSummaryPayload(Blog $article, ?int $currentId = null): array
    {
        $description = $article->description ?? '';

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'href' => '/'.$article->slug,
            'readTime' => $article->read_time ?: $this->estimateReadTime($description),
            'sort_order' => (int) ($article->sort_order ?? 0),
            'excerpt' => $article->excerpt ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($description))), 320),
            'date' => optional($article->published_at ?? $article->created_at)->format('M j, Y'),
            'accent' => $article->accent_color ?: '#B8FF35',
            'attributes' => $this->normalizeAttributes($article->content_attributes),
            'previewHeadings' => $this->extractHeadings($description),
            'isCurrent' => $currentId ? $article->id === $currentId : false,
            'image' => $article->image,
            'categorySlug' => $article->seriesCategory?->slug,
            'categoryTitle' => $article->seriesCategory?->series_title ?: $article->seriesCategory?->name,
        ];
    }

    private function articlesForCategory(Category $category, ?int $currentId = null)
    {
        return $this->publishedArticleQuery($category->blogs())
            ->get()
            ->map(fn (Blog $article) => $this->articleSummaryPayload($article, $currentId))
            ->values();
    }

    private function faqPayload($faq): array
    {
        return [
            'id' => $faq->id,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'sortOrder' => (int) $faq->sort_order,
            'includeInSchema' => (bool) $faq->include_in_schema,
            'schemaQuestion' => $faq->schema_question,
            'schemaAnswer' => $faq->schema_answer,
            'options' => $faq->options ?? [],
        ];
    }

    private function normalizeAttributes($attributes): array
    {
        if (is_string($attributes)) {
            $attributes = json_decode($attributes, true) ?: [];
        }

        if (! is_array($attributes)) {
            return [];
        }

        return collect($attributes)
            ->map(function ($attribute) {
                if (is_string($attribute)) {
                    return ['label' => $attribute, 'color' => '#B8FF35'];
                }

                $label = trim((string) ($attribute['label'] ?? ''));
                if ($label === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $attribute['color'] ?? '')
                        ? $attribute['color']
                        : '#B8FF35',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function extractHeadings(?string $html, int $limit = 5): array
    {
        if (! $html) {
            return [];
        }

        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $html, $matches);

        return collect($matches[1] ?? [])
            ->map(fn ($heading) => trim(html_entity_decode(strip_tags($heading))))
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    public function shareLink(string $code)
    {
        return $this->cachedJson('share-link:'.$code, function () use ($code) {
            $shareLink = \App\Models\ArticleShareLink::where('code', $code)
                ->where('is_active', true)
                ->firstOrFail();

            return [
                'error' => false,
                'data' => [
                    'targetUrl' => $shareLink->target_url,
                    'platform' => $shareLink->platform,
                ],
            ];
        });
    }

    private function shareLinksForBlog(Blog $blog): array
    {
        return app(ArticleShareLinkService::class)->linksForBlog($blog);
    }

    private function cachedJson(string $key, callable $callback, int $seconds = 300)
    {
        $payload = PublicContentCacheService::remember($key, $callback, $seconds);
        $status = 200;

        if (is_array($payload) && isset($payload['_status'])) {
            $status = (int) $payload['_status'];
            unset($payload['_status']);
        }

        return response()
            ->json($payload, $status)
            ->header('Cache-Control', PublicContentCacheService::cacheControl($seconds));
    }

    private function settingValues(array $names): array
    {
        $values = Setting::whereIn('name', $names)->get()->pluck('value', 'name')->all();

        return array_merge(array_fill_keys($names, null), $values);
    }

    private function settingValue(string $name): ?string
    {
        $setting = Setting::where('name', $name)->first();

        return $setting?->value;
    }
}
