<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->string('middle_name', 100)->nullable()->after('last_name');
            $table->string('contact_number', 20)->nullable()->after('middle_name');
            $table->string('username', 100)->nullable()->unique()->after('contact_number');
        });

        // Backfill existing staff users: split name → first_name / last_name
        // and generate a username so current accounts keep working.
        $staffUsers = DB::table('users')->where('role_id', 3)->get();
        foreach ($staffUsers as $user) {
            $parts = preg_split('/\s+/', trim($user->name), 2);
            $firstName = $parts[0] ?? '';
            $lastName  = $parts[1] ?? '';
            $username  = self::makeUsername($lastName, $firstName);

            // Ensure uniqueness even during backfill
            $base = $username;
            $n = 2;
            while (DB::table('users')->where('username', $username)->exists()) {
                $username = $base . $n;
                $n++;
            }

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'username'   => $username,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'middle_name', 'contact_number', 'username']);
        });
    }

    private static function makeUsername(string $lastName, string $firstName): string
    {
        $last  = strtolower(preg_replace('/[^a-zA-Z]/', '', $lastName));
        $first = strtolower(preg_replace('/[^a-zA-Z]/', '', $firstName));
        $slug  = "{$last}.{$first}";
        return $slug !== '.' ? $slug : 'staff';
    }
};
