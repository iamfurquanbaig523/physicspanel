<?php

namespace Database\Seeders;

use App\Models\ArticleShareLink;
use App\Models\Author;
use App\Models\Blog;
use App\Models\Category;
use App\Models\CompanyPage;
use App\Models\SeoSetting;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SearchEngineBasicsSiteSeeder extends Seeder
{
    private const SERIES_SLUG = 'search-engine-basics';
    private const TODAY = '2026-05-20 09:00:00';

    private array $articleSlugs = [
        1  => 'what-is-information-retrieval',
        2  => 'vector-space-model',
        3  => 'tf-idf-bm25-explained',
        4  => 'pagerank-algorithm-explained',
        5  => 'hits-algorithm-explained',
        6  => 'crawl-index-rank-pipeline',
        7  => 'knowledge-graph-hummingbird',
        8  => 'learning-to-rank',
        9  => 'map-mrr-ndcg-explained',
        10 => 'seo-ethics-explained',
    ];

    public function run(): void
    {
        $authors = $this->seedAuthors();
        $this->seedSettings();
        $this->seedHomeSeo();
        $this->seedCategories();
        $category = Category::where('slug', self::SERIES_SLUG)->first();

        if ($category) {
            $this->seedMissingFoundationArticles($category, $authors);
        }

        $this->attachArticleAssetsAndAuthors($authors);
        $this->seedShareLinks();
        $this->seedCompanyPages();
    }

    private function seedAuthors(): array
    {
        $authors = [];

        foreach ($this->authorData() as $data) {
            $avatarPath = $this->copySeedAsset(
                $this->contentPath('assets/authors/'.$data['image']),
                'authors/'.$data['image']
            );

            $authors[$data['slug']] = Author::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'role' => $data['role'],
                    'bio' => $data['bio'],
                    'email' => $data['email'] ?? null,
                    'avatar' => $avatarPath,
                    'website_url' => $data['website_url'],
                    'social_links' => ['linkedin' => $data['website_url']],
                    'status' => true,
                ]
            );
        }

        return $authors;
    }

    private function authorData(): array
    {
        return [
            [
                'name' => 'Muhammad Zia',
                'slug' => 'muhammad-zia',
                'role' => 'SEO Educator',
                'website_url' => 'https://www.linkedin.com/in/muhammad-zia-8b1b33283/',
                'image' => 'Muhammad Zia.jpg',
                'bio' => 'Muhammad Zia is a qualified SEO Educator holding a Bachelor of Science in Computer Science (BSCS) from the University of the Punjab. With over 4 years of hands-on experience in digital marketing and search engine optimization, he helps learners understand keyword strategy, technical audits, on-page optimization, backlink strategy, and organic growth with practical, data-backed clarity.',
            ],
            [
                'name' => 'Sohaib Hayder',
                'slug' => 'sohaib-hayder',
                'role' => 'Educator',
                'website_url' => 'https://www.linkedin.com/in/sohaib-hayder-2a4423321/',
                'image' => 'Sohaib Hayder.jpg',
                'bio' => 'Sohaib Hayder is a professionally trained educator with a learner-first approach to digital education, curriculum development, and knowledge delivery. His review work focuses on structure, clarity, and making complex SEO and technical concepts genuinely transferable to readers.',
            ],
            [
                'name' => 'Muhammad Furquan',
                'slug' => 'muhammad-furquan',
                'role' => 'Legal & Compliance Reviewer',
                'website_url' => 'https://www.linkedin.com/in/muhammad-furquan-baig-52bb01305/',
                'image' => 'Muhammad Furquan, Barrister.jpg',
                'bio' => 'Muhammad Furquan is a qualified Barrister and legal professional with an LLM from BPP University Law School. He reviews content against copyright law, defamation standards, consumer protection rules, digital publishing guidelines, and broader compliance requirements before publication.',
            ],
            [
                'name' => 'Muhammad Baig',
                'slug' => 'muhammad-baig',
                'role' => 'Software Engineer & Mathematical Verifier',
                'website_url' => 'https://www.linkedin.com/in/muhammedbeig/',
                'image' => 'Muhammad Baig.jpg',
                'bio' => 'Muhammad Baig is a Software Engineer with a BSSE from the University of Sahiwal. He specializes in computational verification of mathematical equations, formulas, technical claims, and simulations, validating difficult SEO and information retrieval material by checking the logic in code.',
            ],
            [
                'name' => 'Imdad Ullah Khan, Ph.D.',
                'slug' => 'imdad-ullah-khan-phd',
                'role' => 'Data Science & ML Researcher | Content Evaluator & Approver',
                'website_url' => 'https://www.linkedin.com/in/imdadk/',
                'image' => 'Imdad Ullah Khan, Ph.D..jpg',
                'bio' => 'Imdad Ullah Khan is a Ph.D. in Computer Science from Rutgers University with deep experience in data science, machine learning, artificial intelligence, statistical modeling, NLP, and evidence-based computational methods. He evaluates published material for accuracy, depth, methodological soundness, and intellectual honesty.',
            ],
        ];
    }

    private function seedSettings(): void
    {
        $thumbnail = $this->copySeedAsset(
            $this->contentPath('assets/site/Thumbnail.png'),
            'settings/Thumbnail.png'
        );
        $homeMarkdown = $this->readFile($this->contentPath('search-engines-basics.md'));

        $settings = [
            'company_name' => ['Physics Fundamentals', 'string'],
            'website_url' => ['https://physicsfundamental.org', 'string'],
            'company_email' => ['hello@physicsfundamental.org', 'string'],
            'google_site_verification' => ['vHzrzYvTLVaFa1uW5eOTfAb91sB6jXRJySFCcI_apfc', 'string'],
            'gtm_container_id' => ['GTM-P8LVQLT3', 'string'],
            'default_share_thumbnail' => [$thumbnail, 'file'],
            'home_main_article_markdown' => [$homeMarkdown, 'string'],
        ];

        foreach ($settings as $name => [$value, $type]) {
            if ($value === null || $value === '') {
                continue;
            }

            Setting::updateOrCreate(
                ['name' => $name],
                ['value' => $value, 'type' => $type]
            );
        }
    }

    private function seedHomeSeo(): void
    {
        SeoSetting::updateOrCreate(
            ['page' => 'home'],
            [
                'title' => 'Physics Fundamentals: A Step-by-Step Guide from Mechanics to Quantum',
                'description' => 'Master the fundamentals of physics, from classical mechanics and electromagnetism to relativity and quantum theory.',
                'keywords' => 'physics fundamentals, learn physics, classical mechanics, quantum physics, relativity',
                'image' => 'settings/Thumbnail.png',
            ]
        );
    }

    private function seedCategories(): void
    {
        $categories = [
            [
                'slug'               => 'search-engine-crawling',
                'name'               => 'Crawling',
                'series_title'       => 'Crawling',
                'description'        => 'How Googlebot discovers your pages by following links across the web.',
                'series_description' => 'How Googlebot discovers your pages by following links across the web.',
                'accent_color'       => '#b8ff35',
                'icon'               => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/><path d="M2 12h20"/></svg>',
                'status'             => true,
                'is_coming_soon'     => true,
                'show_in_header_nav' => true,
                'show_in_mobile_nav' => true,
                'header_nav_order'   => 2,
                'mobile_nav_order'   => 2,
                'meta_title'         => 'What is search engine crawling? (2026 guide)',
                'meta_description'   => 'Search engine crawling is how Google discovers your pages. Learn how Googlebot works, crawl budget, and how to ensure important pages get crawled.',
            ],
            [
                'slug'               => 'search-engine-indexing',
                'name'               => 'Indexing',
                'series_title'       => 'Indexing',
                'description'        => 'What happens after a page is crawled â€” and why some pages never make it into the index.',
                'series_description' => 'What happens after a page is crawled â€” and why some pages never make it into the index.',
                'accent_color'       => '#b8ff35',
                'icon'               => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>',
                'status'             => true,
                'is_coming_soon'     => true,
                'show_in_header_nav' => true,
                'show_in_mobile_nav' => true,
                'header_nav_order'   => 3,
                'mobile_nav_order'   => 3,
                'meta_title'         => 'What is search engine indexing? (2026 guide)',
                'meta_description'   => 'Search engine indexing is how Google stores and organizes your content. Learn the inverted index, canonicalization, and what determines if a page gets indexed.',
            ],
            [
                'slug'               => 'search-engine-ranking',
                'name'               => 'Ranking',
                'series_title'       => 'Ranking',
                'description'        => 'The signals, weights, and machine learning systems that decide which page wins position #1.',
                'series_description' => 'The signals, weights, and machine learning systems that decide which page wins position #1.',
                'accent_color'       => '#b8ff35',
                'icon'               => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
                'status'             => true,
                'is_coming_soon'     => true,
                'show_in_header_nav' => true,
                'show_in_mobile_nav' => true,
                'header_nav_order'   => 4,
                'mobile_nav_order'   => 4,
                'meta_title'         => 'What is search engine ranking? (2026 guide)',
                'meta_description'   => 'Search engine ranking decides which pages appear first. Learn the signals Google weighs, from PageRank to machine learning, and how to improve your positions.',
            ],
        ];

        foreach ($categories as $data) {
            $slug = $data['slug'];
            unset($data['slug']);
            Category::updateOrCreate(['slug' => $slug], $data);
        }
    }

    private function seedMissingFoundationArticles(Category $category, array $authors): void
    {
        $foundation = [
            1 => [
                'title' => 'What Is Information Retrieval? The Core Problem Every Search Engine Solves',
                'excerpt' => 'Before search engines existed, IR researchers were solving the same core problem: how do you retrieve a relevant document from a large collection? This article defines the field\'s core concepts, precision, recall, relevance, and the recall-precision tradeoff, grounding every later topic in a rigorous framework rather than marketing folklore.',
                'meta_title' => 'What is information retrieval? (2026 guide)',
                'meta_description' => 'Information retrieval is the science of finding relevant documents. Learn precision, recall, relevance, and why these concepts explain how search engines work.',
            ],
            2 => [
                'title' => 'What Is the Vector Space Model? How Documents Become Numbers (and Why That Changes Everything)',
                'excerpt' => 'The Vector Space Model represents documents and queries as mathematical vectors, making it possible to compare meaning through distance, angle, and weighted terms instead of simple keyword presence.',
                'meta_title' => 'What is the vector space model? (2026 guide)',
                'meta_description' => 'The vector space model turns documents into vectors. Learn cosine similarity, TF weighting, and why this 1975 idea still powers BERT and modern search.',
            ],
        ];

        foreach ($foundation as $number => $data) {
            $slug = $this->articleSlugs[$number];
            if (Blog::where('slug', $slug)->exists()) {
                continue;
            }

            $html = $this->readFile($this->contentPath("article{$number}.html"));
            if ($html === '') {
                $html = '<p>'.$data['excerpt'].'</p>';
            }

            Blog::create([
                'category_id' => $category->id,
                'sort_order' => $number,
                'author_id' => $authors['muhammad-zia']->id ?? Author::query()->value('id'),
                'title' => $data['title'],
                'slug' => $slug,
                'description' => $html,
                'excerpt' => $data['excerpt'],
                'tags' => ['Basics', 'Search Engine Fundamentals'],
                'category' => $category->name,
                'read_time' => $this->estimateReadTime($html),
                'accent_color' => '#B8FF35',
                'content_attributes' => $number === 1
                    ? [['label' => 'Foundation', 'color' => '#B8FF35'], ['label' => 'Research paper', 'color' => '#FF4FA3']]
                    : [['label' => 'Foundation', 'color' => '#B8FF35'], ['label' => 'Technical', 'color' => '#00D1FF'], ['label' => 'Research paper', 'color' => '#FF4FA3'], ['label' => 'Data study', 'color' => '#00C853'], ['label' => 'Math formula', 'color' => '#FFD400']],
                'is_featured' => $number === 1,
                'status' => 'published',
                'published_at' => Carbon::parse('2026-05-15 09:00:00')->addDays($number - 1),
                'updated_on' => Carbon::parse(self::TODAY)->toDateString(),
                'updated_by_author_id' => $authors['imdad-ullah-khan-phd']->id ?? null,
                'meta_title' => $data['meta_title'],
                'meta_description' => $data['meta_description'],
            ]);
        }
    }

    private function attachArticleAssetsAndAuthors(array $authors): void
    {
        $mathArticles = [2, 3, 4, 9];
        $capDate = Carbon::parse(self::TODAY);

        foreach ($this->articleSlugs as $number => $slug) {
            $blog = Blog::where('slug', $slug)->first();
            if (! $blog) {
                continue;
            }

            $image = $this->copySeedAsset(
                $this->contentPath("assets/articles/article{$number}.png"),
                "blog/article{$number}.png"
            );

            $primarySlug = $number === 10
                ? 'muhammad-furquan'
                : (in_array($number, $mathArticles, true)
                    ? 'muhammad-baig'
                    : ($number % 2 === 0 ? 'sohaib-hayder' : 'muhammad-zia'));
            $primary = $authors[$primarySlug] ?? Author::query()->first();

            $publishedAt = $blog->published_at ? Carbon::parse($blog->published_at) : $capDate;
            if ($publishedAt->greaterThan($capDate)) {
                $publishedAt = $capDate->copy();
            }

            $blog->update([
                'author_id' => $primary?->id,
                'image' => $image ?: $blog->getRawOriginal('image'),
                'is_featured' => $number === 1,
                'published_at' => $publishedAt,
                'updated_on' => $blog->updated_on ?: $capDate->toDateString(),
                'updated_by_author_id' => $blog->updated_by_author_id ?: ($authors['imdad-ullah-khan-phd']->id ?? null),
            ]);

            DB::table('blog_contributors')->where('blog_id', $blog->id)->delete();

            foreach (['imdad-ullah-khan-phd' => 'reviewer', 'muhammad-furquan' => 'editor'] as $authorSlug => $role) {
                $author = $authors[$authorSlug] ?? null;
                if (! $author) {
                    continue;
                }

                DB::table('blog_contributors')->updateOrInsert(
                    [
                        'blog_id' => $blog->id,
                        'author_id' => $author->id,
                        'contribution_type' => $role,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function seedShareLinks(): void
    {
        $siteUrl = rtrim(Setting::where('name', 'website_url')->value('value') ?: 'https://physicsfundamental.org', '/');

        Blog::with('seriesCategory')->where('status', 'published')->get()->each(function (Blog $blog) use ($siteUrl) {
            $path = $blog->seriesCategory?->slug
                ? '/'.$blog->seriesCategory->slug.'/'.$blog->slug
                : '/'.$blog->slug;
            $targetUrl = $siteUrl.$path;

            foreach (['facebook', 'instagram', 'tiktok', 'whatsapp', 'link'] as $platform) {
                ArticleShareLink::updateOrCreate(
                    ['blog_id' => $blog->id, 'platform' => $platform],
                    [
                        'code' => $this->shareCode($blog, $platform),
                        'target_url' => $targetUrl,
                        'is_active' => true,
                    ]
                );
            }
        });
    }

    private function seedCompanyPages(): void
    {
        CompanyPage::updateOrCreate(
            ['page_key' => 'about-us'],
            [
                'title' => 'About Physics Fundamentals',
                'slug' => 'about-us',
                'excerpt' => 'Physics Fundamentals is a free, structured guide library for learning physics from first principles.',
                'content' => '<p><strong>We teach physics from the ground up.</strong></p><p>Physics Fundamentals is built for learners who want the ideas behind mechanics, fields, relativity, quantum theory, and mathematical physics to feel connected rather than memorized.</p><h2>What We Teach</h2><p>Every guide starts with the underlying principle, then builds toward the equations, examples, and intuition that make the topic useful.</p><h2>Our Promise</h2><p>We keep the explanations clear, rigorous, and free to read.</p>',
                'meta_title' => 'About Physics Fundamentals',
                'meta_description' => 'Learn about Physics Fundamentals, the free structured library for learning physics from first principles.',
                'status' => true,
                'published_at' => Carbon::parse(self::TODAY),
            ]
        );
    }

    private function copySeedAsset(string $source, string $destination): ?string
    {
        if (! is_file($source)) {
            return null;
        }

        Storage::disk('public')->put($destination, file_get_contents($source));

        return $destination;
    }

    private function shareCode(Blog $blog, string $platform): string
    {
        $platformPrefix = substr(preg_replace('/[^a-z0-9]/i', '', $platform) ?: 's', 0, 1);
        $blogKey = base_convert((string) $blog->id, 10, 36);
        $hash = substr(hash('crc32b', $blog->slug.'|'.$platform), 0, 4);

        return Str::lower($platformPrefix.$blogKey.$hash);
    }

    private function estimateReadTime(string $html): string
    {
        $words = str_word_count(strip_tags($html));

        return max(1, (int) ceil($words / 220)).' min';
    }

    private function contentPath(string $path): string
    {
        return database_path('seeders/content/basics/'.$path);
    }

    private function readFile(string $path): string
    {
        return is_file($path) ? file_get_contents($path) : '';
    }
}
