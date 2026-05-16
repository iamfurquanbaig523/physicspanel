<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('blog_faqs')) {
            return;
        }

        Schema::create('blog_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_id')->constrained('blogs')->cascadeOnDelete();
            $table->string('question', 512);
            $table->longText('answer')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('include_in_schema')->default(true);
            $table->string('schema_question', 512)->nullable();
            $table->longText('schema_answer')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();

            $table->index(['blog_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_faqs');
    }
};
