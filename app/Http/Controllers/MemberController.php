<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        try {
            $limit = $request->query('limit', 10);
            $member = Member::with('user:id,name,email,nik,is_active')
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

    public function show(Request $request)
    {
        $user = Auth::guard('api')->user();

        // 1. Ambil data member beserta relasi user
        $member = Member::with('user:id,name,email,is_active')->where('user_id', $user->id)->first();

        // 2. Pengecekan jika data member belum ada/tidak ditemukan
        if (!$member) {
            return $this->apiService->error('Data Member tidak ditemukan', 404);
        }

        return $this->apiService->successWithData($member, 'Data Member berhasil diambil');
    }

    public function store(Request $request)
    {
        // 1. Tambahkan validasi photo (opsional, harus gambar, max 2MB)
        $request->validate([
            'nik'       => 'required|string|unique:users,nik',
            'email'     => 'nullable|email|unique:users,email',
            'password'  => 'nullable|min:6',
            'full_name' => 'required|string|max:255',
            'phone'     => 'nullable|string|max:20',
            'address'   => 'nullable|string',
            'photo'     => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        try {
            DB::beginTransaction();

            // Tentukan Password (Default NIK)
            $password = $request->filled('password') ? $request->password : $request->nik;

            // 2. Buat Akun User
            $user = User::create([
                'name'      => $request->full_name,
                'nik'       => $request->nik,
                'email'     => $request->email,
                'password'  => Hash::make($password),
                'role'      => 'member',
                'is_active' => true,
            ]);

            // 3. Generate Nomor Anggota Otomatis
            $year = date('Y');
            $lastMember = Member::where('member_number', 'like', "KOP-{$year}-%")->orderBy('id', 'desc')->first();
            $sequence = $lastMember ? ((int) substr($lastMember->member_number, -4) + 1) : 1;

            $memberNumber = sprintf("KOP-%s-%04d", $year, $sequence);
            while (Member::where('member_number', $memberNumber)->exists()) {
                $sequence++;
                $memberNumber = sprintf("KOP-%s-%04d", $year, $sequence);
            }

            // 4. Proses Upload & Convert Foto ke WebP
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photo = $request->file('photo');

                // Format nama file jadi huruf kecil semua (contoh: kop-2026-0286.webp)
                $filename = strtolower($memberNumber) . '.webp';

                // Tentukan lokasi folder (storage/app/public/anggota)
                $storageDirectory = storage_path('app/public/anggota');

                // Bikin foldernya kalau belum ada
                if (!file_exists($storageDirectory)) {
                    mkdir($storageDirectory, 0755, true);
                }

                $destinationPath = $storageDirectory . '/' . $filename;

                // Konversi gambar ke WebP menggunakan native PHP GD
                $image = imagecreatefromstring(file_get_contents($photo->getPathname()));

                // Setup agar transparansi (PNG) tidak berubah jadi latar hitam
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);

                // Simpan gambar sebagai WebP (Angka 80 adalah persentase kualitas gambar)
                imagewebp($image, $destinationPath, 80);

                // Bersihkan memory
                imagedestroy($image);

                // Path yang disimpan ke database agar terbaca di tag <img src="..." />
                $photoPath = '/storage/anggota/' . $filename;
            }

            // 5. Buat Profil Member
            $member = Member::create([
                'user_id'       => $user->id,
                'member_number' => $memberNumber,
                'full_name'     => $request->full_name,
                'phone'         => $request->phone,
                'address'       => $request->address,
                'photo'         => $photoPath, // Masukkan path foto
            ]);

            DB::commit();


            return $this->apiService->successWithData($member->load('user'), 'Member berhasil ditambahkan');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstError = $e->validator->errors()->first();
            return $this->apiService->errorResponse($firstError, 422, $e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiService->errorResponse('Gagal menambahkan member: ' . $e->getMessage(), 500);
        }
    }



    public function update(Request $request, $id)
    {
        try {
            $member = Member::with('user')->findOrFail($id);
            $user = $member->user;

            $request->validate([
                'nik'       => 'required|string|unique:users,nik,' . $user->id,
                'email'     => 'nullable|email|unique:users,email,' . $user->id,
                'password'  => 'nullable|min:6',
                'full_name' => 'required|string|max:255',
                'phone'     => 'nullable|string|max:20',
                'address'   => 'nullable|string',
                'photo'     => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'remove_photo' => 'nullable|boolean', // Validasi flag baru
            ]);

            DB::beginTransaction();

            $userData = [
                'name'  => $request->full_name,
                'nik'   => $request->nik,
                'email' => $request->email,
            ];

            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }
            $user->update($userData);

            //  Logika Baru: Manajemen Foto
            $photoPath = $member->photo;

            // SKENARIO 1: User menekan tombol "X" untuk hapus foto tanpa upload baru
            if ($request->boolean('remove_photo')) {
                if ($photoPath) {
                    $oldFilePath = str_replace('/storage/', storage_path('app/public/'), $photoPath);
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath); // Bersihkan file dari SSD/Hardisk
                    }
                }
                $photoPath = null; // Set path DB menjadi null
            }
            // SKENARIO 2: User mengupload foto baru (timpa yang lama)
            elseif ($request->hasFile('photo')) {
                // Hapus yang lama dulu jika ada
                if ($photoPath) {
                    $oldFilePath = str_replace('/storage/', storage_path('app/public/'), $photoPath);
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                $photo = $request->file('photo');
                $filename = strtolower($member->member_number) . '.webp';
                $storageDirectory = storage_path('app/public/anggota');

                if (!file_exists($storageDirectory)) {
                    mkdir($storageDirectory, 0755, true);
                }

                $destinationPath = $storageDirectory . '/' . $filename;

                $image = imagecreatefromstring(file_get_contents($photo->getPathname()));
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                imagewebp($image, $destinationPath, 80);
                imagedestroy($image);

                $photoPath = '/storage/anggota/' . $filename;
            }

            // Update Profil Member
            $member->update([
                'full_name' => $request->full_name,
                'phone'     => $request->phone,
                'address'   => $request->address,
                'photo'     => $photoPath, // Akan bernilai null jika remove_photo = true
            ]);

            DB::commit();
            return $this->apiService->successWithData($member->load('user'), 'Data Anggota berhasil diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstError = $e->validator->errors()->first();
            return $this->apiService->errorResponse($firstError, 422, $e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal update Member: ' . $e->getMessage());
            return $this->apiService->errorResponse('Gagal memperbarui data: ' . $e->getMessage(), 500);
        }
    }


    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $member = Member::with('user')->findOrFail($id);

            // 1. Hapus Foto Secara Fisik
            if ($member->photo) {
                // Konversi URL path menjadi Physical Path
                $filePath = str_replace('/storage/', storage_path('app/public/'), $member->photo);
                if (file_exists($filePath)) {
                    unlink($filePath); // Bakar file fisiknya!
                }
            }
            $user = $member->user;

            $member->delete(); // Hapus profilnya dulu

            if ($user) {
                $user->delete(); // Baru hapus akun login-nya
            }

            DB::commit();

            return $this->apiService->success('Data Anggota beserta foto berhasil dihapus permanen');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal hapus Member: ' . $e->getMessage());
            return $this->apiService->errorResponse('Gagal menghapus data: ' . $e->getMessage(), 500);
        }
    }
}
