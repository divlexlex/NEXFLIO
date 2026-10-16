<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly roster rows are now individual per-person shifts: the AM/PM
 * grouping is gone, so `shift` is nullable and new code always writes
 * null. Old rows keep their stored values; nothing reads the column
 * anymore (booking eligibility keys off user_id + week_start only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_shift_assignments', function (Blueprint $table) {
            $table->string('shift', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('staff_shift_assignments', function (Blueprint $table) {
            $table->string('shift', 10)->nullable(false)->change();
        });
    }
};
