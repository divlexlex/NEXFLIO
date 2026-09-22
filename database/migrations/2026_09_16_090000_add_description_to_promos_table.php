<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Real Perfect Nails promos (e.g. "Pamper All You Can") bundle several
        // treatments and add-ons rather than discounting a single linked
        // Service — this is where that free-text detail lives. Optional: a
        // simple single-service promo can still rely on the auto "Includes:
        // {service}" line (see CatalogController@promo).
        Schema::table('promos', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
