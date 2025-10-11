<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            ['code' => 'users.create', 'description' => 'Can create users'],
            ['code' => 'users.view', 'description' => 'Can view users and user list'],
            ['code' => 'users.update', 'description' => 'Can update users'],
            ['code' => 'users.delete', 'description' => 'Can delete users'],
            ['code' => 'employees.create', 'description' => 'Can create employees'],
            ['code' => 'employees.view', 'description' => 'Can view employees list'],
            ['code' => 'employees.update', 'description' => 'Can update employees'],
            ['code' => 'employees.delete', 'description' => 'Can delete employees'],
            ['code' => 'roles.create', 'description' => 'Can create roles'],
            ['code' => 'roles.view', 'description' => 'Can view roles and role list'],
            ['code' => 'roles.update', 'description' => 'Can update roles'],
            ['code' => 'roles.delete', 'description' => 'Can delete roles'],
            ['code' => 'permissions.view', 'description' => 'Can view permissions and permissions list'],
            ['code' => 'holidays.create', 'description' => 'Can create holidays'],
            ['code' => 'holidays.view', 'description' => 'Can view holidays and holiday list'],
            ['code' => 'holidays.update', 'description' => 'Can update holidays'],
            ['code' => 'holidays.delete', 'description' => 'Can delete holidays'],
        ];

        // Insert or update permissions
        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['code' => $permission['code']],
                ['description' => $permission['description']]
            );
        }
    }
}
