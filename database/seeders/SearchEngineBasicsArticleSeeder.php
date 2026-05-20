<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Blog;
use App\Models\BlogAttributePreset;
use App\Models\BlogFaq;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SearchEngineBasicsArticleSeeder extends Seeder
{
    private const SERIES_SLUG = 'search-engine-basics';

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

    private array $presetColors = [
        'Foundation' => '#B8FF35',
        'Practical' => '#FF9F43',
        'Technical' => '#00D1FF',
        'Official doc' => '#4A7DFF',
        'Research paper' => '#FF4FA3',
        'Data study' => '#00C853',
        'Math formula' => '#FFD400',
    ];

    private array $citationPlan = [
        1 => [
            '__intro' => [1],
            'What Is Information Retrieval' => [1],
            'Why Did Researchers Start' => [1],
            'What Are Precision and Recall' => [4],
            'How Information Retrieval Became' => [2, 3],
        ],
        2 => [
            '__intro' => [1],
            'What Is the Vector Space Model' => [1],
            'What Is TF-IDF' => [2, 3],
            'How Do Embeddings' => [4],
        ],
        3 => [
            '__intro' => [1],
            'What Is TF-IDF' => [1, 2],
            'Why Did TF-IDF' => [2],
            'What Is BM25' => [3],
            'How Does the BM25' => [3],
            'A Worked Example' => [3],
            'What Is BM25F' => [4],
        ],
        4 => [
            '__intro' => [1],
            'What Is PageRank' => [1, 2],
            'How Does a Link' => [1],
            'What Is the Random Surfer' => [1],
            'What Is the PageRank Formula' => [1],
            'What Are Spider Traps' => [1],
            'What Does PageRank Mean' => [3],
        ],
        5 => [
            '__intro' => [1],
            'What Is the HITS' => [1],
            'What Are Hubs' => [1],
            'How Does the HITS' => [1],
            'What Are the Root' => [1],
            'What Is the TKC' => [2, 3],
            'How Does HITS Compare' => [1, 4],
            'What Does HITS Mean' => [1, 2],
        ],
        6 => [
            '__intro' => [1],
            'What Is the Crawl' => [1],
            'Stage 1' => [1, 2],
            'Stage 2' => [3, 4],
            'Stage 3' => [1],
            'What Does the Pipeline' => [5, 6],
            'Key Takeaways' => [7, 8],
        ],
        7 => [
            '__intro' => [1],
            'What Is Wrong' => [1, 2],
            'What Is the Google Knowledge Graph' => [3, 4],
            'What Is the Hummingbird' => [2, 3],
            'What Is Entity Salience' => [5, 6],
            'How Does Entity Disambiguation' => [1, 4],
            'What Does the String' => [7, 8],
        ],
        8 => [
            '__intro' => [1],
            'Why Did Hand' => [2, 3],
            'What Is Learning' => [3, 4],
            'What Is the Pointwise' => [3],
            'What Is the Pairwise' => [1, 3],
            'What Is the Listwise' => [4],
            'How Does LTR Fit' => [5, 6],
            'What Does Learning' => [7],
        ],
        9 => [
            '__intro' => [1],
            'Why Do Search Engines' => [1],
            'Building Blocks' => [1],
            'What Is Mean Average' => [1],
            'What Is Mean Reciprocal' => [1],
            'What Is Normalized' => [2, 3],
            'How Do the Three Metrics' => [4, 5],
            'Where Do All Three Metrics' => [6],
            'What Do These Metrics Mean' => [7, 8],
        ],
        10 => [
            '__intro' => [1],
            'The Founding Irony' => [1],
            'How Are Organic Rankings' => [1],
            'What Are the Search Quality' => [2],
            'What Is E-E-A-T' => [3, 4],
            'What Are the Most' => [5],
            'What Does SEO Actually' => [5],
            'Google Search Console' => [6, 7],
            'How Do the Ten' => [8, 9],
        ],
    ];

    public function run(): void
    {
        $this->seedPresetAttributes();

        $category = $this->basicsCategory();
        $author = Author::firstOrCreate(
            ['slug' => 'search-engine-basics-team'],
            [
                'name' => 'Search Engine Basics Team',
                'role' => 'Editorial team',
                'bio' => 'The Search Engine Basics editorial team writes practical, source-backed guides to how search engines work.',
                'status' => true,
            ]
        );

        $excerptData = $this->parseExcerptDescriptions($this->contentPath('excerptDesc.md'));
        $this->patchExistingFoundationArticles($category, $author);

        foreach (range(3, 10) as $articleNumber) {
            $article = $this->parseArticle($articleNumber, $excerptData[$articleNumber] ?? []);
            $blog = Blog::updateOrCreate(
                ['slug' => $this->articleSlugs[$articleNumber]],
                [
                    'category_id' => $category->id,
                    'sort_order' => $articleNumber,
                    'author_id' => $author->id,
                    'title' => $article['title'],
                    'description' => $article['html'],
                    'excerpt' => $article['excerpt'],
                    'tags' => ['Basics', 'Search Engine Fundamentals'],
                    'category' => $category->name,
                    'read_time' => $this->estimateReadTime($article['html']),
                    'accent_color' => '#B8FF35',
                    'content_attributes' => $article['attributes'],
                    'is_featured' => false,
                    'status' => 'published',
                    'published_at' => $this->publicationDate($articleNumber),
                    'meta_title' => $article['meta_title'],
                    'meta_description' => $article['meta_description'],
                ]
            );

            BlogFaq::where('blog_id', $blog->id)->delete();
            foreach ($article['faqs'] as $index => $faq) {
                BlogFaq::create([
                    'blog_id' => $blog->id,
                    'question' => $faq['question'],
                    'answer' => $faq['answer'],
                    'sort_order' => $index + 1,
                    'is_visible' => true,
                    'include_in_schema' => true,
                    'schema_question' => $faq['question'],
                    'schema_answer' => strip_tags($faq['answer']),
                    'options' => null,
                ]);
            }
        }
    }

    private function basicsCategory(): Category
    {
        $category = Category::firstOrCreate(
            ['slug' => self::SERIES_SLUG],
            [
                'name' => 'Basics',
                'series_title' => 'Basics',
                'status' => true,
                'accent_color' => '#B8FF35',
                'description' => 'The big picture. Understand the three-stage process every search engine uses before writing a single line of SEO.',
                'series_description' => 'The big picture. Understand the three-stage process every search engine uses before writing a single line of SEO.',
                'show_in_header_nav' => true,
                'show_in_mobile_nav' => true,
                'header_nav_order' => 1,
                'mobile_nav_order' => 1,
                'meta_title' => 'Search Engine Basics Series',
                'meta_description' => 'Read the foundational Search Engine Basics series in order.',
            ]
        );

        $dirty = false;
        foreach ([
            'series_title' => 'Basics',
            'accent_color' => '#B8FF35',
            'description' => 'The big picture. Understand the three-stage process every search engine uses before writing a single line of SEO.',
            'series_description' => 'The big picture. Understand the three-stage process every search engine uses before writing a single line of SEO.',
            'meta_title' => 'Search Engine Basics Series',
            'meta_description' => 'Read the foundational Search Engine Basics series in order.',
        ] as $key => $value) {
            if (blank($category->{$key})) {
                $category->{$key} = $value;
                $dirty = true;
            }
        }

        if (! $category->status) {
            $category->status = true;
            $dirty = true;
        }

        if ($dirty) {
            $category->save();
        }

        return $category;
    }

    private function seedPresetAttributes(): void
    {
        BlogAttributePreset::whereIn('label', [
            'Concept',
            'New ↑',
            'Warning / trap',
            'Worked example',
        ])->delete();

        BlogAttributePreset::whereRaw('CHAR_LENGTH(label) > 32')->delete();

        $index = 1;
        foreach ($this->presetColors as $label => $color) {
            $preset = BlogAttributePreset::updateOrCreate(
                ['label' => $label],
                [
                    'color' => $color,
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );
            if ($preset->label !== $label) {
                $preset->label = $label;
                $preset->save();
            }
            $index++;
        }
    }

    private function publicationDate(int $articleNumber): Carbon
    {
        $date = Carbon::create(2026, 5, 14 + $articleNumber, 9, 0, 0);
        $cap = Carbon::create(2026, 5, 20, 9, 0, 0);

        return $date->greaterThan($cap) ? $cap : $date;
    }

    private function patchExistingFoundationArticles(Category $category, Author $author): void
    {
        foreach ([1, 2] as $articleNumber) {
            $blog = Blog::where('slug', $this->articleSlugs[$articleNumber])->first();
            if (! $blog) {
                continue;
            }

            $sources = $this->foundationSources($articleNumber);
            $html = $this->cleanArticleHtml($blog->description ?? '');
            $html = $this->injectExistingArticleCitations($html, $articleNumber, $sources);
            $linkedTopicTargets = [];
            $articleReferenceTargets = $this->articleReferenceTargets(strip_tags($html));
            $linkedArticleTargets = [];
            $html = $this->linkInternalArticleReferences($html, $linkedArticleTargets);
            $html = $this->linkInternalTopicsInHtml($html, $articleNumber, $linkedTopicTargets, $linkedArticleTargets, $articleReferenceTargets);
            $html .= $this->renderSources($articleNumber, $sources);

            $blog->update([
                'category_id' => $category->id,
                'sort_order' => $articleNumber,
                'author_id' => $blog->author_id ?: $author->id,
                'description' => $html,
                'content_attributes' => $this->articleAttributes($articleNumber),
                'tags' => ['Basics', 'Search Engine Fundamentals'],
                'category' => $category->name,
            ]);
        }
    }

    private function foundationSources(int $articleNumber): array
    {
        $sources = match ($articleNumber) {
            1 => [
                $this->sourceItem(1, 'Cleverdon, C. W. (1967). The Cranfield tests on index language devices. Aslib Proceedings, 19(6), 173-194.', 'https://doi.org/10.1108/eb050097'),
                $this->sourceItem(2, 'Salton, G., Wong, A., & Yang, C. S. (1975). A vector space model for automatic indexing. Communications of the ACM, 18(11), 613-620.', 'https://doi.org/10.1145/361219.361220'),
                $this->sourceItem(3, 'Brin, S., & Page, L. (1998). The anatomy of a large-scale hypertextual Web search engine. Computer Networks and ISDN Systems, 30(1-7), 107-117.', 'https://doi.org/10.1016/S0169-7552(98)00110-X'),
                $this->sourceItem(4, 'Manning, C. D., Raghavan, P., & Schütze, H. (2008). Introduction to information retrieval. Cambridge University Press.', 'https://nlp.stanford.edu/IR-book/information-retrieval-book.html'),
            ],
            2 => [
                $this->sourceItem(1, 'Salton, G., Wong, A., & Yang, C. S. (1975). A vector space model for automatic indexing. Communications of the ACM, 18(11), 613-620.', 'https://doi.org/10.1145/361219.361220'),
                $this->sourceItem(2, 'Salton, G., & Buckley, C. (1988). Term-weighting approaches in automatic text retrieval. Information Processing & Management, 24(5), 513-523.', 'https://doi.org/10.1016/0306-4573(88)90021-0'),
                $this->sourceItem(3, 'Robertson, S., & Zaragoza, H. (2009). The probabilistic relevance framework: BM25 and beyond. Foundations and Trends® in Information Retrieval, 3(4), 333-389.', 'https://doi.org/10.1561/1500000019'),
                $this->sourceItem(4, 'Reimers, N., & Gurevych, I. (2019). Sentence-BERT: Sentence embeddings using Siamese BERT-Networks. In K. Inui, J. Jiang, V. Ng, & X. Wan (Eds.), Proceedings of the 2019 Conference on Empirical Methods in Natural Language Processing and the 9th International Joint Conference on Natural Language Processing (EMNLP-IJCNLP) (pp. 3982-3992). Association for Computational Linguistics.', 'https://doi.org/10.18653/v1/D19-1410'),
            ],
            default => [],
        };

        return collect($sources)->keyBy('number')->all();
    }

    private function sourceItem(int $number, string $citation, string $url): array
    {
        return [
            'number' => $number,
            'citation' => $citation,
            'lines' => [$citation],
            'url' => $url,
        ];
    }

    private function cleanArticleHtml(string $html): string
    {
        $html = preg_replace('/<section\s+class="article-sources"[\s\S]*?<\/section>/i', '', $html) ?? $html;
        $html = preg_replace('/<p\s+class="citation-coverage-note"[\s\S]*?<\/p>/i', '', $html) ?? $html;
        $html = preg_replace('/(?<=[A-Za-z0-9\)])(?:Cleverdon|Salton|Brin|Manning|Robertson|Reimers),?[\s\S]{0,1200}?<\/span>\s*<\/a>\s*<\/span>/u', '', $html) ?? $html;
        $html = preg_replace('/<span\s+class="citation-cluster"[\s\S]*?<\/span>\s*<\/a>\s*<\/span>/i', '', $html) ?? $html;
        $html = preg_replace('/<span\s+class="citation-cluster"[\s\S]*?<\/span>/i', '', $html) ?? $html;
        $html = preg_replace('/<a\s+href="\/'.preg_quote(self::SERIES_SLUG, '/').'\/[^"]+">([\s\S]*?)<\/a>/i', '$1', $html) ?? $html;
        $html = preg_replace('/(<span[^>]*font-style:\s*italic[^>]*>)Sources:\s*[\s\S]*?(<\/span>)/i', '$1$2', $html) ?? $html;
        $html = preg_replace('/(?<!<strong>)(Your next step:)(?!<\/strong>)/u', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/(?<!<strong>)(Coming up next:)(?!<\/strong>)/u', '<strong>$1</strong>', $html) ?? $html;

        return trim($html);
    }

    private function injectExistingArticleCitations(string $html, int $articleNumber, array $sources): string
    {
        $targets = match ($articleNumber) {
            1 => [
                'The Cranfield Experiments' => [1],
                'Vector Space Model' => [2],
                'Brin and Page' => [3],
                'Key takeaways' => [4],
            ],
            2 => [
                '1975 paper' => [1],
                'TF-IDF' => [2, 3],
                'BERT-based embedding space' => [4],
            ],
            default => [],
        };

        foreach ($targets as $target => $citations) {
            $html = $this->appendCitationsToFirstMatch($html, $target, $this->citationRefs($articleNumber, $citations, $sources));
        }

        return $html;
    }

    private function appendCitationsToFirstMatch(string $html, string $target, string $citationHtml): string
    {
        if ($citationHtml === '') {
            return $html;
        }

        $pattern = '/(?<![A-Za-z0-9])'.preg_quote($target, '/').'(?![A-Za-z0-9])/iu';
        return $this->replaceFirstOutsideAnchors($html, $pattern, '$0'.$citationHtml);
    }

    private function articleAttributes(int $articleNumber): array
    {
        $labels = [
            1 => ['Foundation', 'Research paper'],
            2 => ['Foundation', 'Technical', 'Research paper', 'Data study', 'Math formula'],
            3 => ['Foundation', 'Technical', 'Research paper', 'Math formula'],
            4 => ['Foundation', 'Technical', 'Research paper', 'Math formula'],
            5 => ['Foundation', 'Technical', 'Research paper', 'Practical'],
            6 => ['Foundation', 'Technical', 'Official doc', 'Data study', 'Practical'],
            7 => ['Foundation', 'Technical', 'Official doc', 'Data study', 'Practical'],
            8 => ['Foundation', 'Technical', 'Research paper', 'Practical'],
            9 => ['Foundation', 'Technical', 'Research paper', 'Data study', 'Math formula'],
            10 => ['Foundation', 'Practical', 'Official doc', 'Data study'],
        ][$articleNumber] ?? ['Foundation'];

        return array_map(fn ($label) => [
            'label' => $label,
            'color' => $this->attributeColor($label),
        ], $labels);
    }

    private function parseArticle(int $articleNumber, array $excerptData): array
    {
        $markdown = $this->readFile($this->contentPath("article{$articleNumber}.md"));
        $lines = $this->lines($markdown);

        $title = trim($lines[0] ?? '');
        $metaDescription = $this->extractMetaDescription($lines);
        $firstSeparator = $this->firstLineIndex($lines, '---');
        $sourceIndex = $this->firstLineIndexMatching($lines, '/^Sources:\s*$/i');
        $sourceLines = $sourceIndex === null ? [] : array_slice($lines, $sourceIndex + 1);
        $sources = $this->parseSources($sourceLines);

        $contentLines = array_slice($lines, ($firstSeparator ?? 0) + 1, $sourceIndex ? $sourceIndex - (($firstSeparator ?? 0) + 1) : null);
        [$bodyLines, $faqLines, $afterFaqLines] = $this->splitFaqs($contentLines);
        $bodyForRendering = array_merge($bodyLines, $afterFaqLines ? ['---'] : [], $afterFaqLines);
        $usedCitations = [];
        $html = $this->renderMarkdownBody($bodyForRendering, $articleNumber, $sources, $usedCitations);
        $html .= $this->renderSources($articleNumber, $sources);

        $excerpt = $excerptData['excerpt'] ?? $this->plainExcerpt($bodyLines);
        $attributes = $this->articleAttributes($articleNumber);

        return [
            'title' => $title,
            'html' => $html,
            'excerpt' => $excerpt,
            'attributes' => $attributes,
            'faqs' => $this->parseFaqs($faqLines),
            'meta_title' => Str::limit($title.' | Search Engine Basics', 512, ''),
            'meta_description' => $metaDescription ?: $excerpt,
        ];
    }

    private function parseExcerptDescriptions(string $path): array
    {
        $lines = $this->lines($this->readFile($path));
        $articles = [];
        $current = null;
        $buffer = [];

        foreach ($lines as $line) {
            if (preg_match('/^1\.(\d+)$/', trim($line), $match)) {
                if ($current !== null) {
                    $articles[$current] = $this->parseExcerptBlock($buffer);
                }
                $current = (int) $match[1];
                $buffer = [];
                continue;
            }

            if ($current !== null) {
                $buffer[] = $line;
            }
        }

        if ($current !== null) {
            $articles[$current] = $this->parseExcerptBlock($buffer);
        }

        return $articles;
    }

    private function parseExcerptBlock(array $lines): array
    {
        $lines = array_values(array_filter(array_map('trim', $lines), fn ($line) => $line !== ''));
        array_shift($lines); // title, already read from the article file
        $excerpt = array_shift($lines) ?? '';

        return [
            'excerpt' => $excerpt,
            'attributes' => array_map(fn ($line) => [
                'label' => $line,
                'color' => $this->attributeColor($line),
            ], $lines),
        ];
    }

    private function splitFaqs(array $lines): array
    {
        $faqStart = $this->firstLineIndexMatching($lines, '/^Frequently Asked Questions\s*$/i');
        if ($faqStart === null) {
            return [$lines, [], []];
        }

        $before = array_slice($lines, 0, $faqStart);
        $afterHeading = array_slice($lines, $faqStart + 1);
        $separator = $this->firstLineIndex($afterHeading, '---');

        if ($separator === null) {
            return [$before, $afterHeading, []];
        }

        return [
            $before,
            array_slice($afterHeading, 0, $separator),
            array_slice($afterHeading, $separator + 1),
        ];
    }

    private function parseFaqs(array $lines): array
    {
        $blocks = $this->paragraphBlocks($lines);
        $faqs = [];

        for ($index = 0; $index + 1 < count($blocks); $index += 2) {
            $question = trim(implode(' ', $blocks[$index]));
            $answer = trim(implode(' ', $blocks[$index + 1]));

            if ($question === '' || $answer === '') {
                continue;
            }

            $faqs[] = [
                'question' => $question,
                'answer' => '<p>'.$this->inline($answer, false).'</p>',
            ];
        }

        return $faqs;
    }

    private function renderMarkdownBody(array $lines, int $articleNumber, array $sources, array &$usedCitations): string
    {
        $html = '';
        $segments = $this->segments($lines);
        $linkedTopicTargets = [];
        $articleReferenceTargets = $this->articleReferenceTargets(implode("\n", $lines));
        $linkedArticleTargets = [];

        foreach ($segments as $index => $segment) {
            $segment = $this->trimEmptyLines($segment);
            if ($segment === []) {
                continue;
            }

            $heading = null;
            if ($index > 0) {
                $heading = trim(array_shift($segment));
                if ($heading !== '') {
                    $html .= '<h2>'.$this->inline($heading, false).'</h2>';
                }
            }

            $citations = $this->citationsForSegment($articleNumber, $index === 0 ? '__intro' : $heading);
            $html .= $this->renderBlocks($segment, $articleNumber, $sources, $citations, $usedCitations, $linkedTopicTargets, $linkedArticleTargets, $articleReferenceTargets);
        }

        return $html;
    }

    private function renderBlocks(array $lines, int $articleNumber, array $sources, array $citations, array &$usedCitations, array &$linkedTopicTargets, array &$linkedArticleTargets, array &$articleReferenceTargets): string
    {
        $html = '';
        $blocks = $this->paragraphBlocks($lines);
        $citationAdded = false;

        foreach ($blocks as $block) {
            if ($this->isTableBlock($block)) {
                $html .= $this->renderTable($block, $articleNumber, $linkedTopicTargets, $linkedArticleTargets, $articleReferenceTargets);
                continue;
            }

            if ($this->isBulletedList($block)) {
                $html .= '<ul>';
                foreach ($block as $line) {
                    $html .= '<li>'.$this->inline(preg_replace('/^\*\s+/', '', $line), true, $articleNumber, $linkedTopicTargets, $linkedArticleTargets, $articleReferenceTargets).'</li>';
                }
                $html .= '</ul>';
                continue;
            }

            if ($this->isNumberedList($block)) {
                $html .= '<ol>';
                foreach ($block as $line) {
                    $html .= '<li>'.$this->inline(preg_replace('/^\d+\.\s+/', '', $line), true, $articleNumber, $linkedTopicTargets, $linkedArticleTargets, $articleReferenceTargets).'</li>';
                }
                $html .= '</ol>';
                continue;
            }

            $text = trim(implode(' ', $block));
            if ($text === '') {
                continue;
            }

            $formulaMarkup = count($block) === 1 ? $this->formulaLineMarkup($text) : null;
            if ($formulaMarkup !== null) {
                $html .= '<div class="custom-block block-equation">'.$formulaMarkup.'</div>';
                continue;
            }

            $citationHtml = '';
            if (! $citationAdded && $citations !== []) {
                $citationHtml = $this->citationRefs($articleNumber, $citations, $sources);
                $usedCitations = array_values(array_unique(array_merge($usedCitations, $citations)));
                $citationAdded = true;
            }

            $html .= '<p>'.$this->inline($text, true, $articleNumber, $linkedTopicTargets, $linkedArticleTargets, $articleReferenceTargets).$citationHtml.'</p>';
        }

        return $html;
    }

    private function renderTable(array $block, int $articleNumber, array &$linkedTopicTargets, array &$linkedArticleTargets, array &$articleReferenceTargets): string
    {
        $rows = array_map(fn ($line) => array_map('trim', explode("\t", $line)), $block);
        $html = '<div><table><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>'.$this->inline($cell, true, $articleNumber, $linkedTopicTargets, $linkedArticleTargets, $articleReferenceTargets).'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</tbody></table></div>';
    }

    private function parseSources(array $lines): array
    {
        $sources = [];
        $current = null;
        $nextNumber = 1;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^(\d+)\.\s+(.+)$/u', $trimmed, $match)) {
                if ($current !== null) {
                    $source = $this->finalizeSource($current);
                    if ($source !== null) {
                        $source['number'] = $nextNumber;
                        $sources[$nextNumber] = $source;
                        $nextNumber++;
                    }
                }

                $current = [
                    'number' => $nextNumber,
                    'lines' => [$match[2]],
                ];
                continue;
            }

            if ($current !== null) {
                $current['lines'][] = $trimmed;
            }
        }

        if ($current !== null) {
            $source = $this->finalizeSource($current);
            if ($source !== null) {
                $source['number'] = $nextNumber;
                $sources[$nextNumber] = $source;
            }
        }

        return $sources;
    }

    private function finalizeSource(array $source): ?array
    {
        $lines = $this->cleanSourceLines($source['lines']);
        if ($lines === [] || $this->shouldDropSource($lines)) {
            return null;
        }

        $text = implode("\n", $lines);
        preg_match('/https?:\/\/[^\s<]+/u', $text, $urlMatch);
        $url = $urlMatch[0] ?? '#source-'.$source['number'];
        $url = rtrim($url, ".,;:)]}*");
        $citation = $this->sourceCitationLine($lines);

        return [
            'number' => $source['number'],
            'citation' => $citation,
            'lines' => $lines,
            'url' => $url,
        ];
    }

    private function cleanSourceLines(array $lines): array
    {
        $clean = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(Corrections?|Correction|Confirmed accurate|Standard caveat|Notes?|Corrections and notes|Important caveat)\b/iu', $line)) {
                continue;
            }
            if (preg_match('/^(The full|Full text also available|Strongly recommended|Add a retrieved date|If you need|This is not a citation|Referenced indirectly)/iu', $line)) {
                continue;
            }
            if (preg_match('/^\((?:Note|Full text|ISBN|The full)/iu', $line)) {
                continue;
            }

            $clean[] = $line;
        }

        return $clean;
    }

    private function shouldDropSource(array $lines): bool
    {
        $text = implode(' ', $lines);
        return str_contains($text, 'Referenced indirectly via') || str_contains($text, 'This is not a citation at all');
    }

    private function sourceCitationLine(array $lines): string
    {
        foreach ($lines as $index => $line) {
            if (preg_match('/https?:\/\//u', $line) && ! preg_match('/\(\d{4}|n\.d\.|United States v\./iu', $line)) {
                continue;
            }

            if (str_contains($line, '—') && isset($lines[$index + 1])) {
                continue;
            }

            return preg_replace('/\s*https?:\/\/[^\s<]+/u', '', $line) ?: $line;
        }

        return $lines[0] ?? 'Source';
    }

    private function renderSources(int $articleNumber, array $sources): string
    {
        if ($sources === []) {
            return '';
        }

        $html = '<section class="article-sources"><h2>Sources</h2><ol>';
        foreach ($sources as $source) {
            $html .= '<li id="source-article-'.$articleNumber.'-'.$source['number'].'">';
            $html .= '<p>'.$this->linkedSourceCitation($source).'</p>';
            $html .= '</li>';
        }

        return $html.'</ol></section>';
    }

    private function linkedSourceCitation(array $source): string
    {
        $citation = $source['citation'] ?? 'Source '.$source['number'];
        $url = $source['url'] ?? '';
        $cleanCitation = preg_replace('/\s*https?:\/\/[^\s<]+/u', '', $citation) ?: $citation;

        if (! $url || str_starts_with($url, '#')) {
            return $this->inline($cleanCitation, false);
        }

        $yearEnd = strpos($cleanCitation, '). ');
        if ($yearEnd === false) {
            return '<a href="'.$this->escapeAttribute($url).'" target="_blank" rel="noopener noreferrer">'.$this->inline($cleanCitation, false).'</a>';
        }

        $titleStart = $yearEnd + 3;
        $titleEnd = strpos($cleanCitation, '. ', $titleStart);
        if ($titleEnd === false) {
            $titleEnd = strlen($cleanCitation) - 1;
        }

        $prefix = substr($cleanCitation, 0, $titleStart);
        $title = trim(substr($cleanCitation, $titleStart, $titleEnd - $titleStart + 1));
        $suffix = substr($cleanCitation, $titleEnd + 1);

        if ($title === '') {
            return $this->inline($cleanCitation, false);
        }

        return $this->inline($prefix, false)
            .'<a href="'.$this->escapeAttribute($url).'" target="_blank" rel="noopener noreferrer">'.$this->inline($title, false).'</a>'
            .$this->inline($suffix, false);
    }

    private function citationRefs(int $articleNumber, array $numbers, array $sources): string
    {
        $refs = '';
        foreach (array_slice(array_values(array_unique($numbers)), 0, 2) as $number) {
            $source = $sources[$number] ?? null;
            if (! $source) {
                continue;
            }

            $href = '#source-article-'.$articleNumber.'-'.$number;
            $refs .= '<a class="citation-ref" href="'.$this->escapeAttribute($href).'">';
            $refs .= '<sup>['.$number.']</sup>';
            $refs .= '<span class="citation-popover"><span class="citation-popover-title">Source '.$number.'</span>'.$this->escape($source['citation']).'</span>';
            $refs .= '</a>';
        }

        return $refs === '' ? '' : '<span class="citation-cluster">'.$refs.'</span>';
    }

    private function citationsForSegment(int $articleNumber, ?string $heading): array
    {
        $plan = $this->citationPlan[$articleNumber] ?? [];
        if ($heading === null) {
            return [];
        }

        if ($heading === '__intro') {
            return $plan['__intro'] ?? [];
        }

        foreach ($plan as $prefix => $citations) {
            if ($prefix === '__intro') {
                continue;
            }
            if (str_starts_with($heading, $prefix)) {
                return $citations;
            }
        }

        return [];
    }

    private function inline(string $text, bool $internalLinks = true, ?int $articleNumber = null, ?array &$linkedTopicTargets = null, ?array &$linkedArticleTargets = null, ?array &$articleReferenceTargets = null): string
    {
        $escaped = $this->escape($text);
        $escaped = preg_replace('/\*\*(.*?)\*\*/u', '<strong>$1</strong>', $escaped);
        $escaped = preg_replace('/\*(.*?)\*/u', '<em>$1</em>', $escaped);
        $escaped = preg_replace('/\b(Your next step:|Coming up next:)/u', '<strong>$1</strong>', $escaped);
        if ($internalLinks) {
            $topicTargets = &$this->referenceArray($linkedTopicTargets);
            $articleTargets = &$this->referenceArray($linkedArticleTargets);
            $referenceTargets = &$this->referenceArray($articleReferenceTargets);
            $escaped = $this->linkInternalArticleReferences($escaped, $articleTargets);
            $escaped = $this->linkInternalTopicsInHtml($escaped, $articleNumber, $topicTargets, $articleTargets, $referenceTargets);
        }
        $escaped = $this->linkifyExternal($escaped);

        return $escaped;
    }

    private function &referenceArray(?array &$array): array
    {
        static $fallback = [];
        if ($array === null) {
            $fallback = [];
            return $fallback;
        }

        return $array;
    }

    private function articleReferenceTargets(string $text): array
    {
        preg_match_all('/1\.(10|[1-9])/u', $text, $matches);
        $targets = [];
        foreach ($matches[1] ?? [] as $number) {
            $targets[(int) $number] = true;
        }

        return $targets;
    }

    private function linkInternalArticleReferences(string $html, array &$linkedArticleTargets): string
    {
        return preg_replace_callback('/\b([Aa]rticles?)\s+((?:1\.(?:10|[1-9])(?:\s*(?:,|and|through|to|-)\s*)?)+)/u', function ($match) use (&$linkedArticleTargets) {
            return $match[1].' '.$this->linkArticleNumbers($match[2], $linkedArticleTargets);
        }, $html);
    }

    private function linkInternalTopicsInHtml(string $html, ?int $currentArticleNumber = null, ?array &$linkedTopicTargets = null, ?array &$linkedArticleTargets = null, ?array &$articleReferenceTargets = null): string
    {
        $topicTargets = &$this->referenceArray($linkedTopicTargets);
        $articleTargets = &$this->referenceArray($linkedArticleTargets);
        $referenceTargets = &$this->referenceArray($articleReferenceTargets);
        $topics = [
            'Search Quality Rater Guidelines' => 10,
            'Google Search Console' => 10,
            'TF-IDF and BM25' => 3,
            'Vector Space Model' => 2,
            'learning-to-rank' => 8,
            'Learning to rank' => 8,
            'LambdaMART' => 8,
            'LambdaRank' => 8,
            'RankNet' => 8,
            'Knowledge Graph' => 7,
            'Hummingbird' => 7,
            'entity salience' => 7,
            'crawl-index-rank pipeline' => 6,
            'crawl, index, and rank' => 6,
            'HITS algorithm' => 5,
            'HITS' => 5,
            'PageRank' => 4,
            'TF-IDF' => 3,
            'BM25' => 3,
            'NDCG' => 9,
            'MRR' => 9,
            'MAP' => 9,
            'E-E-A-T' => 10,
        ];

        foreach ($topics as $phrase => $articleNumber) {
            if ($currentArticleNumber === $articleNumber) {
                continue;
            }
            if (isset($topicTargets[$articleNumber]) || isset($articleTargets[$articleNumber]) || isset($referenceTargets[$articleNumber])) {
                continue;
            }

            $slug = $this->articleSlugs[$articleNumber] ?? null;
            if (! $slug) {
                continue;
            }

            $updated = $this->replaceFirstOutsideAnchors(
                $html,
                '/(?<![A-Za-z0-9])'.preg_quote($phrase, '/').'(?![A-Za-z0-9])/iu',
                '<a href="/'.self::SERIES_SLUG.'/'.$slug.'">$0</a>'
            );
            if ($updated !== $html) {
                $topicTargets[$articleNumber] = true;
                $html = $updated;
            }
        }

        return $html;
    }

    private function replaceFirstOutsideAnchors(string $html, string $pattern, string $replacement): string
    {
        $parts = preg_split(
            '/(<h[1-3]\b[\s\S]*?<\/h[1-3]>|<span\s+class="citation-cluster"[\s\S]*?<\/span>\s*<\/a>\s*<\/span>|<a\b[^>]*>.*?<\/a>)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );
        if ($parts === false) {
            return $html;
        }

        $replaced = false;
        foreach ($parts as $index => $part) {
            if ($replaced || preg_match('/^(<a\b|<h[1-3]\b|<span\s+class="citation-cluster")/i', $part)) {
                continue;
            }

            $next = preg_replace($pattern, $replacement, $part, 1, $count);
            if ($count > 0 && $next !== null) {
                $parts[$index] = $next;
                $replaced = true;
            }
        }

        return implode('', $parts);
    }

    private function linkArticleNumbers(string $text, array &$linkedArticleTargets): string
    {
        return preg_replace_callback('/1\.(10|[1-9])/u', function ($match) use (&$linkedArticleTargets) {
            $number = (int) $match[1];
            $slug = $this->articleSlugs[$number] ?? null;
            if (! $slug || isset($linkedArticleTargets[$number])) {
                return $match[0];
            }

            $linkedArticleTargets[$number] = true;
            return '<a href="/'.self::SERIES_SLUG.'/'.$slug.'">'.$match[0].'</a>';
        }, $text);
    }

    private function linkifyExternal(string $html): string
    {
        return preg_replace_callback('/https?:\/\/[^\s<]+/u', function ($match) {
            $url = rtrim($match[0], ".,;:)]}*");
            $tail = substr($match[0], strlen($url));

            return '<a href="'.$this->escapeAttribute($url).'" target="_blank" rel="noopener noreferrer">'.$this->escape($url).'</a>'.$this->escape($tail);
        }, $html);
    }

    private function segments(array $lines): array
    {
        $segments = [[]];
        foreach ($lines as $line) {
            if (trim($line) === '---') {
                $segments[] = [];
                continue;
            }

            $segments[count($segments) - 1][] = $line;
        }

        return $segments;
    }

    private function paragraphBlocks(array $lines): array
    {
        $blocks = [];
        $current = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                if ($current !== []) {
                    $blocks[] = $current;
                    $current = [];
                }
                continue;
            }

            $current[] = $line;
        }

        if ($current !== []) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    private function trimEmptyLines(array $lines): array
    {
        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }
        while ($lines !== [] && trim($lines[count($lines) - 1]) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    private function isTableBlock(array $block): bool
    {
        return count($block) > 1 && collect($block)->every(fn ($line) => str_contains($line, "\t"));
    }

    private function isBulletedList(array $block): bool
    {
        return collect($block)->every(fn ($line) => preg_match('/^\*\s+/', trim($line)));
    }

    private function isNumberedList(array $block): bool
    {
        return collect($block)->every(fn ($line) => preg_match('/^\d+\.\s+/', trim($line)));
    }

    private function isFormulaLine(string $line): bool
    {
        return $this->formulaLineMarkup($line) !== null;
    }

    private function formulaLineMarkup(string $line): ?string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
        $plain = strtr($plain, [
            "\u{00A0}" => ' ',
            "\u{00B7}" => '*',
            "\u{00D7}" => 'x',
            "\u{2212}" => '-',
            "\u{2013}" => '-',
            "\u{2014}" => '-',
            'Ã—' => 'x',
            'Î»' => 'λ',
            'Î”' => 'Δ',
        ]);

        if (preg_match('/^(?:BM25|TF-IDF)\s+scores\b/iu', $plain)) {
            return null;
        }

        if (preg_match('/^cosine similarity\s*=/iu', $plain)) {
            return '\[\text{cosine similarity}=\frac{A\cdot B}{\lVert A\rVert\times\lVert B\rVert}\]';
        }

        if (preg_match('/^BM25\(t\s*,\s*d\)\s*=/iu', $plain)) {
            return '\[\mathrm{BM25}(t,d)=\mathrm{IDF}(t)\times\frac{\mathrm{TF}(t,d)(k_1+1)}{\mathrm{TF}(t,d)+k_1\left(1-b+b\frac{|d|}{\mathrm{avgdl}}\right)}\]';
        }

        if (preg_match('/^pseudo-?TF\(t\s*,\s*d\)\s*=/iu', $plain)) {
            return '\[\mathrm{pseudoTF}(t,d)=\sum_f w_f\times\frac{\mathrm{TF}(t,f)}{\mathrm{lengthNorm}_f}\]';
        }

        if (preg_match('/^PR\(i\)\s*=/iu', $plain)) {
            return '\[\mathrm{PR}(i)=\frac{1-d}{N}+d\sum_{j\in M(i)}\frac{\mathrm{PR}(j)}{L(j)}\]';
        }

        if (preg_match('/^authority\(i\)\s*=/iu', $plain)) {
            return '\[\mathrm{authority}(i)=\sum_{j\to i}\mathrm{hub}(j)\]';
        }

        if (preg_match('/^hub\(i\)\s*=/iu', $plain)) {
            return '\[\mathrm{hub}(i)=\sum_{i\to j}\mathrm{authority}(j)\]';
        }

        if (preg_match('/^(?:λ|lambda)_?ij\s*=/iu', $plain)) {
            return '\[\lambda_{ij}=|\Delta\mathrm{NDCG}|\times\text{pairwise gradient}_{ij}^{\mathrm{RankNet}}\]';
        }

        if (preg_match('/^Precision\s*=/iu', $plain)) {
            return '\[\mathrm{Precision}=\frac{\text{number of relevant documents retrieved}}{\text{total documents retrieved}}\]';
        }

        if (preg_match('/^Recall\s*=/iu', $plain)) {
            return '\[\mathrm{Recall}=\frac{\text{number of relevant documents retrieved}}{\text{total relevant documents in collection}}\]';
        }

        if (preg_match('/^Precision@K\s*=/iu', $plain)) {
            return '\[\mathrm{Precision@K}=\frac{\text{number of relevant documents in top }K\text{ positions}}{K}\]';
        }

        if (preg_match('/^AP\s*=\s*\(\s*1\s*\/\s*R\s*\)/iu', $plain)) {
            return '\[\mathrm{AP}=\frac{1}{R}\sum_i \mathrm{Precision@K_i}\]';
        }

        if (preg_match('/^AP\s*=\s*\(\s*1\s*\/\s*4\s*\)/iu', $plain)) {
            return '\[\mathrm{AP}=\frac{1}{4}(1.00+0.67+0.60+0.44)=\frac{1}{4}\times2.71=0.678\]';
        }

        if (str_contains($plain, 'MAP across both queries')) {
            return '\[\mathrm{MAP}=\frac{0.678+0.510}{2}=0.594\]';
        }

        if (preg_match('/^RR\s*=/iu', $plain)) {
            return '\[\mathrm{RR}=\frac{1}{\text{rank position of the first relevant result}}\]';
        }

        if (preg_match('/^MRR\s*=/iu', $plain)) {
            return '\[\mathrm{MRR}=\frac{1.00+0.33+0.50}{3}=0.61\]';
        }

        if (preg_match('/^DCG@K\s*=/iu', $plain) && str_contains($plain, '2^')) {
            return '\[\mathrm{DCG@K}=\sum_{i=1}^{K}\frac{2^{rel_i}-1}{\log_2(i+1)}\]';
        }

        if (preg_match('/^DCG@K\s*=/iu', $plain)) {
            return '\[\mathrm{DCG@K}=\sum_{i=1}^{K}\frac{rel_i}{\log_2(i+1)}\]';
        }

        if (preg_match('/^NDCG@K\s*=/iu', $plain)) {
            return '\[\mathrm{NDCG@K}=\frac{\mathrm{DCG@K}}{\mathrm{IDCG@K}}\]';
        }

        if (preg_match('/^DCG@5\s*=/iu', $plain)) {
            return '\[\mathrm{DCG@5}=2.000+1.893+0.000+0.431+1.161=5.485\]';
        }

        if (preg_match('/^IDCG@5\s*=/iu', $plain)) {
            return '\[\mathrm{IDCG@5}=3.000+1.893+1.000+0.431+0.000=6.324\]';
        }

        if (preg_match('/^NDCG@5\s*=/iu', $plain)) {
            return '\[\mathrm{NDCG@5}=\frac{5.485}{6.324}=0.867\]';
        }

        return null;
    }

    private function extractMetaDescription(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/^Meta description:\s*(.*?)\s*(?:\(\d+\s*chars\))?\s*$/iu', trim($line), $match)) {
                return trim($match[1]);
            }
        }

        return null;
    }

    private function plainExcerpt(array $lines): string
    {
        foreach ($this->paragraphBlocks($lines) as $block) {
            $text = trim(implode(' ', $block));
            if ($text !== '' && $text !== '---') {
                return Str::limit($text, 320);
            }
        }

        return '';
    }

    private function estimateReadTime(string $html): string
    {
        $words = str_word_count(strip_tags($html));

        return max(1, (int) ceil($words / 220)).' min';
    }

    private function attributeColor(string $label): string
    {
        if (isset($this->presetColors[$label])) {
            return $this->presetColors[$label];
        }

        if (str_contains($label, 'Google') || str_contains($label, 'Guidelines') || str_contains($label, 'Official')) {
            return '#005BBB';
        }

        if (preg_match('/\b(19|20)\d{2}\b/', $label)) {
            return '#fbff00';
        }

        return '#B8FF35';
    }

    private function contentPath(string $file): string
    {
        return database_path('seeders/content/basics/'.$file);
    }

    private function readFile(string $path): string
    {
        return str_replace(["\r\n", "\r"], "\n", file_get_contents($path) ?: '');
    }

    private function lines(string $text): array
    {
        return explode("\n", $text);
    }

    private function firstLineIndex(array $lines, string $needle): ?int
    {
        foreach ($lines as $index => $line) {
            if (trim($line) === $needle) {
                return $index;
            }
        }

        return null;
    }

    private function firstLineIndexMatching(array $lines, string $pattern): ?int
    {
        foreach ($lines as $index => $line) {
            if (preg_match($pattern, trim($line))) {
                return $index;
            }
        }

        return null;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
