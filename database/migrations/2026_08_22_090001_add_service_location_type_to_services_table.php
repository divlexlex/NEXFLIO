<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive column only — no data is dropped or altered. Adds the real,
 * server-enforced Home Service eligibility flag (App\Enums\ServiceLocationType)
 * that Phase 3B's resume audit found missing: `category` was always just a
 * display label ("Home Service" as free text), never a bookable-location
 * signal. Defaults every existing/future row to 'branch' — the only location
 * type Phase 3A ever supported — so nothing already relying on Branch Booking
 * changes behavior; a service must be explicitly marked 'home' or 'both' by
 * Admin to become Home-Service-bookable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('service_location_type', 10)->default('branch')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('service_location_type');
        });
    }
};
