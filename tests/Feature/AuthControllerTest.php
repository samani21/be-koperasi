<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_with_email()
    {
        // 1. ARRANGE: Buat user aktif dan data member-nya
        $user = User::factory()->create([
            'email' => 'admin@koperasi.com',
            'nik' => '3171234567890001',
            'password' => Hash::make('password123'),
            'role' => 'frontoffice', // Sesuaikan role yang ada di sistemmu
            'is_active' => true,
        ]);

        Member::create([
            'user_id' => $user->id,
            'member_number' => 'KOP-001',
            'full_name' => 'Budi Santoso',
            'photo' => '/storage/anggota/foto.webp' // Path mentah
        ]);

        // 2. ACT: Tembak endpoint login
        $response = $this->postJson('/api/auth/login', [
            'login_id' => 'admin@koperasi.com',
            'password' => 'password123',
        ]);

        // 3. ASSERT: Pastikan status 200 dan response sesuai
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Login berhasil',
            'data' => [
                'user' => [
                    'email' => 'admin@koperasi.com',
                    // ✅ Pastikan yang keluar adalah path mentah, bukan URL
                    'photo' => '/storage/anggota/foto.webp',
                ]
            ]
        ]);

        // Pastikan token dikembalikan
        $this->assertArrayHasKey('token', $response->json('data'));
    }

    public function test_login_failed_wrong_password()
    {
        // 1. ARRANGE
        $user = User::factory()->create([
            'email' => 'admin@koperasi.com',
            'password' => Hash::make('password123'),
        ]);

        // 2. ACT: Tembak dengan password salah
        $response = $this->postJson('/api/auth/login', [
            'login_id' => 'admin@koperasi.com',
            'password' => 'salahpassword',
        ]);

        // 3. ASSERT: Status 401 Unauthorized
        $response->assertStatus(401);
        // Mengandung pesan error (sesuaikan dengan isi apiService kamu)
        $response->assertJsonFragment([
            'message' => 'Email/NIK atau password salah. Sisa percobaan: 4x'
        ]);
    }

    public function test_login_failed_inactive_user()
    {
        // 1. ARRANGE: User dibuat tapi is_active = false
        $user = User::factory()->create([
            'email' => 'nonaktif@koperasi.com',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        // 2. ACT
        $response = $this->postJson('/api/auth/login', [
            'login_id' => 'nonaktif@koperasi.com',
            'password' => 'password123',
        ]);

        // 3. ASSERT: Status 403 Forbidden
        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Akun Anda dinonaktifkan. Silakan hubungi Super Admin.'
        ]);
    }

    public function test_me_success()
    {
        // 1. ARRANGE
        $user = User::factory()->create([
            'role' => 'frontoffice',
            'is_active' => true,
        ]);

        Member::create([
            'user_id' => $user->id,
            'member_number' => 'KOP-002',
            'full_name' => 'Siti Aminah',
            'photo' => '/storage/anggota/siti.webp'
        ]);

        // 2. ACT: Tembak endpoint /me dengan token (simulasi actingAs)
        $response = $this->actingAs($user, 'api')->getJson('/api/auth/me');

        // 3. ASSERT
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Data profil berhasil diambil',
            'data' => [
                'email' => $user->email,
                // ✅ Validasi path mentah
                'photo' => '/storage/anggota/siti.webp'
            ]
        ]);
    }
}
