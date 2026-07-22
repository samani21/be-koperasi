<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login_id' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->apiService->error(
                'Harap isi Email/NIK dan Password',
                422,
                $validator->errors()
            );
        }

        $loginId = trim($request->login_id);

        // 1. BUAT KUNCI THROTTLE (Gabungan Email/NIK dan IP Address)
        // Ini memastikan jika 1 akun diserang, IP penyerang diblokir tanpa mengganggu pengguna sah di IP lain.
        $throttleKey = strtolower($loginId) . '|' . $request->ip();

        // 2. CEK STATUS RATE LIMITER (Maksimal 5x Coba)
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return $this->apiService->error(
                "Terlalu banyak percobaan. Silakan coba lagi dalam {$seconds} detik.",
                429 // 429: Too Many Requests
            );
        }

        $loginType = filter_var($loginId, FILTER_VALIDATE_EMAIL) ? 'email' : 'nik';

        $credentials = [
            $loginType => $loginId,
            'password' => $request->password,
        ];

        $token = Auth::guard('api')->attempt($credentials);

        if (!$token) {
            // 3. CATAT KEGAGALAN (Hit)
            // Set decay time ke 60 detik (1 Menit)
            RateLimiter::hit($throttleKey, 60);

            $retriesLeft = RateLimiter::retriesLeft($throttleKey, 5);
            return $this->apiService->error("Email/NIK atau password salah. Sisa percobaan: {$retriesLeft}x", 401);
        }

        // 4. BERSIHKAN RATE LIMITER JIKA BERHASIL LOGIN
        RateLimiter::clear($throttleKey);

        $user = Auth::guard('api')->user();

        if (!$user->is_active) {
            Auth::guard('api')->logout();
            return $this->apiService->error('Akun Anda dinonaktifkan. Silakan hubungi Super Admin.', 403);
        }

        // 5. ANTISIPASI ERROR RELASI KOSONG & OPTIMASI N+1
        // Menggunakan safe navigation operator (??) untuk mencegah crash jika relasi member tidak ditemukan
        $user->load('member');
        $dataUser = [
            'name' => $user->name,
            'role' => $user->role,
            'email' => $user->email,
            'nik' => $user->nik,
            'is_active' => $user->is_active,
            'photo' => $user->member->photo ?? null
        ];
        $data = [
            'user'  => $dataUser,
            'token' => $token,
            'role'  => $user->role
        ];

        return $this->apiService->successWithData($data, 'Login berhasil', 200);
    }


    public function me()
    {
        // Langsung muat relasi 'member' jika dia adalah anggota koperasi
        $user = Auth::guard('api')->user()->load('member');
        $dataUser = [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'role' => $user->role,
            'nik' => $user->nik,
            'photo' => $user->member->photo ?? null
        ];
        return $this->apiService->successWithData($dataUser, 'Data profil berhasil diambil', 200);
    }
}
