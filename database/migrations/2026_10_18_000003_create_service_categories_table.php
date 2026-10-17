<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service categories, managed from the Services page instead of the
 * hardcoded App\Support\ServiceCatalog::CATEGORY_ORDER list. services.category
 * stays a plain string holding the category NAME (no FK), exactly like
 * inventories.category and staff_profiles.position.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
