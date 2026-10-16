<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff positions, managed from the Employees page instead of the fixed
 * App\Enums\Position list. staff_profiles.position stays a plain string
 * holding the position NAME (no FK) — existing rows already match the
 * seeded names, so no data migration is needed. service_category links a
 * position to the service category it can fulfil (booking eligibility).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('service_category')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
