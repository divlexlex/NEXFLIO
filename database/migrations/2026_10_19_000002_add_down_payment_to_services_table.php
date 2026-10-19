<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-service down payment. A positive value is the amount due online
     * (capped at the booked price); NULL means the service has none, so a
     * booking can be settled by Cash or by paying the full price online.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('down_payment', 10, 2)->nullable()->default(20.00)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('down_payment');
        });
    }
};
