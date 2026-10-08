<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The live MySQL `inventories.category` column was hand-edited to
 * enum('facial','aesthetic','massage'), so it rejects the 'nails' value the
 * Inventory page's Nails tab filters and writes (and it no longer matches
 * 2026_10_07_145037_add_category_to_inventories_table, which creates a
 * varchar(40) everywhere the schema is built from migrations).
 *
 * Converts it to the same nullable varchar(40). Existing enum values map to
 * plain strings unchanged, so no data is lost or rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventories', 'category')) {
            return;
        }

        $category = collect(Schema::getColumns('inventories'))->firstWhere('name', 'category');
        $type = (string) ($category['type'] ?? '');

        if (str_starts_with(strtolower($type), 'varchar')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            $table->string('category', 40)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Restoring the hand-maintained enum would require knowing its exact
        // value list per environment, so the varchar stays.
    }
};
