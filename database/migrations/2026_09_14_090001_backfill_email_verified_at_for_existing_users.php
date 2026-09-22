<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Email verification enforcement is new — every account created
        // before this feature shipped must be grandfathered in as verified,
        // or every existing Owner/Manager/Staff/Client gets locked out of
        // their own account on next login. Only NEW registrations from here
        // on are required to go through the code flow.
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible — we can't know which rows were
        // originally null, and re-nulling them would lock everyone out.
    }
};
