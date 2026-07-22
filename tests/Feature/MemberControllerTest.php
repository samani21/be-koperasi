<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MemberControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper untuk membuat user Front Office
     */
    private function getFrontOfficeUser()
    {
        return User::factory()->create([
            'role' => 'frontoffice',
            'is_active' => true
        ]);
    }

    // 1. TEST INDEX (GET LIST MEMBER)
    public function test_index_member_success()
    {
        $foUser = $this->getFrontOfficeUser();

        // Buat 3 dummy member
        $user1 = User::factory()->create(['role' => 'member', 'nik' => '1111']);
        Member::create(['user_id' => $user1->id, 'member_number' => 'KOP-2024-0001', 'full_name' => 'Agus']);

        $user2 = User::factory()->create(['role' => 'member', 'nik' => '2222']);
        Member::create(['user_id' => $user2->id, 'member_number' => 'KOP-2024-0002', 'full_name' => 'Budi']);

        // ACT
        $response = $this->actingAs($foUser, 'api')->getJson('/api/front-office/member');

        // ASSERT
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => [
                '*' => ['id', 'member_number', 'full_name', 'user']
            ],
            'meta' => ['current_page', 'per_page', 'total', 'last_page']
        ]);

        // Memastikan total data adalah 2
        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_index_member_with_search()
    {
        $foUser = $this->getFrontOfficeUser();

        $user1 = User::factory()->create(['role' => 'member', 'nik' => '1111']);
        Member::create(['user_id' => $user1->id, 'member_number' => 'KOP-2024-0001', 'full_name' => 'Joko Santoso']);

        // ACT (Cari berdasarkan nama "Joko")
        $response = $this->actingAs($foUser, 'api')->getJson('/api/front-office/member?search=Joko');

        // ASSERT
        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals('Joko Santoso', $response->json('data.0.full_name'));
    }

    // 2. TEST STORE (TAMBAH MEMBER BARU)
    public function test_store_member_success_without_photo()
    {
        $foUser = $this->getFrontOfficeUser();

        $payload = [
            'nik' => '3171234567890001',
            'email' => 'member.baru@example.com',
            'password' => 'rahasia123',
            'full_name' => 'Member Baru',
            'phone' => '081234567890',
            'address' => 'Jl. Koperasi No. 1'
            // Kita tidak menyertakan foto untuk mempercepat testing dan menghindari crash ekstensi GD di PHPUnit
        ];

        // ACT (Sesuai routes/api.php kamu, ini menggunakan POST /)
        $response = $this->actingAs($foUser, 'api')->postJson('/api/front-office/member', $payload);

        // ASSERT
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Member berhasil ditambahkan']);

        // Pastikan User & Member masuk ke Database
        $this->assertDatabaseHas('users', [
            'nik' => '3171234567890001',
            'email' => 'member.baru@example.com',
            'role' => 'member'
        ]);

        $this->assertDatabaseHas('members', [
            'full_name' => 'Member Baru',
            'phone' => '081234567890'
        ]);
    }

    public function test_store_member_validation_error()
    {
        $foUser = $this->getFrontOfficeUser();

        // NIK kosong akan memicu error validasi
        $payload = [
            'email' => 'member.baru@example.com',
            'full_name' => 'Member Baru',
        ];

        $response = $this->actingAs($foUser, 'api')->postJson('/api/front-office/member', $payload);

        // ASSERT
        $response->assertStatus(422);
    }

    // 3. TEST UPDATE MEMBER
    public function test_update_member_success_and_remove_photo()
    {
        $foUser = $this->getFrontOfficeUser();

        // Bikin member existing
        $memberUser = User::factory()->create([
            'role' => 'member',
            'nik' => '9999999999999999',
            'email' => 'lama@example.com'
        ]);
        $member = Member::create([
            'user_id' => $memberUser->id,
            'member_number' => 'KOP-2024-0005',
            'full_name' => 'Nama Lama',
            'photo' => '/storage/anggota/kop-2024-0005.webp' // Simulasi ada foto
        ]);

        $payload = [
            'nik' => '8888888888888888', // NIK Diupdate
            'full_name' => 'Nama Baru Diupdate',
            'remove_photo' => true // Simulasi hapus foto
        ];

        // ACT (Sesuai routes/api.php, rutenya POST /{id})
        $response = $this->actingAs($foUser, 'api')->postJson("/api/front-office/member/{$member->id}", $payload);

        // ASSERT
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Data Anggota berhasil diperbarui']);

        // Pastikan DB terupdate
        $this->assertDatabaseHas('users', ['nik' => '8888888888888888']);
        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'full_name' => 'Nama Baru Diupdate',
            'photo' => null // Karena remove_photo = true
        ]);
    }

    // 4. TEST DESTROY (HAPUS MEMBER)
    public function test_destroy_member_success()
    {
        $foUser = $this->getFrontOfficeUser();

        $memberUser = User::factory()->create(['role' => 'member']);
        $member = Member::create([
            'user_id' => $memberUser->id,
            'member_number' => 'KOP-2024-0010',
            'full_name' => 'Member Akan Dihapus'
        ]);

        // ACT
        $response = $this->actingAs($foUser, 'api')->deleteJson("/api/front-office/member/{$member->id}");

        // ASSERT
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Data Anggota beserta foto berhasil dihapus permanen']);

        // Pastikan Data Hilang (karena kamu pakai hard delete, bukan soft delete)
        $this->assertDatabaseMissing('members', ['id' => $member->id]);
        $this->assertDatabaseMissing('users', ['id' => $memberUser->id]);
    }

    // 5. TEST SHOW (Test lama yang sudah kita kerjakan sebelumnya)
    public function test_show_member_success()
    {
        // 1. ARRANGE (Gunakan endpoint untuk member agar sesuai middleware member)
        $user = User::factory()->create([
            'role' => 'member',
            'is_active' => true
        ]);

        $member = Member::create([
            'user_id' => $user->id,
            'member_number' => 'KOP-001',
            'full_name' => 'Budi Santoso',
            'photo' => '/storage/anggota/foto.webp'
        ]);

        // 2. ACT
        $response = $this->actingAs($user, 'api')->getJson('/api/member/show');

        // 3. ASSERT
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Data Member berhasil diambil',
            'data' => [
                'full_name' => 'Budi Santoso',
                'photo' => '/storage/anggota/foto.webp',
            ]
        ]);
    }
}
