<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packages assign multiple techs, each earning commission on their included
 * service's original price. The old unique(appointment_id) allowed only one
 * row; switch to unique(appointment_id, user_id, included_service_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropUnique(['appointment_id']);
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->foreignId('included_service_id')
                ->nullable()
                ->after('appointment_id')
                ->constrained('services')
                ->nullOnDelete();

            $table->unique(
                ['appointment_id', 'user_id', 'included_service_id'],
                'commissions_appointment_user_service_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropUnique('commissions_appointment_user_service_unique');
            $table->dropConstrainedForeignId('included_service_id');
            $table->unique(['appointment_id']);
        });
    }
};
