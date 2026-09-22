<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->id();
            // unique() enforces at most one profile per account; cascadeOnDelete
            // is a safety net for the rare hard-delete path only — normal
            // deletion goes through SoftDeletes below, per this project's
            // existing no-delete policy (see users table migration).
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            // Nullable at the DB level so existing role_id=4 Clients (no
            // profile row yet) are never broken; Website registration
            // enforces these as required at the validation layer instead —
            // see App\Http\Controllers\Web\AuthController@register.
            $table->string('gender', 20)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('mobile_number', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_profiles');
    }
};
