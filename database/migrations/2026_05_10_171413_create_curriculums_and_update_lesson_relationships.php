<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create Curriculums table
        if (!Schema::hasTable('curriculums')) {
            Schema::create('curriculums', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }

        // 2. Create polymorphic pivot for curriculum items (Modules or Lessons)
        if (!Schema::hasTable('curriculum_items')) {
            Schema::create('curriculum_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('curriculum_id')->constrained('curriculums')->cascadeOnDelete();
                $table->string('item_type'); // 'module' or 'lesson'
                $table->unsignedBigInteger('item_id'); // lesson_modules.id or blogs.id
                $table->integer('sort_order')->default(0);
                
                $table->index(['item_type', 'item_id']);
                $table->unique(['curriculum_id', 'item_type', 'item_id'], 'curr_item_unique');
            });
        }

        // 3. Create pivot table for module -> lessons
        if (!Schema::hasTable('module_lesson')) {
            Schema::create('module_lesson', function (Blueprint $table) {
                $table->id();
                $table->foreignId('module_id')->constrained('lesson_modules')->cascadeOnDelete();
                $table->foreignId('lesson_id')->constrained('blogs')->cascadeOnDelete();
                $table->integer('sort_order')->default(0);

                $table->unique(['module_id', 'lesson_id']);
            });
        }

        // 4. Data Migration
        // 4a. Create default curriculum
        $curriculumId = DB::table('curriculums')->insertGetId([
            'title' => 'Search Engine Fundamentals',
            'slug' => 'search-engine-fundamentals',
            'description' => 'The complete guide to understanding search engines.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4b. Attach existing modules to the default curriculum
        $modules = DB::table('lesson_modules')->orderBy('sort_order')->get();
        foreach ($modules as $module) {
            DB::table('curriculum_items')->insert([
                'curriculum_id' => $curriculumId,
                'item_type' => 'module',
                'item_id' => $module->id,
                'sort_order' => $module->sort_order,
            ]);
        }

        // 4c. Migrate existing lesson relationships from `blogs` to `module_lesson`
        $lessons = DB::table('blogs')
            ->whereNotNull('module_id')
            ->where('content_type', 'lesson')
            ->get();

        foreach ($lessons as $lesson) {
            DB::table('module_lesson')->updateOrInsert(
                ['module_id' => $lesson->module_id, 'lesson_id' => $lesson->id],
                ['sort_order' => $lesson->sort_order]
            );
        }

        // 5. Drop old columns from blogs
        if (Schema::hasColumn('blogs', 'module_id')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropForeign(['module_id']);
                $table->dropColumn('module_id');
            });
        }
        
        if (Schema::hasColumn('blogs', 'sort_order')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }

    public function down(): void
    {
        // Re-add columns to blogs
        if (!Schema::hasColumn('blogs', 'module_id')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->foreignId('module_id')->nullable()->constrained('lesson_modules')->nullOnDelete();
                $table->integer('sort_order')->default(0);
            });
            
            // Restore data from pivot
            $pivots = DB::table('module_lesson')->get();
            foreach ($pivots as $pivot) {
                DB::table('blogs')->where('id', $pivot->lesson_id)->update([
                    'module_id' => $pivot->module_id,
                    'sort_order' => $pivot->sort_order,
                ]);
            }
        }

        Schema::dropIfExists('module_lesson');
        Schema::dropIfExists('curriculum_items');
        Schema::dropIfExists('curriculums');
    }
};
