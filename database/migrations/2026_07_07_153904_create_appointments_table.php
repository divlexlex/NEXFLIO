<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::create('appointments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained(); // Yung Client
        $table->foreignId('service_id')->constrained(); // Anong service?
        $table->foreignId('personnel_id')->constrained('users'); // Sinong Staff? (Naka-link sa users table)
        $table->dateTime('appointment_date');
        $table->time('start_time');
        $table->enum('status', ['pending', 'confirmed', 'in-service', 'served', 'cancelled', 'no-show'])->default('pending');
        $table->text('notes')->nullable();
        $table->timestamps();
        $table->softDeletes(); // No-delete policy
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
