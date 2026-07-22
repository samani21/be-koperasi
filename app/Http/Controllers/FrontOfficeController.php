<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FrontOfficeController extends Controller
{
    public function dashboard(Request $request)
    {
        // Ambil filter tanggal dari React, set default ke 7 hari terakhir
        $startDate = $request->query('start_date', Carbon::now()->subDays(6)->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->toDateString());

        // Pastikan format tanggal valid
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Hitung selisih hari
        $diffDays = (int) $start->diffInDays($end);

        $totalMember = User::where('role', 'member')->count();

        $chartData = [];

        // KONDISI 1: TAMPILAN HARIAN (<= 7 Hari)
        if ($diffDays <= 7) {
            $membersQuery = User::where('role', 'member')
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->pluck('count', 'date');

            $currentDate = $start->copy();
            $daysMap = [
                'Sun' => 'Min',
                'Mon' => 'Sen',
                'Tue' => 'Sel',
                'Wed' => 'Rab',
                'Thu' => 'Kam',
                'Fri' => 'Jum',
                'Sat' => 'Sab'
            ];

            while ($currentDate <= $end) {
                $chartData[] = [
                    'name' => $daysMap[$currentDate->format('D')],
                    'total' => $membersQuery[$currentDate->toDateString()] ?? 0,
                    'type' => 'Pendaftaran Anggota'
                ];
                $currentDate->addDay();
            }
        }
        // KONDISI 2: TAMPILAN MINGGUAN (8 - 31 Hari)
        elseif ($diffDays <= 31) {
            $membersQuery = User::where('role', 'member')
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->pluck('count', 'date');

            $totalWeeks = ceil(($diffDays === 0 ? 1 : $diffDays) / 7);
            $currentDate = $start->copy();

            for ($i = 1; $i <= $totalWeeks; $i++) {
                $weekTotal = 0;
                for ($d = 0; $d < 7; $d++) {
                    if ($currentDate > $end) break;
                    $weekTotal += $membersQuery[$currentDate->toDateString()] ?? 0;
                    $currentDate->addDay();
                }
                $chartData[] = [
                    'name' => "Mng $i",
                    'total' => $weekTotal,
                    'type' => 'Pendaftaran Anggota'
                ];
            }
        }
        // KONDISI 3: TAMPILAN BULANAN (> 31 Hari)
        else {
            $membersQuery = User::where('role', 'member')
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as date, COUNT(*) as count')
                ->groupBy('date')
                ->pluck('count', 'date');

            $currentDate = $start->copy()->startOfMonth();
            $endLoop = $end->copy()->endOfMonth();

            $monthsMap = [
                '01' => 'Jan',
                '02' => 'Feb',
                '03' => 'Mar',
                '04' => 'Apr',
                '05' => 'Mei',
                '06' => 'Jun',
                '07' => 'Jul',
                '08' => 'Agu',
                '09' => 'Sep',
                '10' => 'Okt',
                '11' => 'Nov',
                '12' => 'Des'
            ];

            while ($currentDate <= $endLoop) {
                $dateString = $currentDate->format('Y-m');
                $monthNum = $currentDate->format('m');
                $year = $currentDate->format('y');

                $chartData[] = [
                    'name' => $monthsMap[$monthNum] . " " . $year,
                    'total' => $membersQuery[$dateString] ?? 0,
                    'type' => 'Pendaftaran Anggota'
                ];
                $currentDate->addMonth();
            }
        }

        // Ambil 10 Member Terbaru ---
        $recentMembers = Member::with('user:id,email,is_active,nik')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $data = [
            'stats' => [
                'total_member' => $totalMember,
            ],
            'chartData' => $chartData,
            'recent_members' => $recentMembers
        ];

        return $this->apiService->successWithData($data, 'Data dashboard berhasil diambil', 200);
    }
}
