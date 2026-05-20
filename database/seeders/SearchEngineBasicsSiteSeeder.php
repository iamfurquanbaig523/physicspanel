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
        1 => 'what-is-information-retrieval',
        2 => 'what-is-the-vector-space-model-how-documents-become-numbers-and-why-that-changes-everything',
        3 => 'tf-idf-and-bm25-the-mathematics-of-keyword-relevance-and-why-repetition-stops-helping',
        4 => 'pagerank-how-brin-and-page-replaced-word-counting-with-link-counting',
        5 => 'hubs-and-authorities-how-kleinbergs-hits-algorithm-explains-why-niche-links-beat-generic-ones',
        6 => 'crawl-index-rank-the-search-engine-pipeline-that-decides-whether-your-page-exists-to-google',
        7 => 'from-strings-to-things-how-googles-knowledge-graph-and-hummingbird-update-changed-what-relevant-means',
        8 => 'learning-to-rank-how-machine-learning-replaced-the-200-factor-checklist',
        9 => 'map-mrr-and-ndcg-the-metrics-that-define-what-better-rankings-actually-mean',
        10 => 'the-ethics-of-search-the-business-model-that-funds-it-and-what-seo-actually-is',
    ];

    public function run(): void
    {
        $authors = $this->seedAuthors();
        $this->seedSettings();
        $this->seedHomeSeo();
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
            'company_name' => ['Search Engine Basics', 'string'],
            'website_url' => ['https://searchenginebasics.io', 'string'],
            'company_email' => ['hello@searchenginebasics.io', 'string'],
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
                'title' => 'Search Engine Basics: A Step-by-Step SEO Guide for Beginners',
                'description' => 'Master search engine basics — learn how crawling, indexing, and ranking work. Build your SEO Knowledge from beginner to expert. Your SEO marketing starts here.',
                'keywords' => 'search engine basics, seo guide, crawling, indexing, ranking',
                'image' => 'settings/Thumbnail.png',
            ]
        );
    }

    private function seedMissingFoundationArticles(Category $category, array $authors): void
    {
        $foundation = [
            1 => [
                'title' => 'What Is Information Retrieval? The Core Problem Every Search Engine Solves',
                'excerpt' => 'Before search engines existed, IR researchers were solving the same core problem: how do you retrieve a relevant document from a large collection? This article defines the field\'s core concepts, precision, recall, relevance, and the recall-precision tradeoff, grounding every later topic in a rigorous framework rather than marketing folklore.',
                'meta_title' => 'What Is Information Retrieval? Precision, Recall, Relevance & Search',
                'meta_description' => 'Information retrieval (IR) is the science of finding relevant documents inside large collections. Learn what precision, recall, and relevance mean.',
            ],
            2 => [
                'title' => 'What Is the Vector Space Model? How Documents Become Numbers (and Why That Changes Everything)',
                'excerpt' => 'The Vector Space Model represents documents and queries as mathematical vectors, making it possible to compare meaning through distance, angle, and weighted terms instead of simple keyword presence.',
                'meta_title' => 'Vector Space Model: How Documents Become Search Vectors',
                'meta_description' => 'Learn how the Vector Space Model turns words into numbers, why cosine similarity matters, and how TF-IDF connects classic IR to semantic search.',
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

            $primarySlug = in_array($number, $mathArticles, true)
                ? 'muhammad-baig'
                : ($number % 2 === 0 ? 'sohaib-hayder' : 'muhammad-zia');
            $primary = $authors[$primarySlug] ?? Author::query()->first();
            $contributors = in_array($number, $mathArticles, true)
                ? ['imdad-ullah-khan-phd', 'muhammad-furquan', $number % 2 === 0 ? 'muhammad-zia' : 'sohaib-hayder']
                : ['muhammad-baig', 'imdad-ullah-khan-phd', 'muhammad-furquan'];

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

            foreach ($contributors as $contributorSlug) {
                $author = $authors[$contributorSlug] ?? null;
                if (! $author || $author->id === $primary?->id) {
                    continue;
                }

                DB::table('blog_contributors')->insert([
                    'blog_id' => $blog->id,
                    'author_id' => $author->id,
                    'contribution_type' => 'author',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

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
        $siteUrl = rtrim(Setting::where('name', 'website_url')->value('value') ?: 'https://searchenginebasics.io', '/');

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
                'title' => 'About Search Engine Basics',
                'slug' => 'about-us',
                'excerpt' => 'Search Engine Basics is a free, structured guide library for understanding how search engines crawl, index, rank, and evaluate the web.',
                'content' => '<p><strong>We teach you how search engines think, not just how to trick them.</strong></p><p>Most SEO content on the internet hands you a checklist. Put your keyword in the H1. Write a meta description under 160 characters. Get backlinks. Done.</p><p>But nobody explains why any of that works.</p><p>That\'s exactly the gap we built this site to fill.</p><h2>Who We Are</h2><p>We are a team of SEO practitioners, researchers, and educators who got tired of surface-level advice. We spent years studying how search engines actually work, the crawling, the indexing, the ranking signals, the algorithms — and we noticed something: people who understand the system never have to memorize tactics. The right moves become obvious.</p><p>So we built a place where that understanding comes first.</p><h2>What We Actually Teach</h2><p>Every piece of content on this site starts from the ground up. Before we tell you what to put in an H1 tag, we explain what an H1 tag actually communicates to a search engine and why it was designed that way. Before we talk about meta descriptions, we show you how a search result page works and what job the description is really doing.</p><p>By the time you finish reading our content, you will not just know what to do, you will know why it works, which means you can apply it to any situation, any niche, any website, without needing a new checklist every time Google updates its algorithm.</p><h2>What Makes Us Different</h2><ul><li><strong>We go foundational.</strong> Every article is built from first principles. No assumed knowledge, no jargon without explanation.</li><li><strong>We make it interactive.</strong> We do not just describe concepts, we show you how they behave, with real examples you can see and test yourself.</li><li><strong>We teach the system, not the shortcut.</strong> Shortcuts expire. System knowledge compounds. Our goal is to turn you into someone who understands search engines deeply enough to figure out any SEO challenge on your own.</li><li><strong>We cover the whys.</strong> Why does Google care about page speed? Why does keyword placement in a title matter? Why do some backlinks count and others do not? Every lesson answers the question underneath the question.</li></ul><h2>What You Will Walk Away With</h2><p>After going through our content, you will understand:</p><ul><li>How search engines crawl and index the web</li><li>Why certain HTML elements carry more weight than others</li><li>How to write title tags and meta descriptions that actually work, and why they work</li><li>What signals search engines use to decide which page deserves to rank</li><li>How to think about any SEO decision from a logical, system-level perspective</li></ul><p>You will not just be better at SEO. You will understand SEO, and that is something no algorithm update can take away from you.</p><h2>Our Promise</h2><p>We will never publish content that tells you what to do without explaining why. If we cover a topic, we cover it properly, from the foundation up, in plain language, with real examples.</p><p>Because we believe the internet deserves more people who actually understand how it works.</p><p>Welcome. Let\'s start from the beginning.</p>',
                'meta_title' => 'About Search Engine Basics',
                'meta_description' => 'Learn about Search Engine Basics, the free structured library for understanding crawling, indexing, ranking, search algorithms, and SEO fundamentals.',
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
