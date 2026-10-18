<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-position staff on a Packages booking. Regular services keep using
 * appointments.personnel_id alone; packages that need Facial + Massage
 * (etc.) store one row per required position. user_id null means the
 * client chose "No preference" for that role — manager assigns later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_personnel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['appointment_id', 'position_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_personnel');
    }
};
