<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refund tracking for a rejected payment (step 2A; top-up is step 2B).
 * amount_received is the cash the manager actually saw on the proof,
 * captured at rejection time for refund/topup-eligible categories only.
 * refund_status moves null -> 'requested' (client, My Bookings) ->
 * 'sent' (manager/admin, Payments page), with who/when recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount_received', 10, 2)->nullable()->after('amount');
            $table->string('refund_status', 20)->nullable()->after('rejection_category');
            $table->dateTime('refund_requested_at')->nullable()->after('refund_status');
            $table->dateTime('refund_sent_at')->nullable()->after('refund_requested_at');
            $table->foreignId('refund_sent_by')->nullable()->after('refund_sent_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'amount_received',
                'refund_status',
                'refund_requested_at',
                'refund_sent_at',
                'refund_sent_by',
            ]);
        });
    }
};
