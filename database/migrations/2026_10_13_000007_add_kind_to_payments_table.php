<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment kind (step 2B top-up flow). 'payment' is every pre-existing row:
 * the booking-time charge. 'partial_receipt' rows record money the branch
 * actually holds after a topup_eligible rejection (verified at once).
 * 'topup' rows are client-submitted shortfalls awaiting verification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('kind', 20)->default('payment')->after('appointment_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
