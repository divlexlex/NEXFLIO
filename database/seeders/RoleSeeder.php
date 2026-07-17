<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'Super Admin'],
            ['id' => 2, 'name' => 'Manager'],
            ['id' => 3, 'name' => 'Staff'],
            ['id' => 4, 'name' => 'Client'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(['id' => $role['id']], ['name' => $role['name']]);
        }
    }
}
