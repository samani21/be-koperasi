<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SuperAdminController extends Controller
{
    public function dashboardStats(Request $request)
    {
        // Ambil filter tanggal dari React, set default ke 7 hari terakhir
        $startDate = $request->query('start_date', \Carbon\Carbon::now()->subDays(6)->toDateString());
        $endDate = $request->query('end_date', \Carbon\Carbon::now()->toDateString());

        // Pastikan format tanggal valid
        $start = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end = \Carbon\Carbon::parse($endDate)->endOfDay();

        // Hitung selisih hari
        $diffDays = (int) $start->diffInDays($end);

        $totalMember = User::where('role', 'member')->count();
        $totalFO = User::where('role', 'frontoffice')->count();

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
                'total_fo' => $totalFO,
            ],
            'chartData' => $chartData,
            'recent_members' => $recentMembers
        ];

        return $this->apiService->successWithData($data, 'Data dashboard berhasil diambil', 200);
    }

    public function index(Request $request)
    {
        try {
            $limit = $request->query('limit', 10);

            // Eksekusi query dengan paginate
            $frontOffices = User::where('role', 'frontoffice')
                ->when($request->query('search'), function ($query, $name) {
                    return $query->where('name', 'like', '%' . $name . '%');
                })
                ->orderBy('created_at', 'desc')
                ->paginate($limit);

            // Pisahkan data utama (array of objects)
            $data = $frontOffices->items();

            // Buat struktur Meta untuk kebutuhan Frontend
            $meta = [
                'current_page' => $frontOffices->currentPage(),
                'per_page'     => $frontOffices->perPage(),
                'total'        => $frontOffices->total(),
                'last_page'    => $frontOffices->lastPage(),
            ];

            return $this->apiService->successWithDataMeta($data, $meta, 'Data Front Office berhasil diambil');
        } catch (\Exception $e) {
            Log::error('Gagal mengambil list FO: ' . $e->getMessage());
            return $this->apiService->error('Terjadi kesalahan pada server saat mengambil data.', 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'nullable|string|min:6', // Boleh kosong
        ]);
        $user = auth()->user();
        if ($user->role != 'superadmin') {
            return $this->apiService->error('tidak bisa nambah data', 401);
        }
        if ($validator->fails()) {
            return $this->apiService->error('Periksa kembali inputan Anda.', 422, $validator->errors());
        }

        DB::beginTransaction();

        try {
            //  Jika password kosong, jadikan 'name' sebagai password default
            $rawPassword = $request->filled('password') ? $request->password : $request->name;

            $frontOffice = User::create([
                'name'      => $request->name,
                'email'     => $request->email,
                'password'  => Hash::make($rawPassword),
                'role'      => 'frontoffice', // Paksa role menjadi FO
                'is_active' => true, // Default langsung aktif
            ]);

            DB::commit();
            return $this->apiService->successWithData($frontOffice, 'Akun Front Office berhasil dibuat', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal membuat FO baru: ' . $e->getMessage());
            return $this->apiService->error('Gagal membuat akun. Silakan coba beberapa saat lagi.', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $frontOffice = User::where('role', 'frontoffice')->find($id);

            if (!$frontOffice) {
                return $this->apiService->error('Data Front Office tidak ditemukan', 404);
            }

            $validator = Validator::make($request->all(), [
                'name'     => 'required|string|max:255',
                'email'    => 'required|string|email|max:255|unique:users,email,' . $id,
                'password' => 'nullable|string|min:6',
            ]);

            if ($validator->fails()) {
                return $this->apiService->error('Gagal memperbarui data.', 422, $validator->errors());
            }

            DB::beginTransaction();

            // Update name dan email
            $updateData = [
                'name'  => $request->name,
                'email' => $request->email,
            ];

            // Saat update, jika form password diisi, baru kita ubah passwordnya.
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $frontOffice->update($updateData);

            DB::commit();
            return $this->apiService->successWithData($frontOffice, 'Data Front Office berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal update FO ID ' . $id . ': ' . $e->getMessage());
            return $this->apiService->error('Gagal memperbarui data. Coba lagi nanti.', 500);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $frontOffice = User::where('role', 'frontoffice')->find($id);

            if (!$frontOffice) {
                return $this->apiService->error('Data Front Office tidak ditemukan', 404);
            }

            $frontOffice->delete();

            DB::commit();
            return $this->apiService->success('Data Front Office berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal menghapus FO ID ' . $id . ': ' . $e->getMessage());
            return $this->apiService->error('Gagal menghapus! Akun ini mungkin masih terikat dengan data transaksi aktif.', 409);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->apiService->error('Akun tidak ditemukan', 404);
            }

            //  Cegah Super Admin menonaktifkan dirinya sendiri (Harakiri)
            if ($user->id === auth()->guard('api')->id()) {
                return $this->apiService->error('Anda tidak dapat menonaktifkan akun Anda sendiri!', 403);
            }

            // Balikkan status (Jika true jadi false, jika false jadi true)
            $user->is_active = !$user->is_active;
            $user->save();

            $pesan = $user->is_active ? 'diaktifkan' : 'dinonaktifkan (diblokir dari login)';
            return $this->apiService->successWithData($user, "Akun berhasil $pesan");
        } catch (\Exception $e) {
            Log::error('Gagal toggle status akun ID ' . $id . ': ' . $e->getMessage());
            return $this->apiService->error('Terjadi kesalahan pada server saat mengubah status.', 500);
        }
    }
}
