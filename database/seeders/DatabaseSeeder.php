<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
            FrontOfficeSeeder::class,
            MemberSeeder::class,
        ]);

        $this->command->info('Seeder berhasil dipanggil semua dengan rapi!');
    }
}
