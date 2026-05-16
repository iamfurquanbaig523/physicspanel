<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lesson_modules')) {
            Schema::create('lesson_modules', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('color', 20)->default('#B8FF35');
                $table->integer('sort_order')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('blogs')) {
            Schema::table('blogs', function (Blueprint $table) {
                if (! Schema::hasColumn('blogs', 'content_type')) {
                    $table->string('content_type', 40)->default('blog')->after('id');
                }
                if (! Schema::hasColumn('blogs', 'module_id')) {
                    $table->foreignId('module_id')->nullable()->after('content_type')
                          ->constrained('lesson_modules')->nullOnDelete();
                }
                if (! Schema::hasColumn('blogs', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('module_id');
                }
                if (! Schema::hasColumn('blogs', 'difficulty')) {
                    $table->string('difficulty', 20)->nullable()->after('sort_order');
                }
            });
        }

        $modules = [
            ['title' => 'Search Engine Fundamentals', 'slug' => 'search-engine-fundamentals', 'color' => '#B8FF35', 'sort_order' => 1],
            ['title' => 'Crawling', 'slug' => 'crawling', 'color' => '#4A4AFF', 'sort_order' => 2],
            ['title' => 'Indexing', 'slug' => 'indexing', 'color' => '#FF6B6B', 'sort_order' => 3],
            ['title' => 'Ranking', 'slug' => 'ranking', 'color' => '#FF9F43', 'sort_order' => 4],
        ];

        foreach ($modules as $module) {
            DB::table('lesson_modules')->updateOrInsert(
                ['slug' => $module['slug']],
                array_merge($module, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('blogs')) {
            Schema::table('blogs', function (Blueprint $table) {
                if (Schema::hasColumn('blogs', 'module_id')) {
                    $table->dropConstrainedForeignId('module_id');
                }
                foreach (['content_type', 'sort_order', 'difficulty'] as $col) {
                    if (Schema::hasColumn('blogs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('lesson_modules');
    }
};
