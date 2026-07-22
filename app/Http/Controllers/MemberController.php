<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        try {
            $limit = $request->query('limit', 10);
            $member = Member::with('user:id,name,email,nik')
                ->when($request->query('search'), function ($query, $search) {
                    return $query->where(function ($q) use ($search) {
                        $q->where('full_name', 'like', '%' . $search . '%')
                            ->orWhere('member_number', 'like', '%' . $search . '%')
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('nik', 'like', '%' . $search . '%')
                                    ->orWhere('email', 'like', '%' . $search . '%');
                            });
                    });
                })
                //Filter Rentang Tanggal (Start Date & End Date)
                ->when($request->query('start_date') && $request->query('end_date'), function ($query) use ($request) {
                    $start = Carbon::parse($request->query('start_date'))->startOfDay();
                    $end = Carbon::parse($request->query('end_date'))->endOfDay();
                    return $query->whereBetween('created_at', [$start, $end]);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($limit);

            $data = $member->items();

            $meta = [
                'current_page' => $member->currentPage(),
                'per_page'     => $member->perPage(),
                'total'        => $member->total(),
                'last_page'    => $member->lastPage(),
            ];

            return $this->apiService->successWithDataMeta($data, $meta, 'Data Member berhasil diambil');
        } catch (\Exception $e) {
            Log::error('Gagal mengambil list Member: ' . $e->getMessage());
            return $this->apiService->error('Terjadi kesalahan pada server saat mengambil data.', 500);
        }
    }
}
