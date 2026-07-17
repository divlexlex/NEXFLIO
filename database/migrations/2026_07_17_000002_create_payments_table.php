<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method', 20)->default('gcash');
            $table->string('proof_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->dateTime('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Backfill: every appointment booked with a proof upload gets a payment
        // row. Amount is the service's listed price; bookings that a manager
        // already confirmed under the old flow count as verified.
        $now = now();
        $appointments = DB::table('appointments')
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->whereNotNull('appointments.payment_proof_path')
            ->select(
                'appointments.id as appointment_id',
                'appointments.status',
                'appointments.payment_proof_path',
                'appointments.created_at',
                'services.price'
            )
            ->get();

        foreach ($appointments as $appointment) {
            DB::table('payments')->insert([
                'appointment_id' => $appointment->appointment_id,
                'amount' => $appointment->price,
                'method' => 'gcash',
                'proof_path' => $appointment->payment_proof_path,
                'status' => in_array($appointment->status, ['booked', 'in-service', 'completed'], true)
                    ? 'verified'
                    : 'pending',
                'created_at' => $appointment->created_at ?? $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
