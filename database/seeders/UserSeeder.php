<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::where('is_superuser', true)->first();
        
        $superuser = User::updateOrCreate(
            ['email' => "johndoe@mail.com"],
            [
                'first_name' => "John",
                'last_name' => "Doe",
                'phone' => "1234567890",
                'password' => Hash::make("Johndoe@123"),
            ]
        );

        $superuser->roles()->syncWithoutDetaching([$role->id]);
    }
}
