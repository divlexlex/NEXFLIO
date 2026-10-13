<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client-facing Appointment ID (e.g. PN-20261015-000123) — the code shown
 * on the booking success page and My Bookings so the client can quote it
 * at the spa, and surfaced in the admin calendar/drawer as the lookup key.
 * Format: PN-<creation date YYYYMMDD>-<appointment id, 6-padded>, computed
 * in App\Models\Appointment::referenceCode(). Stored (not purely computed)
 * so feeds and listings can read it straight off the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('reference_code', 20)->nullable()->unique()->after('id');
        });

        // Backfill pre-existing rows with the same format new rows get.
        foreach (
            DB::table('appointments')->select(['id', 'created_at'])->orderBy('id')->cursor() as $row
        ) {
            $date = $row->created_at
                ? \Illuminate\Support\Carbon::parse($row->created_at)->format('Ymd')
                : now()->format('Ymd');

            DB::table('appointments')
                ->where('id', $row->id)
                ->update(['reference_code' => sprintf('PN-%s-%06d', $date, $row->id)]);
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('reference_code');
        });
    }
};
