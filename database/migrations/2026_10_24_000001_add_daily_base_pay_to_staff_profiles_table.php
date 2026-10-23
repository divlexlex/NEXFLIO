<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            // Weekly payroll (2026-10-24): pay is now a DAILY rate, paid per
            // attended day. The old monthly base_pay column is kept as-is
            // (no longer used by any payroll computation); values are NOT
            // converted — an admin types each employee's real daily rate.
            $table->decimal('daily_base_pay', 10, 2)->default(0)->after('base_pay');
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropColumn('daily_base_pay');
        });
    }
};
