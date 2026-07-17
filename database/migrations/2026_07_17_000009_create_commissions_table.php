<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable earnings ledger: one row per completed appointment
        // (unique constraint makes commission creation idempotent). Rows keep
        // the price and rate as they were at completion time, so later edits
        // to a service's price never rewrite anyone's pay.
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // the staff member
            $table->foreignId('appointment_id')->unique()->constrained();
            $table->decimal('service_price', 10, 2);
            $table->decimal('rate', 5, 2);
            $table->decimal('amount', 10, 2);
            $table->dateTime('earned_at');
            $table->timestamps();

            $table->index(['user_id', 'earned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
