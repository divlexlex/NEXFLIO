<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per item in a weekly count: what the system believed was on hand
 * when the count started (immutable snapshot) and what was physically counted
 * (null = not counted yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_inventory_count_id')
                ->constrained('weekly_inventory_counts')
                ->cascadeOnDelete()
                ->name('wic_items_count_id_foreign');
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('system_quantity');
            $table->unsignedInteger('counted_quantity')->nullable();
            $table->timestamps();

            // Short name: MySQL's identifier limit is 64 characters.
            $table->unique(
                ['weekly_inventory_count_id', 'inventory_id'],
                'wic_items_count_item_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_inventory_count_items');
    }
};
