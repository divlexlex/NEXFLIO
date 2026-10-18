<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which staff positions can cater a Packages-category service. Regular
 * services keep the category→position map on the positions table; packages
 * that mix Facial + Massage (etc.) need an explicit multi-position list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_id', 'position_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_positions');
    }
};
