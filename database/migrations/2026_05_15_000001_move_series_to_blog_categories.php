<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (! Schema::hasColumn('categories', 'series_title')) {
                    $table->string('series_title', 255)->nullable()->after('name');
                }
                if (! Schema::hasColumn('categories', 'series_description')) {
                    $table->text('series_description')->nullable()->after('description');
                }
                if (! Schema::hasColumn('categories', 'series_content')) {
                    $table->longText('series_content')->nullable()->after('series_description');
                }
                if (! Schema::hasColumn('categories', 'icon')) {
                    $table->text('icon')->nullable()->after('image');
                }
                if (! Schema::hasColumn('categories', 'accent_color')) {
                    $table->string('accent_color', 20)->default('#B8FF35')->after('icon');
                }
                if (! Schema::hasColumn('categories', 'show_in_header_nav')) {
                    $table->boolean('show_in_header_nav')->default(false)->after('status');
                }
                if (! Schema::hasColumn('categories', 'header_nav_order')) {
                    $table->integer('header_nav_order')->default(0)->after('show_in_header_nav');
                }
                if (! Schema::hasColumn('categories', 'show_in_mobile_nav')) {
                    $table->boolean('show_in_mobile_nav')->default(false)->after('header_nav_order');
                }
                if (! Schema::hasColumn('categories', 'mobile_nav_order')) {
                    $table->integer('mobile_nav_order')->default(0)->after('show_in_mobile_nav');
                }
                if (! Schema::hasColumn('categories', 'meta_title')) {
                    $table->string('meta_title', 512)->nullable()->after('mobile_nav_order');
                }
                if (! Schema::hasColumn('categories', 'meta_description')) {
                    $table->text('meta_description')->nullable()->after('meta_title');
                }
            });

            try {
                DB::statement('ALTER TABLE categories MODIFY image VARCHAR(191) NULL');
            } catch (Throwable) {
                // Some database engines or installs may already allow null images.
            }
        }

        if (Schema::hasTable('blogs')) {
            Schema::table('blogs', function (Blueprint $table) {
                if (! Schema::hasColumn('blogs', 'category_id')) {
                    $table->foreignId('category_id')->nullable()->after('author_id')->constrained('categories')->nullOnDelete();
                }
                if (! Schema::hasColumn('blogs', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('category_id');
                }
                if (! Schema::hasColumn('blogs', 'content_attributes')) {
                    $table->json('content_attributes')->nullable()->after('accent_color');
                }
            });
        }

        $this->copySeriesIntoCategories();
        $this->removeLessonColumnsAndTables();
    }

    public function down(): void
    {
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                foreach ([
                    'series_title',
                    'series_description',
                    'series_content',
                    'icon',
                    'accent_color',
                    'show_in_header_nav',
                    'header_nav_order',
                    'show_in_mobile_nav',
                    'mobile_nav_order',
                    'meta_title',
                    'meta_description',
                ] as $column) {
                    if (Schema::hasColumn('categories', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('blogs')) {
            Schema::table('blogs', function (Blueprint $table) {
                if (Schema::hasColumn('blogs', 'category_id')) {
                    $table->dropConstrainedForeignId('category_id');
                }
                if (Schema::hasColumn('blogs', 'content_attributes')) {
                    $table->dropColumn('content_attributes');
                }
            });
        }
    }

    private function copySeriesIntoCategories(): void
    {
        if (! Schema::hasTable('series') || ! Schema::hasTable('categories')) {
            return;
        }

        $seriesRows = DB::table('series')->orderBy('nav_order')->orderBy('id')->get();

        foreach ($seriesRows as $series) {
            $categoryId = DB::table('categories')->where('slug', $series->slug)->value('id');

            $payload = [
                'name' => $series->title,
                'series_title' => $series->title,
                'description' => $series->description,
                'series_description' => $series->description,
                'series_content' => $series->content ?? null,
                'image' => $series->image ?: null,
                'icon' => $series->icon ?? null,
                'status' => (bool) ($series->status ?? true),
                'show_in_header_nav' => (bool) ($series->show_in_nav ?? false),
                'header_nav_order' => (int) ($series->nav_order ?? 0),
                'show_in_mobile_nav' => (bool) ($series->show_in_nav ?? false),
                'mobile_nav_order' => (int) ($series->nav_order ?? 0),
                'meta_title' => $series->meta_title ?? null,
                'meta_description' => $series->meta_description ?? null,
                'updated_at' => now(),
            ];

            if ($categoryId) {
                DB::table('categories')->where('id', $categoryId)->update($payload);
            } else {
                $categoryId = DB::table('categories')->insertGetId(array_merge($payload, [
                    'slug' => $series->slug,
                    'created_at' => now(),
                ]));
            }

            if (! Schema::hasTable('series_items') || ! Schema::hasTable('blogs')) {
                continue;
            }

            $items = DB::table('series_items')
                ->where('series_id', $series->id)
                ->orderBy('sort_order')
                ->get();

            foreach ($items as $item) {
                DB::table('blogs')
                    ->where('id', $item->item_id)
                    ->update([
                        'category_id' => $categoryId,
                        'category' => $series->title,
                        'sort_order' => (int) ($item->sort_order ?? 0),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    private function removeLessonColumnsAndTables(): void
    {
        if (Schema::hasTable('blogs')) {
            if (Schema::hasColumn('blogs', 'module_id')) {
                try {
                    Schema::table('blogs', function (Blueprint $table) {
                        $table->dropForeign(['module_id']);
                    });
                } catch (Throwable) {
                    // Some installs already removed the foreign key during the previous architecture migration.
                }

                Schema::table('blogs', function (Blueprint $table) {
                    if (Schema::hasColumn('blogs', 'module_id')) {
                        $table->dropColumn('module_id');
                    }
                });
            }

            Schema::table('blogs', function (Blueprint $table) {
                foreach (['content_type', 'difficulty'] as $column) {
                    if (Schema::hasColumn('blogs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('chapter_blog');
        Schema::dropIfExists('module_lesson');
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('lesson_modules');
        Schema::dropIfExists('series_items');
        Schema::dropIfExists('series');
    }
};
