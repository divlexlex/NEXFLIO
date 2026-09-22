<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Home Service appointment-address snapshot (Phase 3B). Deliberately
 * duplicates the address fields already on client_addresses rather than just
 * storing source_client_address_id — a Client editing/removing their saved
 * default address later must NEVER change what a past appointment shows (see
 * the Phase 3B "Historical Address Test"). source_client_address_id is kept
 * only as an optional trace back to which saved address (if any) the
 * snapshot was taken from; it is never read for display, only the snapshot
 * columns are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_client_address_id')->nullable()->constrained('client_addresses')->nullOnDelete();
            $table->string('street_address');
            $table->string('barangay', 100);
            $table->string('city_municipality', 100);
            $table->string('province', 100);
            $table->string('postal_code', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_addresses');
    }
};
