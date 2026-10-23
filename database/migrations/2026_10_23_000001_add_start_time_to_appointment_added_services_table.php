<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Online multi-service bookings give EVERY added service its own start
        // time inside the same appointment/visit. NULL stays meaningful:
        // walk-in 'creation' extras and Payments 'payment' adds keep the old
        // behaviour (they have no independent time of their own).
        Schema::table('appointment_added_services', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('personnel_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_added_services', function (Blueprint $table) {
            $table->dropColumn('start_time');
        });
    }
};
