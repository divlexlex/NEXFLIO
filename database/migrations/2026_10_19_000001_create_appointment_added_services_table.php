<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Services added to an existing appointment after it was booked
        // (same Appointment ID). Each row snapshots the price charged at the
        // time it was added — later catalog edits never rewrite the amount
        // the appointment collects. Removing a row is a hard delete (this is
        // an operational ledger row, not an audit trail).
        Schema::create('appointment_added_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services');
            $table->string('variant_label')->nullable();
            $table->decimal('price', 10, 2);
            // Staff who performs the added service. Optional: a category with
            // no linked positions (or a manager who doesn't care yet) leaves
            // this null and the appointment's own personnel is used.
            $table->foreignId('personnel_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('added_by')->constrained('users');
            // Where this avail came from: 'creation' (picked in the Walk-in
            // Entry form as one of the services the client is taking) or
            // 'payment' (added later from the Payments add flow). The Payments
            // card and the drawer use this to show a creation avail as part of
            // the appointment's services and a payment add as a separate
            // "Added" line. Defaults to 'payment' so pre-existing rows stay
            // unambiguous; a one-time backfill (see the change report) marks
            // the creation rows.
            $table->string('source')->default('payment');
            $table->timestamps();

            $table->index('appointment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_added_services');
    }
};
