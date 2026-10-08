<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One physical-count session per week (the business counts every Thursday),
 * identified by that week's Thursday date so a week can never hold two
 * sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->date('count_date')->unique();
            $table->string('status', 20)->default('draft'); // draft | finalized
            $table->foreignId('conducted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_inventory_counts');
    }
};
