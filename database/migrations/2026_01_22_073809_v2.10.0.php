<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Custom Fields Translation Name Nullable
        Schema::whenTableHasColumn('custom_fields_translations', 'name', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
        });

        // 2. Add Package Columns
        Schema::table('packages', function (Blueprint $table) {
            $table->enum('listing_duration_type', ['standard', 'package', 'custom'])->nullable()->after('duration');
            $table->integer('listing_duration_days')->nullable()->after('listing_duration_type');
            $table->longText('key_points')->nullable()->after('description');
            $table->tinyInteger('is_global')->default(1)->after('type');
        });

        // 3. Create Pivot Table
        Schema::create('package_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['package_id', 'category_id']);
        });

        // 4. Update Sliders
        Schema::table('sliders', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('id')->constrained('countries')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->after('country_id')->constrained('states')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('state_id')->constrained('cities')->nullOnDelete();
        });

        // 5. Create Currencies & Update Countries
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained('countries')->cascadeOnDelete();
            $table->char('iso_code', 3)->unique()->comment('ISO 4217 currency code');
            $table->string('name', 100)->unique()->comment('Currency name');
            $table->string('symbol', 10)->comment('Currency symbol');
            $table->enum('symbol_position', ['left', 'right'])->default('left')->comment('Currency symbol position');
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->string('thousand_separator', 5)->default(',');
            $table->string('decimal_separator', 5)->default('.');
            $table->timestamps();
        });

        Schema::table('countries', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
        });

        // 6. Update Items
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('show_mobile_number')->default(false)->after('show_only_to_premium');
            $table->foreignId('currency_id')->after('price')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('contact')->nullable()->change();
        });

        // 7. Translations & OTPs
        Schema::table('package_translations', function (Blueprint $table) {
            $table->longText('key_points')->nullable()->after('description');
        });

        Schema::table('number_otps', function (Blueprint $table) {
            $table->string('country_code', 10)->after('number')->nullable();
        });

        // 8. Jobs Table
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        // 9. Modify Package Column Type (Enum to Varchar)
        Schema::table('packages', function (Blueprint $table) {
            DB::statement('ALTER TABLE packages MODIFY listing_duration_type VARCHAR(255) NULL');
        });

        // 10. User Purchased Packages Columns
        Schema::table('user_purchased_packages', function (Blueprint $table) {
            $table->string('listing_duration_type')->nullable(); // Set as string to match parent
            $table->integer('listing_duration_days')->nullable()->after('listing_duration_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_purchased_packages', function (Blueprint $table) {
            $table->dropColumn(['listing_duration_type', 'listing_duration_days']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['show_mobile_number', 'currency_id']);
            $table->string('contact')->nullable(false)->change();
        });

        Schema::table('countries', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn('currency_id');
        });

        Schema::dropIfExists('currencies');
        Schema::dropIfExists('package_categories');
        Schema::dropIfExists('jobs');

        Schema::table('number_otps', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });

        Schema::table('package_translations', function (Blueprint $table) {
            $table->dropColumn('key_points');
        });

        Schema::table('sliders', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['state_id']);
            $table->dropForeign(['city_id']);
            $table->dropColumn(['country_id', 'state_id', 'city_id']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['listing_duration_type', 'listing_duration_days', 'key_points', 'is_global']);
        });
    }
};