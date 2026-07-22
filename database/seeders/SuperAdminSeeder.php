<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'      => 'Super Admin',
            'email'     => 'superadmin@koperasi.com',
            'nik'       => null,
            'password'  => Hash::make('secret123'),
            'role'      => 'superadmin',
            'is_active' => true,
        ]);
    }
}
