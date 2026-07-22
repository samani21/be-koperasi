<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontOfficeControllerTest extends TestCase
{
    use RefreshDatabase;

    // Endpoint sesuai dengan routes/api.php sebelumnya
    private string $endpoint = '/api/front-office/dashboard';

    /**
     * Helper untuk membuat user Front Office yang aktif
     */
    private function getFrontOfficeUser()
    {
        return User::factory()->create([
            'role' => 'frontoffice',
            'is_active' => true,
        ]);
    }

    public function test_dashboard_daily_view_success()
    {
        // 1. ARRANGE
        $foUser = $this->getFrontOfficeUser();

        // Buat 1 user member beserta data member-nya (untuk memastikan count dan recent_members terisi)
        $memberUser = User::factory()->create(['role' => 'member', 'is_active' => true]);
        Member::create([
            'user_id' => $memberUser->id,
            'member_number' => 'KOP-001',
            'full_name' => 'Budi Harian',
            'created_at' => Carbon::now() // Dibuat hari ini
        ]);

        // 2. ACT
        // Tanpa parameter tanggal, otomatis masuk ke Kondisi 1 (<= 7 hari)
        $response = $this->actingAs($foUser, 'api')->getJson($this->endpoint);

        // 3. ASSERT
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'stats' => ['total_member'],
                'chartData' => [
                    '*' => ['name', 'total', 'type']
                ],
                'recent_members' => [
                    '*' => [
                        'id',
                        'user_id',
                        'member_number',
                        'full_name',
                        'user' => ['id', 'email', 'is_active', 'nik']
                    ]
                ]
            ]
        ]);

        // Pastikan total member terbaca 1
        $this->assertEquals(1, $response->json('data.stats.total_member'));
    }

    public function test_dashboard_weekly_view_success()
    {
        // 1. ARRANGE
        $foUser = $this->getFrontOfficeUser();

        // Rentang waktu 14 hari (masuk Kondisi 2: 8 - 31 hari)
        $startDate = Carbon::now()->subDays(14)->toDateString();
        $endDate = Carbon::now()->toDateString();

        // 2. ACT
        $response = $this->actingAs($foUser, 'api')->getJson("{$this->endpoint}?start_date={$startDate}&end_date={$endDate}");

        // 3. ASSERT
        $response->assertStatus(200);

        // Pastikan penamaan chart sesuai format mingguan ("Mng 1", "Mng 2", dst)
        $this->assertStringContainsString('Mng', $response->json('data.chartData.0.name'));
    }

    public function test_dashboard_monthly_view_success()
    {
        // 1. ARRANGE
        $foUser = $this->getFrontOfficeUser();

        // Rentang waktu 3 bulan (masuk Kondisi 3: > 31 hari)
        $startDate = Carbon::now()->subMonths(3)->toDateString();
        $endDate = Carbon::now()->toDateString();

        // 2. ACT
        $response = $this->actingAs($foUser, 'api')->getJson("{$this->endpoint}?start_date={$startDate}&end_date={$endDate}");

        // 3. ASSERT
        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.chartData'));
    }

    public function test_dashboard_forbidden_for_non_frontoffice()
    {
        // 1. ARRANGE: Gunakan role 'member', bukan 'frontoffice'
        $memberUser = User::factory()->create([
            'role' => 'member',
            'is_active' => true,
        ]);

        // 2. ACT
        $response = $this->actingAs($memberUser, 'api')->getJson($this->endpoint);

        // 3. ASSERT
        // Harusnya ditolak oleh middleware `role:frontoffice`
        $response->assertStatus(403);
    }
}
