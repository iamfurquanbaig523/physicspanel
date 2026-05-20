<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_id')->constrained('blogs')->cascadeOnDelete();
            $table->string('platform', 40);
            $table->string('code', 32)->unique();
            $table->string('target_url', 1024);
            $table->unsignedBigInteger('click_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['blog_id', 'platform'], 'blog_platform_share_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_share_links');
    }
};
