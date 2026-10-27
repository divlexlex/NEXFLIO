<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-entered leave corrections. Adjustments are separate, auditable rows
 * (signed days + required reason + author) instead of an overwrite of the
 * computed "approved days consumed" number, so a later approval can never
 * clobber an owner's correction. See App\Support\LeaveBalances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // sick | vacation
            $table->integer('days'); // signed: negative reduces remaining, positive adds back
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users');
            $table->unsignedSmallInteger('year');
            $table->timestamps();

            $table->index(['user_id', 'type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_adjustments');
    }
};
