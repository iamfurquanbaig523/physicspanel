<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename lesson_modules to chapters
        if (Schema::hasTable('lesson_modules') && !Schema::hasTable('chapters')) {
            Schema::rename('lesson_modules', 'chapters');
        }

        // 2. Rename curriculums to series
        if (Schema::hasTable('curriculums') && !Schema::hasTable('series')) {
            Schema::rename('curriculums', 'series');
        }

        // 3. Rename curriculum_items to series_items and update column & foreign key
        if (Schema::hasTable('curriculum_items') && !Schema::hasTable('series_items')) {
            Schema::table('curriculum_items', function (Blueprint $table) {
                // Constraints do not exist in the current db state.
                $table->renameColumn('curriculum_id', 'series_id');
            });
            
            Schema::rename('curriculum_items', 'series_items');
            
            Schema::table('series_items', function (Blueprint $table) {
                $table->foreign('series_id')->references('id')->on('series')->cascadeOnDelete();
                $table->unique(['series_id', 'item_type', 'item_id'], 'series_item_unique');
            });
        }

        // 4. Update data in series_items (item_type polymorphic values)
        if (Schema::hasTable('series_items')) {
            DB::table('series_items')->where('item_type', 'module')->update(['item_type' => 'chapter']);
            DB::table('series_items')->where('item_type', 'lesson')->update(['item_type' => 'article']);
        }

        // 5. Rename module_lesson to chapter_blog and update columns
        if (Schema::hasTable('module_lesson') && !Schema::hasTable('chapter_blog')) {
            Schema::table('module_lesson', function (Blueprint $table) {
                $table->dropForeign(['module_id']);
                $table->dropForeign(['lesson_id']);
                $table->dropUnique(['module_id', 'lesson_id']);

                $table->renameColumn('module_id', 'chapter_id');
                $table->renameColumn('lesson_id', 'blog_id');
            });
            
            Schema::rename('module_lesson', 'chapter_blog');

            Schema::table('chapter_blog', function (Blueprint $table) {
                $table->foreign('chapter_id')->references('id')->on('chapters')->cascadeOnDelete();
                $table->foreign('blog_id')->references('id')->on('blogs')->cascadeOnDelete();
                $table->unique(['chapter_id', 'blog_id']);
            });
        }

        // 6. Update blogs table content_type
        if (Schema::hasTable('blogs')) {
            DB::table('blogs')->where('content_type', 'lesson')->update(['content_type' => 'article']);
        }
    }

    public function down(): void
    {
        // Reverse operations
        if (Schema::hasTable('blogs')) {
            DB::table('blogs')->where('content_type', 'article')->update(['content_type' => 'lesson']);
        }

        if (Schema::hasTable('chapter_blog') && !Schema::hasTable('module_lesson')) {
            Schema::table('chapter_blog', function (Blueprint $table) {
                $table->dropForeign(['chapter_id']);
                $table->dropForeign(['blog_id']);
                $table->dropUnique(['chapter_id', 'blog_id']);
                
                $table->renameColumn('chapter_id', 'module_id');
                $table->renameColumn('blog_id', 'lesson_id');
            });

            Schema::rename('chapter_blog', 'module_lesson');

            Schema::table('module_lesson', function (Blueprint $table) {
                $table->foreign('module_id')->references('id')->on('lesson_modules')->cascadeOnDelete();
                $table->foreign('lesson_id')->references('id')->on('blogs')->cascadeOnDelete();
                $table->unique(['module_id', 'lesson_id']);
            });
        }

        if (Schema::hasTable('series_items')) {
            DB::table('series_items')->where('item_type', 'chapter')->update(['item_type' => 'module']);
            DB::table('series_items')->where('item_type', 'article')->update(['item_type' => 'lesson']);
        }

        if (Schema::hasTable('series_items') && !Schema::hasTable('curriculum_items')) {
            Schema::table('series_items', function (Blueprint $table) {
                $table->dropForeign(['series_id']);
                $table->dropUnique('series_item_unique');

                $table->renameColumn('series_id', 'curriculum_id');
            });

            Schema::rename('series_items', 'curriculum_items');

            Schema::table('curriculum_items', function (Blueprint $table) {
                $table->foreign('curriculum_id')->references('id')->on('curriculums')->cascadeOnDelete();
                $table->unique(['curriculum_id', 'item_type', 'item_id'], 'curr_item_unique');
            });
        }

        if (Schema::hasTable('series') && !Schema::hasTable('curriculums')) {
            Schema::rename('series', 'curriculums');
        }

        if (Schema::hasTable('chapters') && !Schema::hasTable('lesson_modules')) {
            Schema::rename('chapters', 'lesson_modules');
        }
    }
};
