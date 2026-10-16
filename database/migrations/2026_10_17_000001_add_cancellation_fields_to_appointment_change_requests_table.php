<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client cancellation-request categories + manager refund decision.
 * reason_category stores the config/booking.php 'cancellation_reasons' key
 * (the details text stays in the existing `reason` column); existing rows
 * stay NULL and display as "—". refund_decision stores the manager's
 * approve-time choice ('refundable' / 'non_refundable') for cancellation
 * requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_change_requests', function (Blueprint $table) {
            $table->string('reason_category', 50)->nullable()->after('reason');
            $table->string('refund_decision', 20)->nullable()->after('reason_category');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_change_requests', function (Blueprint $table) {
            $table->dropColumn(['reason_category', 'refund_decision']);
        });
    }
};
