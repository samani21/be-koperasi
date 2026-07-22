<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class FrontOfficeSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        for ($i = 1; $i <= 50; $i++) {
            $name = $faker->name;

            User::create([
                'name'      => $name,
                'email'     => $faker->unique()->safeEmail,
                'nik'       => null,
                'password'  => Hash::make('password123'),
                'role'      => 'frontoffice',
                'is_active' => true,
            ]);
        }
    }
}
