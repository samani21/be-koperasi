<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper untuk membuat user Super Admin aktif
     */
    private function getSuperAdminUser()
    {
        return User::factory()->create([
            'role' => 'superadmin',
            'is_active' => true
        ]);
    }

    // 1. TEST DASHBOARD STATS
    public function test_dashboard_stats_success()
    {
        $superAdmin = $this->getSuperAdminUser();

        // Buat dummy data untuk dihitung
        User::factory()->create(['role' => 'frontoffice']);
        $memberUser = User::factory()->create(['role' => 'member']);
        Member::create([
            'user_id' => $memberUser->id,
            'member_number' => 'KOP-2024-0001',
            'full_name' => 'Budi',
            'created_at' => Carbon::now()
        ]);

        $response = $this->actingAs($superAdmin, 'api')->getJson('/api/super-admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'stats' => ['total_member', 'total_fo'],
                'chartData' => [
                    '*' => ['name', 'total', 'type']
                ],
                'recent_members'
            ]
        ]);

        // Pastikan jumlah terhitung benar (1 member, 1 FO)
        $this->assertEquals(1, $response->json('data.stats.total_member'));
        $this->assertEquals(1, $response->json('data.stats.total_fo'));
    }

    // 2. TEST INDEX FRONT OFFICE
    public function test_index_front_office_success()
    {
        $superAdmin = $this->getSuperAdminUser();

        // Buat 2 akun FO
        User::factory()->create(['role' => 'frontoffice', 'name' => 'FO Satu']);
        User::factory()->create(['role' => 'frontoffice', 'name' => 'FO Dua']);

        $response = $this->actingAs($superAdmin, 'api')->getJson('/api/super-admin/front-office');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.total'));
    }

    // 3. TEST STORE (TAMBAH FO)
    public function test_store_front_office_success()
    {
        $superAdmin = $this->getSuperAdminUser();

        $payload = [
            'name' => 'Front Office Baru',
            'email' => 'fo.baru@koperasi.com',
            'password' => 'rahasia123'
        ];

        $response = $this->actingAs($superAdmin, 'api')->postJson('/api/super-admin/front-office', $payload);

        $response->assertStatus(201); // Created
        $response->assertJson(['message' => 'Akun Front Office berhasil dibuat']);

        $this->assertDatabaseHas('users', [
            'email' => 'fo.baru@koperasi.com',
            'role' => 'frontoffice',
            'is_active' => 1
        ]);
    }

    public function test_store_front_office_forbidden_for_non_superadmin()
    {
        $foUser = User::factory()->create(['role' => 'frontoffice', 'is_active' => true]);

        $payload = [
            'name' => 'Hacker FO',
            'email' => 'hacker@koperasi.com',
        ];

        $response = $this->actingAs($foUser, 'api')->postJson('/api/super-admin/front-office', $payload);

        $response->assertStatus(403);
    }
    // 4. TEST UPDATE FO
    public function test_update_front_office_success()
    {
        $superAdmin = $this->getSuperAdminUser();

        $foLama = User::factory()->create([
            'role' => 'frontoffice',
            'name' => 'FO Lama',
            'email' => 'lama@koperasi.com'
        ]);

        $payload = [
            'name' => 'FO Diupdate',
            'email' => 'update@koperasi.com',
        ];

        $response = $this->actingAs($superAdmin, 'api')->postJson("/api/super-admin/front-office/{$foLama->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $foLama->id,
            'name' => 'FO Diupdate',
            'email' => 'update@koperasi.com'
        ]);
    }

    // 5. TEST DESTROY FO
    public function test_destroy_front_office_success()
    {
        $superAdmin = $this->getSuperAdminUser();

        $foDihapus = User::factory()->create(['role' => 'frontoffice']);

        $response = $this->actingAs($superAdmin, 'api')->deleteJson("/api/super-admin/front-office/{$foDihapus->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Data Front Office berhasil dihapus']);

        $this->assertDatabaseMissing('users', ['id' => $foDihapus->id]);
    }

    // 6. TEST TOGGLE STATUS
    public function test_toggle_status_success()
    {
        $superAdmin = $this->getSuperAdminUser();

        // Target: akun FO yang sedang aktif
        $targetUser = User::factory()->create(['role' => 'frontoffice', 'is_active' => true]);

        $response = $this->actingAs($superAdmin, 'api')->patchJson("/api/super-admin/front-office/{$targetUser->id}/toggle-status");

        $response->assertStatus(200);
        // Pastikan pesannya "dinonaktifkan (diblokir dari login)"
        $response->assertJsonFragment(['message' => 'Akun berhasil dinonaktifkan (diblokir dari login)']);

        // Cek ke DB pastikan jadi false/0
        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'is_active' => 0
        ]);
    }

    public function test_toggle_status_harakiri_prevention()
    {
        $superAdmin = $this->getSuperAdminUser();

        // ACT: Super Admin mencoba toggle status akunnya sendiri!
        $response = $this->actingAs($superAdmin, 'api')->patchJson("/api/super-admin/front-office/{$superAdmin->id}/toggle-status");

        // ASSERT: Harus dicegat!
        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri!']);

        // Pastikan akun Super Admin tetap aktif
        $this->assertDatabaseHas('users', [
            'id' => $superAdmin->id,
            'is_active' => 1
        ]);
    }
}
