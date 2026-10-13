<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest count for a reservation-fee booking without package variants — maps
 * 1->Single, 2->Couple, 3+->Barkada (see config/booking.php and
 * App\Support\ReservationFee). Nullable: regular (non-fee, or variant-decision)
 * bookings have no guest count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedSmallInteger('guests')->nullable()->after('variant_label');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('guests');
        });
    }
};
