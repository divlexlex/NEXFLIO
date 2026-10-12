<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saleable tiers for a service (e.g. Solo / Couple / Barkada for the head-spa
 * packages). Each variant has its own price; when a service has variants the
 * booking flow charges the selected variant and no longer the base services
 * price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('label', 40);
            $table->decimal('price', 10, 2);
            $table->unsignedTinyInteger('guests')->nullable(); // 1 Solo, 2 Couple, null Barkada
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['service_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_variants');
    }
};