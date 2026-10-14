<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly staff shift roster (AM/PM per week). One row per assigned person
 * per week — week_start is always the Sunday of that week. The old
 * staff_schedules table is left untouched; client booking prefers this
 * roster when a week has one (see BookingController) and otherwise falls
 * back to staff_schedules exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->string('shift', 10); // 'am' | 'pm'
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['user_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_shift_assignments');
    }
};
