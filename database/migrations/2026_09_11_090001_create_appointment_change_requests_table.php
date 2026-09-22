<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-raised change requests on their own appointments — a cancellation
 * or a reschedule proposal that a Manager must approve before anything on
 * the appointment actually changes. Mirrors the leave_requests approval
 * shape (pending/approved/rejected + reviewer + reviewed_at + note). No
 * native enum columns (portable to Azure SQL — same policy as the rest of
 * the schema); the allowed values live on App\Models\AppointmentChangeRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);   // cancellation | reschedule
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->text('reason')->nullable(); // the client's message

            // Reschedule proposal (all null for a cancellation request).
            $table->date('requested_date')->nullable();
            $table->string('requested_start_time', 5)->nullable(); // 'H:i'
            $table->foreignId('requested_personnel_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            $table->index(['appointment_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_change_requests');
    }
};
