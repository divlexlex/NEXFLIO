<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A string column instead of an enum keeps the schema portable
        // (Azure SQL has no enum type) and lets us add statuses without
        // further migrations. Validation lives in App\Enums\AppointmentStatus.
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('status', 20)->default('unverified')->change();
        });

        DB::table('appointments')->where('status', 'pending')->update(['status' => 'unverified']);
        DB::table('appointments')->where('status', 'confirmed')->update(['status' => 'booked']);
        DB::table('appointments')->where('status', 'served')->update(['status' => 'completed']);

        Schema::table('appointments', function (Blueprint $table) {
            // Walk-ins are entered by managers without a client account.
            $table->string('walk_in_name')->nullable()->after('user_id');
            $table->string('walk_in_phone', 30)->nullable()->after('walk_in_name');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        DB::table('appointments')->where('status', 'unverified')->update(['status' => 'pending']);
        DB::table('appointments')->where('status', 'booked')->update(['status' => 'confirmed']);
        DB::table('appointments')->where('status', 'completed')->update(['status' => 'served']);

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['walk_in_name', 'walk_in_phone']);
        });
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('status', 20)->default('pending')->change();
        });
    }
};
