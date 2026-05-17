<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blogs') && Schema::hasColumn('blogs', 'description')) {
            DB::statement('ALTER TABLE `blogs` MODIFY `description` LONGTEXT NULL');
        }

        if (Schema::hasTable('blog_translations') && Schema::hasColumn('blog_translations', 'description')) {
            DB::statement('ALTER TABLE `blog_translations` MODIFY `description` LONGTEXT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('blogs') && Schema::hasColumn('blogs', 'description')) {
            DB::statement('ALTER TABLE `blogs` MODIFY `description` TEXT NULL');
        }

        if (Schema::hasTable('blog_translations') && Schema::hasColumn('blog_translations', 'description')) {
            DB::statement('ALTER TABLE `blog_translations` MODIFY `description` TEXT NULL');
        }
    }
};
