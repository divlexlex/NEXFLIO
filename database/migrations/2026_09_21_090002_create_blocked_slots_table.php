<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_slots', function (Blueprint $table) {
            $table->id();
            $table->date('blocked_date');
            $table->time('start_time')->nullable(); // null = block entire day
            $table->time('end_time')->nullable();   // null = block entire day
            $table->foreignId('personnel_id')->nullable()->constrained('users')->nullOnDelete(); // null = block for all staff
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['blocked_date', 'start_time', 'personnel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_slots');
    }
};
