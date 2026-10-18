<?php

use App\Enums\AppointmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recognized revenue is dated by service completion, not booking creation
 * or payment verification. completed_at is stamped once when status becomes
 * Completed; historical Completed rows are backfilled from updated_at so
 * existing reports keep a stable recognition timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
        });

        DB::table('appointments')
            ->where('status', AppointmentStatus::Completed->value)
            ->whereNull('completed_at')
            ->update([
                'completed_at' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
