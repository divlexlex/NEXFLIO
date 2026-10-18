<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Shop closes at 10:00 PM same day (no overnight 1:00 AM window). Existing
 * installs still had the old seeded close_at of 01:00:00 — flip those to
 * 22:00:00 so same-day booking cannot offer starts past closing.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('business_hours')
            ->where('close_at', '01:00:00')
            ->update([
                'close_at' => '22:00:00',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('business_hours')
            ->where('close_at', '22:00:00')
            ->update([
                'close_at' => '01:00:00',
                'updated_at' => now(),
            ]);
    }
};
