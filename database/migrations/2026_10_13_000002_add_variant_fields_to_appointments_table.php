<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshots the package tier at booking time so later edits to the variant
 * (or the service) never retroactively change what a client was charged.
 * Old rows keep NULL and fall back to services.price for display.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('variant_label', 40)->nullable()->after('service_id');
            $table->decimal('booked_price', 10, 2)->nullable()->after('variant_label');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['booked_price', 'variant_label']);
        });
    }
};