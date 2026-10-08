<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Optional short label ("New", "Best", "Most Booked"...) shown on the
        // public Services card; Admin-settable per real Service.
        Schema::table('services', function (Blueprint $table) {
            $table->string('badge', 50)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('badge');
        });
    }
};
