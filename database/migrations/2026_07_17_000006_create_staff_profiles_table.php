<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('base_pay', 10, 2)->default(0); // fixed monthly base
            $table->decimal('commission_rate', 5, 2)->default(10.00); // % of service price
            $table->string('position')->nullable();
            $table->boolean('is_on_break')->default(false);
            $table->dateTime('break_started_at')->nullable();
            $table->string('employment_status', 20)->default('active'); // active | inactive
            $table->date('hired_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
