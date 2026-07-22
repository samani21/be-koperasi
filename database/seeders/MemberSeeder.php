<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        for ($i = 1; $i <= 300; $i++) {
            $name = $faker->name;
            $nik = $faker->unique()->numerify('################');

            $randomDate = $faker->dateTimeBetween('-1 year', 'now');
            $year = $randomDate->format('Y');

            $user = User::create([
                'name'       => $name,
                'email'      => null,
                'nik'        => $nik,
                'password'   => Hash::make($nik),
                'role'       => 'member',
                'is_active'  => true,
                'created_at' => $randomDate,
                'updated_at' => $randomDate,
            ]);

            $memberNumber = 'KOP-' . $year . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);

            $user->member()->create([
                'member_number' => $memberNumber,
                'full_name'     => $name,
                'address'       => $faker->address,
                'phone'         => $faker->numerify('08##########'),
                'created_at'    => $randomDate,
                'updated_at'    => $randomDate,
            ]);
        }
    }
}
