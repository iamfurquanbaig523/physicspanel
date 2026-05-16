<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Remove any chapter-type rows from series_items (they reference the chapters table)
        DB::table('series_items')->where('item_type', 'chapter')->delete();

        // 2. Drop the chapter_blog pivot table
        Schema::dropIfExists('chapter_blog');

        // 3. Drop the chapters table
        Schema::dropIfExists('chapters');

        // 4. The series_items table now only holds item_type = 'article' entries
        //    pointing at blogs. The polymorphic column remains for forward compat
        //    but in practice every row is an article.
    }

    public function down(): void
    {
        // Re-create the chapters table
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#B8FF35');
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // Re-create the chapter_blog pivot table
        Schema::create('chapter_blog', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chapter_id');
            $table->unsignedBigInteger('blog_id');
            $table->integer('sort_order')->default(0);
            $table->unique(['chapter_id', 'blog_id']);
            $table->foreign('chapter_id')->references('id')->on('chapters')->onDelete('cascade');
            $table->foreign('blog_id')->references('id')->on('blogs')->onDelete('cascade');
        });
    }
};
