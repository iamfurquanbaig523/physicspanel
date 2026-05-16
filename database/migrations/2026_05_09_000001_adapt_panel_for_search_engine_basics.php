<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('authors')) {
            Schema::create('authors', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('role')->nullable();
                $table->text('bio')->nullable();
                $table->string('email')->nullable();
                $table->string('avatar', 512)->nullable();
                $table->string('website_url')->nullable();
                $table->json('social_links')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }

        $defaultAuthorId = DB::table('authors')->where('slug', 'search-engine-basics-team')->value('id');
        if (! $defaultAuthorId) {
            $defaultAuthorId = DB::table('authors')->insertGetId([
                'name' => 'Search Engine Basics Team',
                'slug' => 'search-engine-basics-team',
                'role' => 'Editorial Team',
                'bio' => 'The editorial team behind Search Engine Basics.',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('blogs')) {
            Schema::table('blogs', function (Blueprint $table) {
                if (! Schema::hasColumn('blogs', 'author_id')) {
                    $table->foreignId('author_id')->nullable()->after('id')->constrained('authors')->nullOnDelete();
                }
                if (! Schema::hasColumn('blogs', 'excerpt')) {
                    $table->text('excerpt')->nullable()->after('description');
                }
                if (! Schema::hasColumn('blogs', 'category')) {
                    $table->string('category', 120)->nullable()->after('tags');
                }
                if (! Schema::hasColumn('blogs', 'read_time')) {
                    $table->string('read_time', 40)->nullable()->after('category');
                }
                if (! Schema::hasColumn('blogs', 'accent_color')) {
                    $table->string('accent_color', 20)->default('#B8FF35')->after('read_time');
                }
                if (! Schema::hasColumn('blogs', 'is_featured')) {
                    $table->boolean('is_featured')->default(false)->after('accent_color');
                }
                if (! Schema::hasColumn('blogs', 'status')) {
                    $table->string('status', 40)->default('published')->after('is_featured');
                }
                if (! Schema::hasColumn('blogs', 'published_at')) {
                    $table->timestamp('published_at')->nullable()->after('status');
                }
                if (! Schema::hasColumn('blogs', 'meta_title')) {
                    $table->string('meta_title', 512)->nullable()->after('published_at');
                }
                if (! Schema::hasColumn('blogs', 'meta_description')) {
                    $table->text('meta_description')->nullable()->after('meta_title');
                }
            });

            Schema::table('blogs', function (Blueprint $table) {
                if (Schema::hasColumn('blogs', 'image')) {
                    $table->string('image', 512)->nullable()->change();
                }
            });

            DB::table('blogs')->whereNull('author_id')->update(['author_id' => $defaultAuthorId]);
            DB::table('blogs')->whereNull('published_at')->update(['published_at' => DB::raw('created_at')]);
        }

        if (! Schema::hasTable('company_pages')) {
            Schema::create('company_pages', function (Blueprint $table) {
                $table->id();
                $table->string('page_key')->unique();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('excerpt')->nullable();
                $table->longText('content')->nullable();
                $table->string('meta_title', 512)->nullable();
                $table->text('meta_description')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });
        }

        foreach ($this->defaultCompanyPages() as $page) {
            DB::table('company_pages')->updateOrInsert(
                ['page_key' => $page['page_key']],
                array_merge($page, ['updated_at' => now(), 'created_at' => now()])
            );
        }

        if (! Schema::hasTable('newsletter_subscribers')) {
            Schema::create('newsletter_subscribers', function (Blueprint $table) {
                $table->id();
                $table->string('email')->unique();
                $table->string('name')->nullable();
                $table->string('source')->nullable();
                $table->string('status', 40)->default('subscribed');
                $table->timestamp('subscribed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('search_queries')) {
            Schema::create('search_queries', function (Blueprint $table) {
                $table->id();
                $table->string('query');
                $table->string('page')->nullable();
                $table->string('source')->nullable();
                $table->unsignedInteger('results_count')->default(0);
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        DB::table('settings')->updateOrInsert(
            ['name' => 'company_name'],
            ['value' => 'Search Engine Basics', 'type' => 'string']
        );
        DB::table('settings')->updateOrInsert(
            ['name' => 'website_url'],
            ['value' => 'https://searchenginebasics.io', 'type' => 'string']
        );
        DB::table('settings')->updateOrInsert(
            ['name' => 'company_email'],
            ['value' => 'hello@searchenginebasics.io', 'type' => 'string']
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('blogs')) {
            Schema::table('blogs', function (Blueprint $table) {
                if (Schema::hasColumn('blogs', 'author_id')) {
                    $table->dropConstrainedForeignId('author_id');
                }

                foreach ([
                    'excerpt',
                    'category',
                    'read_time',
                    'accent_color',
                    'is_featured',
                    'status',
                    'published_at',
                    'meta_title',
                    'meta_description',
                ] as $column) {
                    if (Schema::hasColumn('blogs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('search_queries');
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('company_pages');
        Schema::dropIfExists('authors');
    }

    private function defaultCompanyPages(): array
    {
        return [
            [
                'page_key' => 'about-us',
                'title' => 'About Search Engine Basics',
                'slug' => 'about-us',
                'excerpt' => 'Clear, practical education about how search engines discover, index, and rank the web.',
                'content' => '<p>Search Engine Basics teaches the systems behind search: crawling, indexing, ranking, and the technical decisions that help pages become discoverable.</p><p>Our goal is to make SEO fundamentals easier to understand without turning them into vague checklists.</p>',
                'meta_title' => 'About Search Engine Basics',
                'meta_description' => 'Learn why Search Engine Basics exists and how it teaches SEO from first principles.',
                'status' => true,
                'published_at' => now(),
            ],
            [
                'page_key' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'excerpt' => 'How Search Engine Basics handles visitor messages, newsletter signups, and analytics data.',
                'content' => '<p>Search Engine Basics collects only the information needed to respond to messages, manage newsletter subscriptions, and improve the learning experience.</p><p>You can contact us at hello@searchenginebasics.io for privacy questions.</p>',
                'meta_title' => 'Privacy Policy | Search Engine Basics',
                'meta_description' => 'Privacy information for Search Engine Basics visitors and subscribers.',
                'status' => true,
                'published_at' => now(),
            ],
            [
                'page_key' => 'terms-and-conditions',
                'title' => 'Terms and Conditions',
                'slug' => 'terms-and-conditions',
                'excerpt' => 'The basic terms for using Search Engine Basics content and learning resources.',
                'content' => '<p>Search Engine Basics provides educational content for general learning. You are responsible for how you apply any advice to your own websites or projects.</p>',
                'meta_title' => 'Terms and Conditions | Search Engine Basics',
                'meta_description' => 'Terms for using Search Engine Basics.',
                'status' => true,
                'published_at' => now(),
            ],
            [
                'page_key' => 'contact-us',
                'title' => 'Contact Search Engine Basics',
                'slug' => 'contact-us',
                'excerpt' => 'Send questions, corrections, feedback, or collaboration ideas.',
                'content' => '<p>Use the contact form to send questions, correction requests, article ideas, or partnership notes. We read every message.</p>',
                'meta_title' => 'Contact Search Engine Basics',
                'meta_description' => 'Contact Search Engine Basics with questions, feedback, and collaboration requests.',
                'status' => true,
                'published_at' => now(),
            ],
        ];
    }
};
