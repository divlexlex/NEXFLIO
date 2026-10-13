<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured rejection category for a rejected payment (see
 * config/payment.php 'rejection_reasons') — stored alongside the manager's
 * free-text rejection_reason. Nullable: rejections made before categories
 * (or via the API status route, which carries no category) have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('rejection_category', 30)->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('rejection_category');
        });
    }
};
