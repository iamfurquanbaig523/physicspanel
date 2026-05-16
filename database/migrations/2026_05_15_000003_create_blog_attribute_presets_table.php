<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('blog_attribute_presets')) {
            Schema::create('blog_attribute_presets', function (Blueprint $table) {
                $table->id();
                $table->string('label', 160);
                $table->string('color', 20)->default('#B8FF35');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (DB::table('blog_attribute_presets')->count() === 0) {
            $presets = [
                ['Concept', '#534AB7'],
                ['Technical', '#0F6E56'],
                ['Practical', '#854F0B'],
                ['Research paper', '#993556'],
                ['Official doc', '#185FA5'],
                ['New ↑', '#3B6D11'],
                ['Data study', '#3B6D11'],
                ['Warning / trap', '#A32D2D'],
                ['Worked example', '#7A4F00'],
                ['Math formula', '#00AA55'],
            ];

            foreach ($presets as $index => [$label, $color]) {
                DB::table('blog_attribute_presets')->insert([
                    'label' => $label,
                    'color' => $color,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_attribute_presets');
    }
};
